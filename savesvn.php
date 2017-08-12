<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(0);

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'shared/connect.php';
require_once 'shared/TokenGenerator.php';

header('Content-Type: application/json');

if (!isset($_POST) || !isset($_POST['action'])) {
	die(json_encode(['success' => false, 'error' => 'missing action']));
}

function getLastRevision($dir) {
	$status = svn_status($dir, SVN_ALL);
	$maxRev = 0;
	foreach ($status as $i => $filestatus) {
		if ($filestatus['cmt_rev'] > $maxRev) {
			$maxRev = $filestatus['cmt_rev'];
		}
	}
	return $maxRev;
}

function listTaskDirs($dir) {
    if (file_exists(__DIR__.'/files/checkouts/'.$dir.'/index.html')) {
        return array($dir);
    } else {
        $taskDirs = array();
        foreach(scandir(__DIR__.'/files/checkouts/'.$dir) as $elem) {
            $elemPath = $dir.'/'.$elem;
            if(is_dir(__DIR__.'/files/checkouts/'.$elemPath) && $elem != '.' && $elem != '..') {
                $taskDirs = array_merge($taskDirs, listTaskDirs($elemPath));
            }
        }
        return $taskDirs;
    }
}

function checkStatic($path) {
    $handle = fopen($path, 'r');
    while(!feof($handle)) {
        $line = fgets($handle);
        if(strstr($line, 'PEMTaskMetaData')) {
            return false;
        }
    }
    return true;
}

function warnPaths($path) {
    $handle = fopen($path, 'r');
    while(!feof($handle)) {
        $line = fgets($handle);
        if(preg_match('/= *[\'"]([^=]+)_common/', $line, $matches)) {
            if (!is_dir(dirname($path) . '/' . $matches[1] . '/_common')) {
                return true;
            }
        }
    }
    return false;
}

