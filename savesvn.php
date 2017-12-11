<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(0);

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'shared/connect.php';
require_once 'shared/TokenGenerator.php';

header('Content-Type: application/json');

$request = json_decode(file_get_contents('php://input'), true);

if (!isset($request) || !isset($request['action'])) {
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

function listTaskDirs($dir, $recursive) {
    $filenames = scandir(__DIR__.'/files/checkouts/'.$dir.'/');
    foreach($filenames as $filename) {
        if(preg_match('/^index.*\.html/', $filename) === 1 && file_exists(__DIR__.'/files/checkouts/'.$dir.'/'.$filename)) {
            return array($dir);
        }
    }

    // No task file has been found
    if(!$recursive) {
        die(json_encode(['success' => false, 'error' => 'error_noindex']));
    }

    $taskDirs = array();
    foreach(scandir(__DIR__.'/files/checkouts/'.$dir) as $elem) {
        $elemPath = $dir.'/'.$elem;
        if(is_dir(__DIR__.'/files/checkouts/'.$elemPath) && $elem != '.' && $elem != '..') {
            $taskDirs = array_merge($taskDirs, listTaskDirs($elemPath, $filenames, $recursive));
        }
    }
    return $taskDirs;
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

function processDir($taskDir, $baseSvnFirst) {
    global $config;

    // Remove first component of the path
    $taskDirCompl = implode('/', array_slice(explode('/', $taskDir), 1));
    $taskSvnDir = $baseSvnFirst . '/' . $taskDirCompl;

    $indexList = [];
    $taskDirMoved = false;

    $filenames = scandir(__DIR__.'/files/checkouts/'.$taskDir.'/');

    foreach($filenames as $filename) {
        if(preg_match('/index.*\.html/', $filename) !== 1) {
            continue;
        }
        if(!file_exists(__DIR__.'/files/checkouts/'.$taskDir.'/'.$filename)) {
            continue;
        }
        if(checkStatic(__DIR__.'/files/checkouts/'.$taskDir.'/'.$filename)) {
            if(!$taskDirMoved) {
                // Move task to a static location
                $targetDir = md5($taskSvnDir). '/' . $taskDirCompl;
                $targetFsDir = __DIR__.'/files/checkouts/'.$targetDir;
                mkdir($targetFsDir, 0777, true);
                deleteRecDirectory($targetFsDir);
                rename(__DIR__.'/files/checkouts/'.$taskDir, $targetFsDir);
                $taskDir = $targetDir;
                $taskDirMoved = true;
            }

            $indexList[] = [
                'filename' => $filename,
                'isStatic' => true
                ];
        } else {
            $indexList[] = [
                'filename' => $filename,
                'isStatic' => false,
                'warnPaths' => warnPaths(__DIR__.'/files/checkouts/'.$taskDir.'/'.$filename)
                ];
        }
    }
    $taskData = [
        'dirPath' => $taskDir,
        'ID' => $taskDir,
        'svnUrl' => $taskSvnDir,
        'baseUrl' => $config->baseUrl.'/files/checkouts/'.$taskDir.'/',
        'staticUrl' => $config->staticUrl.$taskDir.'/',
        'files' => $indexList
        ];

    if(file_exists(__DIR__.'/files/checkouts/'.$taskDir.'/ref_lang.txt')) {
        $taskData['refLang'] = trim(file_get_contents(__DIR__.'/files/checkouts/'.$taskDir.'/ref_lang.txt'));
    }

    return $taskData;
}

function checkoutSvn($subdir, $user, $password, $userRevision, $recursive, $noimport) {
	global $config, $db;

    if($recursive && $noimport) {
        // TODO :: adapt to recursive
        // not supported (yet)
        echo(json_encode([
            'success' => false,
            'error' => 'error_recno_unsupported'
            ]));
        return;
    }

	$baseSvnDir = trim($subdir);
	$baseSvnDir = trim($baseSvnDir, '/');

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
		die(json_encode(['success' => false, 'error' => 'error_checkout']));
	}

    // Get _local_common
    $localCommonSvn = $baseSvnFirst.'/_local_common';
    $localCommonDir = __DIR__.'/files/checkouts/local/'.$baseSvnFirst;
    $localCommonExists = is_dir($localCommonDir);
    $localCommonTargetDir = $localCommonExists ? $localCommonDir . '_new' : $localCommonDir;
    mkdir($localCommonTargetDir, 0777, true);
    try {
        svn_checkout($config->svnBaseUrl.$localCommonSvn, $localCommonTargetDir);
        if($localCommonExists) {
            deleteRecDirectory($localCommonDir);
            rename($localCommonTargetDir, $localCommonDir);
        }
        $localCommonExists = true;
    } catch(Exception $e) {}

	if($noimport) {
        // TODO :: adapt to recursive
	    $sTaskPath = '$ROOT_PATH/'.$baseSvnDir;
    	$stmt = $db->prepare('select ID, sRevision from tm_tasks where sTaskPath = :sTaskPath');
    	$stmt->execute(['sTaskPath' => $sTaskPath]);
		$taskInfo = $stmt->fetch();
		if ($taskInfo) {
            $tasks = [[
                'imported' => true,
                'svnUrl' => $baseSvnDir,
                'ltiUrl' => $config->ltiUrl.$taskInfo['ID'],
                'normalUrl' => $config->normalUrl.$taskInfo['ID'],
                'tokenUrl' => addToken($config->normalUrl.$taskInfo['ID']),
                ]];
			echo(json_encode([
                'success' => true,
                'revision' => $taskInfo['sRevision'],
                'tasks' => $tasks
                ]));
			return;
		} elseif ($noimport) {
            echo(json_encode([
                'success' => false,
                'error' => 'error_notyetimported'
                ]));
            return;
        }
	}

    $tasks = array();
    $taskDirs = listTaskDirs($baseTargetDir, $recursive);
    foreach($taskDirs as $taskDir) {
        $newTaskData = processDir($taskDir, $baseSvnFirst);
        if(count($newTaskData['files']) > 0) {
            $tasks[] = $newTaskData;
        }
    }
    echo(json_encode(['success' => $success, 'tasks' => $tasks, 'revision' => $revision, 'localCommon' => $localCommonExists]));

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

    $baseSvnFirst = explode('/', $dirPath)[0];
    $localCommonDir = __DIR__.'/files/checkouts/local/'.$baseSvnFirst;
    $localCommonExists = is_dir($localCommonDir);

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

    if($localCommonExists) {
        $solution = str_replace('_local_common', '../local/'.$baseSvnFirst, $solution);
        $statement = str_replace('_local_common', '../local/'.$baseSvnFirst, $statement);
    }

	$stmt = $db->prepare('insert into tm_tasks_strings (idTask, sLanguage, sTitle, sStatement, sSolution) values (:idTask, :sLanguage, :sTitle, :sStatement, :sSolution) on duplicate key update sTitle = values(sTitle), sStatement = values(sStatement), sSolution = values(sSolution);');
	$stmt->execute(['idTask' => $taskId, 'sLanguage' => $metadata['language'], 'sTitle' => $metadata['title'], 'sStatement' => $statement, 'sSolution' => $solution]);
}

function saveHints($taskId, $hintsResources, $metadata) {
	global $db;
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
		die(json_encode(['success' => false, 'error' => 'missing name field in answer resource']));
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

function saveResources($data, $subdir, $revision, $dirPath) {
	global $config, $db;
	$subdir = trim($subdir);
	$subdir = trim($subdir, '/');

    $metadata = $data[0]['metadata'];
    $resources = $data[0]['resources'];

	if (!isset($metadata['id']) || !isset($metadata['language'])) {
		die(json_encode(['success' => false, 'error' => 'missing id or language in metadata']));
	}
	$textId = $metadata['id'];
	// save task to get ID
	list($taskId, $alreadyImported) = saveTask($metadata, $subdir, $revision, $resources);
	if (!$alreadyImported) {
		// limits
		saveLimits($taskId, $metadata['limits']);
		// source code
		saveSourceCodes($taskId, $resources);
		saveSamples($taskId, $resources);
        // subtasks
        saveSubtasks($taskId, $metadata);

        // Delete former lang-specific data (strings and hints)
        $stmt = $db->prepare('delete from tm_tasks_strings where idTask = :idTask;');
        $stmt->execute(['idTask' => $taskId]);
        $deleteQuery = 'delete tm_hints_strings from tm_hints_strings join tm_hints on tm_hints_strings.idHint = tm_hints.ID where tm_hints.idTask = :idTask;';
        $stmt = $db->prepare($deleteQuery);
        $stmt->execute(['idTask' => $taskId]);
        $deleteQuery = 'delete from tm_hints where tm_hints.idTask = :idTask';
        $stmt = $db->prepare($deleteQuery);
        $stmt->execute(['idTask' => $taskId]);

        foreach($data as $langData) {
            // lang-specific data
            $metadata = $langData['metadata'];
            $resources = $langData['resources'];
    		// strings
    		saveStrings($taskId, $resources, $metadata, $dirPath);
    		// hints
    		$hintsResources = isset($resources['hints']) ? $resources['hints'] : [];
    		saveHints($taskId, $hintsResources, $metadata);
        }
	}
	echo(json_encode([
        'success' => true,
        'ID' => $taskId,
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
		die(json_encode(['success' => false, 'error' => 'error_request']));
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




if ($request['action'] == 'checkoutSvn') {
	if (!isset($request['svnUrl'])) {
		die(json_encode(['success' => false, 'error' => 'error_request']));
	}
	$user = $request['username'] ? $request['username'] : $config->defaultSvnUser;
	$password = $request['password'] ? $request['password'] : $config->defaultSvnPassword;
	checkoutSvn($request['svnUrl'], $user, $password, $request['svnRev'], isset($request['recursive']), isset($request['noimport']));
} elseif ($request['action'] == 'saveResources') {
	if (!isset($request['data']) || !isset($request['svnUrl']) || !isset($request['svnRev'])) {
		die(json_encode(['success' => false, 'error' => 'error_request']));
	}
	saveResources($request['data'], $request['svnUrl'], $request['svnRev'], $request['dirPath']);
} elseif ($request['action'] == 'deletedirectory') {
	if (!isset($request['ID'])) {
		die(json_encode(['success' => false, 'error' => 'error_request']));
	}
	deleteDirectory($request['ID']);
} else {
	echo(json_encode(['success' => false, 'error' => 'error_action']));	
}