function checkoutSvn($subdir, $user, $password, $userRevision, $recursive, $noimport) {
	global $config, $db;

    if($recursive && $noimport) {
        // TODO :: adapt to recursive
        // not supported (yet)
        echo(json_encode([
            'success' => false,
            'error' => 'Cannot use recursive mode when not importing. Uncheck one of the boxes.'
            ]));
        return;
    }

	$baseSvnDir = trim($subdir);
	$baseSvnDir = trim($baseSvnDir, '/');
	$sTaskPath = '$ROOT_PATH/'.$baseSvnDir; // TODO :: adapt to recursive

	// Create target checkout directory
	$baseSvnExpl = explode('/', $baseSvnDir);
    $baseSvnFirst = array_shift($baseSvnExpl);
	$baseTargetDir = mt_rand(100000, mt_getrandmax());
    if(count($baseSvnExpl)) {
        $baseTargetDir .= '/' . implode('/', $baseSvnExpl);
    }
    mkdir(__DIR__.'/files/checkouts/'.$baseTargetDir, 0777, true);

	svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME,             $user);
	svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD,             $password);
	svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true); // <--- Important for certificate issues!
	svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE,              true);
	svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE,                true);
	$success = true;
	$url = $config->svnBaseUrl.$baseSvnDir;
	try {
		svn_update(__DIR__.'/files/checkouts/_common/');
		if ($userRevision) {
			$success = svn_checkout($url, __DIR__.'/files/checkouts/'.$baseTargetDir, $userRevision);
            $revision = $userRevision;
		} else {
			$success = svn_checkout($url, __DIR__.'/files/checkouts/'.$baseTargetDir);
			$revision = getLastRevision(__DIR__.'/files/checkouts/'.$baseTargetDir);
		}
	} catch (Exception $e) {
		die(json_encode(['success' => false, 'error' => $e->getMessage()]));
	}
	if (!$success) {
		die(json_encode(['success' => false, 'error' => 'impossible de faire un checkout de '.$url.' (répertoire inexistant ou identifiants invalides).']));
	}

	if ($userRevision || $noimport) {
        // TODO :: adapt to recursive
        if ($userRevision) {
    		$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
    		$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
        } else {
    		$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath');
    		$stmt->execute(['sTaskPath' => $sTaskPath]);
        }
		$ID = $stmt->fetchColumn();
		if ($ID) {
			echo(json_encode([
                'success' => true,
                'ltiUrl' => $config->ltiUrl.$ID,
                'normalUrl' => $config->normalUrl.$ID,
                'tokenUrl' => addToken($config->normalUrl.$ID),
                'reason' => 'revision already in db'
                ]));
			return;
		} elseif ($noimport) {
            echo(json_encode([
                'success' => false,
                'error' => 'This task has not been imported yet.'
                ]));
            return;
        }
	}

    $tasks = array();
    if ($recursive) {
        $taskDirs = listTaskDirs($baseTargetDir);
    } else {
    	if (!file_exists(__DIR__.'/files/checkouts/'.$baseTargetDir.'/index.html')) {
    		die(json_encode(['success' => false, 'error' => 'le fichier index.html n\'existe pas !']));
    	}
        $taskDirs = array($baseTargetDir);
    }
    foreach($taskDirs as $taskDir) {
        // Remove first component of the path
        $taskDirCompl = implode('/', array_slice(explode('/', $taskDir), 1));
        $taskSvnDir = $baseSvnFirst . '/' . $taskDirCompl;

        if(checkStatic(__DIR__.'/files/checkouts/'.$taskDir.'/index.html')) {
            $targetDir = md5($taskSvnDir). '/' . $taskDirCompl;
            $targetFsDir = __DIR__.'/files/checkouts/'.$targetDir;
            mkdir($targetFsDir, 0777, true);
            deleteRecDirectory($targetFsDir);
            rename(__DIR__.'/files/checkouts/'.$taskDir, $targetFsDir);

            $tasks[] = [
                'dirPath' => $targetDir,
                'ID' => $targetDir,
                'url' => $config->baseUrl.'/files/checkouts/'.$targetDir.'/index.html',
                'svnUrl' => $taskSvnDir,
                'isstatic' => true,
                'normalUrl' => $config->staticUrl.$targetDir.'/index.html',
                'ltiUrl' => $config->staticUrl.$targetDir.'/index.html',
                ];
        } else {
            $tasks[] = [
                'dirPath' => $taskDir,
                'ID' => $taskDir,
                'url' => $config->baseUrl.'/files/checkouts/'.$taskDir.'/index.html',
                'svnUrl' => $taskSvnDir,
                'warnPaths' => warnPaths(__DIR__.'/files/checkouts/'.$taskDir.'/index.html'),
                ];
        }
    }
    echo(json_encode(['success' => $success, 'tasks' => $tasks, 'revision' => $revision]));
/* TODO :: do something with that
        	$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
        	$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
        	$ID = $stmt->fetchColumn();
        	if ($ID && false) { // XXX :: remove false
        		//deleteRecDirectory(__DIR__.'/files/checkouts/'.$dir);
        		echo(json_encode([
                    'success' => $success,
                    'ltiUrl' => $config->ltiUrl.$ID,
                    'normalUrl' => $config->normalUrl.$ID,
                    'tokenUrl' => addToken($config->normalUrl.$ID),
                    'seenrevision' => $revision,
                    'dir' => $dir
                    ]));
        		return;
        	}*/
}

function saveLimits($taskId, $limits) {
	global $db;
	$deleteQuery = 'delete from tm_tasks_limits where tm_tasks_limits.idTask = :idTask;';
	$stmt = $db->prepare($deleteQuery);
	$stmt->execute(['idTask' => $taskId]);
	if (!$limits || !count($limits)) {
		return;
	}
	$stmt = $db->prepare('insert ignore into tm_tasks_limits (idTask, sLangProg, iMaxTime, iMaxMemory) values (:taskId, :lang, :maxTime, :maxMemory) on duplicate key update iMaxTime = values(iMaxTime), iMaxMemory = values(iMaxMemory);');
	foreach ($limits as $lang => $limits) {
		$maxTime = isset($limits['time']) ? $limits['time'] : 0;
		$maxMemory = isset($limits['memory']) ? $limits['memory'] : 0;
		$stmt->execute(['taskId' => $taskId, 'lang' => $lang, 'maxTime' => $maxTime, 'maxMemory' => $maxMemory]);
	}
}

function saveTask($metadata, $subdir, $revision, $resources) {
	global $db;
	$sTaskPath = '$ROOT_PATH/'.$subdir;
	$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
	$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
	$ID = $stmt->fetchColumn();
	if ($ID) {
		return [$ID, true];
	}
	$authors = (isset($metadata['authors']) && count($metadata['authors'])) ? join(',', $metadata['authors']) : '';
	$sSupportedLangProg = (isset($metadata['supportedLanguages']) && count($metadata['supportedLanguages'])) ? join(',', $metadata['supportedLanguages']) : '*';
	$sEvalTags = (isset($metadata['evaluationTags']) && count($metadata['evaluationTags'])) ? join(',', $metadata['evaluationTags']) : '';
	$bUserTests = isset($metadata['hasUserTests']) ? ($metadata['hasUserTests'] == 'true' ? 1 : 0) : 0;
	$sEvalResultOutputScript = isset($metadata['evalOutputScript']) ? $metadata['evalOutputScript'] : null;
	$sScriptAnimation = '';
	foreach ($resources['task'] as $i => $resource) {
		if ($resource['type'] == 'javascript' && $resource['id'] == 'animation' && isset($resource['content'])) {
			$sScriptAnimation = $resource['content'];
			break;
		}
	}
	$stmt = $db->prepare('insert into tm_tasks (sTextId, sSupportedLangProg, sEvalTags, sAuthor, bUserTests, sTaskPath, sRevision, sEvalResultOutputScript, sScriptAnimation) values (:id, :langprog, :evaltags, :authors, :bUserTests, :sTaskPath, :revision, :sEvalResultOutputScript, :sScriptAnimation) on duplicate key update sSupportedLangProg = values(sSupportedLangProg), sAuthor = values(sAuthor), bUserTests = values(bUserTests), sTaskPath = values(sTaskPath), sRevision = values(sRevision), sEvalResultOutputScript = values(sEvalResultOutputScript), sScriptAnimation = values(sScriptAnimation);');
	$stmt->execute(['id' => $metadata['id'], 'langprog' => $sSupportedLangProg, 'evaltags' => $sEvalTags, 'authors' => $authors, 'bUserTests' => $bUserTests, 'sTaskPath' => $sTaskPath, 'revision' => $revision, 'sEvalResultOutputScript' => $sEvalResultOutputScript, 'sScriptAnimation' => $sScriptAnimation]);
	$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
	$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
	$taskId = $stmt->fetchColumn();
	if (!$taskId) {
		die(json_encode(['success' => false, 'impossible to find id for '.$metadata['id']]));
	}
	return [$taskId, false];
}

function saveStrings($taskId, $resources, $metadata, $dirPath) {
	global $config, $db;
	$statement = null;
	$solution = null;
	$css = null;
	$imagesRes = [];
    $files = array();
	foreach ($resources['task'] as $i => $resource) {
		if ($resource['type'] == 'html') {
			$statement = $resource['content'];
		} elseif ($resource['type'] == 'css' && isset($resource['content'])) {
			$css = $resource['content'];
		} else if ($resource['type'] == 'image' && isset($resource['url'])) {
			$imagesRes[] = $resource;
		}
	}
	foreach($imagesRes as $imageRes) {
      if(!in_array($imageRes['url'], $files)) {
        $files[] = $imageRes['url'];
      }
	}
	$imagesRes = [];
	foreach ($resources['solution'] as $i => $resource) {
		if ($resource['type'] == 'html') {
			$solution = $resource['content'];
		} else if ($resource['type'] == 'image' && isset($resource['url'])) {
			$imagesRes[] = $resource;
		}
	}
	foreach($imagesRes as $imageRes) {
		$solution = str_replace ($imageRes['url'] , $config->staticUrl.$dirPath.'/'.$imageRes['url'], $solution);
	}

    // Save files
    foreach($resources['files'] as $f) {
      if(!in_array($f['url'], $files)) {
        $files[] = $f['url'];
      }
    }
    foreach((isset($metadata['otherFiles']) ? $metadata['otherFiles'] : []) as $f) {
      if(!in_array($f, $files)) {
        $files[] = $f;
      }
    }
    foreach($files as $f) {
      $statement = str_replace($f, $config->staticUrl.$dirPath.'/'.$f, $statement);
    }

	$stmt = $db->prepare('insert into tm_tasks_strings (idTask, sLanguage, sTitle, sStatement, sSolution) values (:idTask, :sLanguage, :sTitle, :sStatement, :sSolution) on duplicate key update sTitle = values(sTitle), sStatement = values(sStatement), sSolution = values(sSolution);');
	$stmt->execute(['idTask' => $taskId, 'sLanguage' => $metadata['language'], 'sTitle' => $metadata['title'], 'sStatement' => $statement, 'sSolution' => $solution]);
}

function saveHints($taskId, $hintsResources, $metadata) {
	global $db;
	$deleteQuery = 'delete tm_hints_strings from tm_hints_strings join tm_hints on tm_hints_strings.idHint = tm_hints.ID where tm_hints.idTask = :idTask;';
	$stmt = $db->prepare($deleteQuery);
	$stmt->execute(['idTask' => $taskId]);
	$deleteQuery = 'delete from tm_hints where tm_hints.idTask = :idTask;';
	$stmt = $db->prepare($deleteQuery);
	$stmt->execute(['idTask' => $taskId]);
	foreach ($hintsResources as $i => $resources) {
		$imagesRes = [];
		foreach ($resources as $j => $resource) {
			if ($resource['type'] == 'html') {
				$hint = $resource['content'];
			} else if ($resource['type'] == 'image' && isset($resource['content'])) {
				$imagesRes[] = $resource;
			}
		}
		if ($hint) {
			foreach($imagesRes as $imageRes) {
				$hint = str_replace ($imageRes['url'] , $imageRes['content'], $hint);
			}
			$stmt = $db->prepare('insert ignore into tm_hints (idTask, iRank) values (:idTask, :iRank);');
			$stmt->execute(['idTask' => $taskId, 'iRank' => $i+1]);
			$stmt = $db->prepare('select id from tm_hints where idTask = :idTask and iRank = :iRank;');
			$stmt->execute(['idTask' => $taskId, 'iRank' => $i+1]);
			$idHint = $stmt->fetchColumn();
			if (!$idHint) {
				die(json_encode(['success' => false, 'error' => 'impossible to find hint '.($i+1).' for task '.$taskId]));
			}
			$stmt = $db->prepare('insert ignore into tm_hints_strings (idHint, sLanguage, sContent) values (:idHint, :sLanguage, :sContent) on duplicate key update sContent = values(sContent);');
			$stmt->execute(['idHint' => $idHint, 'sLanguage' => $metadata['language'], 'sContent' => $hint]);
		}
	}
}

function saveAnswer($taskId, $answer) {
	global $db;
	$deleteQuery = 'delete from tm_source_codes where idTask = :idTask and sName = :sName and sType = :sType;';
	$insertQuery = 'insert into tm_source_codes (idTask, sDate, sParams, sName, sSource, bEditable, sType) values (:idTask, NOW(), :sParams, :sName, :sSource, 0, :sType);';
	$resourceName = $answer['name'];
	if (!$resourceName) {
		die(json_encode(['sucess' => false, 'error' => 'missing name field in answer resource']));
	}
	$stmt = $db->prepare($deleteQuery);
	$stmt->execute(['idTask' => $taskId, 'sName' => $resourceName, 'sType' => 'Task']);
	$stmt = $db->prepare($insertQuery);
	if (isset($answer['answerVersions']) && count($answer['answerVersions'])) {
		foreach ($answer['answerVersions'] as $i => $answerVersion) {
			$sParams = json_encode($answerVersion['params']);
			$stmt->execute(['idTask' => $taskId, 'sName' => $resourceName, 'sType' => 'Task', 'sParams' => $sParams, 'sSource' => $answerVersion['answerContent']]);
		}
	} elseif ($answer['answerContent']) {
		$sParams = json_encode($answer['params']);
		$stmt->execute(['idTask' => $taskId, 'sName' => $resourceName, 'sType' => 'Task', 'sParams' => $sParams, 'sSource' => $answer['answerContent']]);
	}
}

function saveSourceCodes($taskId, $resources) {
	foreach ($resources['task'] as $i => $resource) {
		if ($resource['type'] == 'answer') {
			saveAnswer($taskId, $resource);
		}
	}
	foreach ($resources['solution'] as $i => $resource) {
		if ($resource['type'] == 'answer') {
			saveAnswer($taskId, $resource);
		}
	}
}

function saveSamples($taskId, $resources) {
	global $db;
	$insertQuery = 'insert into tm_tasks_tests (idTask, sGroupType, sName, sInput, sOutput) values (:idTask, :sGroupType, :sName, :sInput, :sOutput);';
	$deleteQuery = 'delete from tm_tasks_tests where idTask = :idTask and sGroupType = :sGroupType and sName = :sName;';
	foreach ($resources['task'] as $i => $resource) {
		if ($resource['type'] == 'sample' && isset($resource['name']) && $resource['name']) {
			$stmt = $db->prepare($deleteQuery);
			$stmt->execute(['idTask' => $taskId, 'sGroupType' => 'Example', 'sName' => $resource['name']]);
			$stmt = $db->prepare($insertQuery);
			$stmt->execute(['idTask' => $taskId, 'sGroupType' => 'Example', 'sName' => $resource['name'], 'sInput' => $resource['inContent'], 'sOutput' => $resource['outContent']]);
		}
	}
	if (!isset($resources['grader'])) {
		return;
	}
	foreach ($resources['grader'] as $i => $resource) {
		if ($resource['type'] == 'sample' && isset($resource['name']) && $resource['name']) {
			$stmt = $db->prepare($deleteQuery);
			$stmt->execute(['idTask' => $taskId, 'sGroupType' => 'Evaluation', 'sName' => $resource['name']]);
			$stmt = $db->prepare($insertQuery);
			$stmt->execute(['idTask' => $taskId, 'sGroupType' => 'Evaluation', 'sName' => $resource['name'], 'sInput' => $resource['inContent'], 'sOutput' => $resource['outContent']]);
		}
	}
}

function saveSubtasks($taskId, $metadata) {
    // Save subtasks into the database from task metadata
    // Subtasks must be described in the metadata as a
    // list of objects representing subtasks, each subtask having a
    // name, comments, and gradeMax (sum of gradeMax must be 100)
    global $db;

    if(!isset($metadata['subtasks'])) { return; }

    $stmt = $db->prepare("DELETE FROM tm_tasks_subtasks where idTask = :idTask;");
    $stmt->execute(['idTask' => $taskId]);

    $iRank = 0;
    foreach($metadata['subtasks'] as $subtask) {
        $stmt = $db->prepare("INSERT INTO tm_tasks_subtasks (idTask, iRank, name, comments, iPointsMax) VALUES (:idTask, :iRank, :name, :comments, :iPointsMax);");
        $stmt->execute(['idTask' => $taskId, 'iRank' => $iRank, 'name' => $subtask['name'], 'comments' => $subtask['comments'], 'iPointsMax' => $subtask['gradeMax']]);
        $iRank += 1;
    }
}

function saveResources($metadata, $resources, $subdir, $revision, $dirPath) {
	global $config;
	$subdir = trim($subdir);
	$subdir = trim($subdir, '/');
	if (!isset($metadata['id']) || !isset($metadata['language'])) {
		die(json_encode(['success' => false, 'error' => 'missing id or language in metadata']));
	}
	$textId = $metadata['id'];
	// save task to get ID
	list($taskId, $alreadyImported) = saveTask($metadata, $subdir, $revision, $resources);
	if (!$alreadyImported) {
		// limits
		saveLimits($taskId, $metadata['limits']);
		// strings
		saveStrings($taskId, $resources, $metadata, $dirPath);
		// hints
		$hintsResources = isset($resources['hints']) ? $resources['hints'] : [];
		saveHints($taskId, $hintsResources, $metadata);
		// source code
		saveSourceCodes($taskId, $resources);
		saveSamples($taskId, $resources);
        // subtasks
        saveSubtasks($taskId, $metadata);
	}
	echo(json_encode([
        'success' => true,
        'normalUrl' => $config->normalUrl.$taskId,
        'tokenUrl' => addToken($config->normalUrl.$taskId),
        'ltiUrl' => $config->ltiUrl.$taskId
        ]));
}

// why is there no such thing in the php library?
function deleteRecDirectory($dir) {
	if (!$dir || substr($dir, 0, strlen(__DIR__.'/files/checkouts')) != __DIR__.'/files/checkouts') return;
	$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it,
	             RecursiveIteratorIterator::CHILD_FIRST);
	foreach($files as $file) {
	    if ($file->isDir()){
	        rmdir($file->getRealPath());
	    } else {
	        unlink($file->getRealPath());
	    }
	}
	rmdir($dir);	
}

function deleteDirectory($path) {
	$firstDir = explode('/', $path);
	$firstDir = $firstDir[0];
	$ID = intval($firstDir);
	if ($ID < 1) {
		die(json_encode(['success' => false, 'error' => 'invalid ID format (must be number)']));
	}
	//deleteRecDirectory(__DIR__.'/files/checkouts/'.$ID);
	echo(json_encode(['success' => true]));
}

function unparse_url($parsed_url) {
    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    return "$scheme$user$pass$host$port$path$query$fragment";
}

function addToken($url, $token=null) {
    if ($token == null) {
        $token = generateToken($url);
    }
    $parsed_url = parse_url($url);
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $queryArgs);
        $queryArgs['sToken'] = $token;
        $parsed_url['query'] = http_build_query($queryArgs);
    } else {
        $parsed_url['query'] = 'sToken=' . $token;
    }
    return unparse_url($parsed_url);
}

function generateToken($url) {
    global $config;

    $tokenGenerator = new TokenGenerator($config->platform->private_key, $config->platform->name);
    $params = array(
      'bAccessSolutions' => true,
      'bSubmissionPossible' => true,
      'bHintsAllowed' => true,
      'nbHintsGiven' => 0,
      'bIsAdmin' => false,
      'bReadAnswers' => true,
      'idUser' => 0,
      'idItemLocal' => 0,
      'itemUrl' => $url,
      'bHasSolvedTask' => false,
      'bTestMode' => true,
    );
    $sToken = $tokenGenerator->encodeJWS($params);
    return $sToken;
}




if ($_POST['action'] == 'checkoutSvn') {
	if (!isset($_POST['svnUrl'])) {
		die(json_encode(['success' => false, 'error' => 'missing svnUrl']));
	}
	$user = $_POST['username'] ? $_POST['username'] : $config->defaultSvnUser;
	$password = $_POST['password'] ? $_POST['password'] : $config->defaultSvnPassword;
	checkoutSvn($_POST['svnUrl'], $user, $password, $_POST['svnRev'], isset($_POST['recursive']), isset($_POST['noimport']));
} elseif ($_POST['action'] == 'saveResources') {
	if (!isset($_POST['metadata']) || !isset($_POST['resources']) || !isset($_POST['svnUrl']) || !isset($_POST['svnRev'])) {
		die(json_encode(['success' => false, 'error' => 'missing metada, resources, svnUrl or svnRev']));
	}
	saveResources($_POST['metadata'], $_POST['resources'], $_POST['svnUrl'], $_POST['svnRev'], $_POST['dirPath']);
} elseif ($_POST['action'] == 'deletedirectory') {
	if (!isset($_POST['ID'])) {
		die(json_encode(['success' => false, 'error' => 'missing directory']));
	}
	deleteDirectory($_POST['ID']);
} else {
	echo(json_encode(['success' => false, 'error' => 'unknown action '.$_POST['action']]));	
}
