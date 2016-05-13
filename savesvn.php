<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(0);

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'shared/connect.php';

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

function checkoutSvn($subdir, $user, $password, $revision) {
	global $config, $db;
	$subdir = trim($subdir);
	$subdir = trim($subdir, '/');
	$sTaskPath = '$ROOT_PATH/'.$subdir;
	if ($revision) {
		$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
		$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
		$ID = $stmt->fetchColumn();
		if ($ID) {
			echo(json_encode(['success' => $success, 'ltiUrl' => $config->ltiUrl.$ID, 'normalUrl' => $config->normalUrl.$ID]));
			return;
		}
	}
	$dir = mt_rand(100000, mt_getrandmax());
	svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME,             $user);
	svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD,             $password);
	svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true); // <--- Important for certificate issues!
	svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE,              true);
	svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE,                true);
	$sucess = true;
	$url = $config->svnBaseUrl.$subdir;
	try {
		svn_update(__DIR__.'/files/_common/');
		if ($revision) {
			$success = svn_checkout($url, __DIR__.'/files/checkouts/'.$dir, $revision);
		} else {
			$success = svn_checkout($url, __DIR__.'/files/checkouts/'.$dir);
			$revision = getLastRevision(__DIR__.'/files/checkouts/'.$dir);
		}
	} catch (Exception $e) {
		die(json_encode(['success' => false, 'error' => $e->getMessage()]));
	}
	if (!$success) {
		die(json_encode(['success' => false, 'error' => 'impossible de faire un checkout de '.$url.' (répertoire inexistant ou indentifiants invalides).']));
	}
	if (!file_exists(__DIR__.'/files/checkouts/'.$dir.'/index.html')) {
		die(json_encode(['success' => false, 'error' => 'le fichier index.html n\'existe pas !']));
	}
	$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
	$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
	$ID = $stmt->fetchColumn();
	if ($ID) {
		deleteRecDirectory(__DIR__.'/files/checkouts/'.$dir);
		echo(json_encode(['success' => $success, 'ltiUrl' => $config->ltiUrl.$ID, 'normalUrl' => $config->normalUrl.$ID]));
		return;
	}
	echo(json_encode(['success' => $success, 'url' => $config->baseUrl.'/files/checkouts/'.$dir.'/index.html', 'revision' => $revision, 'ID' => $dir]));
}

function saveLimits($taskId, $limits) {
	global $db;
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

function saveTask($metadata, $subdir, $revision) {
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
	$bUserTests = isset($metadata['hasUserTests']) ? $metadata['hasUserTests'] : 0;
	$stmt = $db->prepare('insert into tm_tasks (sTextId, sSupportedLangProg, sAuthor, bUserTests, sTaskPath, sRevision) values (:id, :langprog, :authors, :bUserTests, :sTaskPath, :revision);');
	$stmt->execute(['id' => $metadata['id'], 'langprog' => $sSupportedLangProg, 'authors' => $authors, 'bUserTests' => $bUserTests, 'sTaskPath' => $sTaskPath, 'revision' => $revision]);
	$stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
	$stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
	$taskId = $stmt->fetchColumn();
	if (!$taskId) {
		die(json_encode(['success' => false, 'impossible to find id for '.$metadata['id']]));
	}
	return [$taskId, false];
}

function saveStrings($taskId, $resources, $metadata) {
	global $db;
	$statement = null;
	$solution = null;
	$css = null;
	foreach ($resources['task'] as $i => $resource) {
		if ($resource['type'] == 'html') {
			$statement = $resource['content'];
		} elseif ($resource['type'] == 'css' && isset($resource['content'])) {
			$css = $resource['content'];
		}
	}
	foreach ($resources['solution'] as $i => $resource) {
		if ($resource['type'] == 'html') {
			$solution = $resource['content'];
			break;
		}
	}
	$stmt = $db->prepare('insert into tm_tasks_strings (idTask, sLanguage, sTitle, sStatement, sSolution, sCss) values (:idTask, :sLanguage, :sTitle, :sStatement, :sSolution, :sCss) on duplicate key update sTitle = values(sTitle), sStatement = values(sStatement), sSolution = values(sSolution), sCss = values(sCss);');
	$stmt->execute(['idTask' => $taskId, 'sLanguage' => $metadata['language'], 'sTitle' => $metadata['title'], 'sStatement' => $statement, 'sSolution' => $solution, 'sCss' => $css]);
}

function saveHints($taskId, $hintsResources, $metadata) {
	global $db;
	foreach ($hintsResources as $i => $resources) {
		foreach ($resources as $j => $resource) {
			if ($resource['type'] == 'html') {
				$hint = $resource['content'];
				break;
			}
		}
		if ($hint) {
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

function saveResources($metadata, $resources, $subdir, $revision) {
	global $config;
	$subdir = trim($subdir);
	$subdir = trim($subdir, '/');
	if (!isset($metadata['id']) || !isset($metadata['language'])) {
		die(json_encode(['success' => false, 'error' => 'missing id or language in metadata']));
	}
	$textId = $metadata['id'];
	// save task to get ID
	list($taskId, $alreadyImported) = saveTask($metadata, $subdir, $revision);
	if (!$alreadyImported) {
		// limits
		saveLimits($taskId, $metadata['limits']);
		// strings
		saveStrings($taskId, $resources, $metadata);
		// hints
		if (isset($resources['hints']) && count($resources['hints'])) {
			saveHints($taskId, $resources['hints'], $metadata);
		}
		// source code
		saveSourceCodes($taskId, $resources);
		saveSamples($taskId, $resources);
	}
	echo(json_encode(['success' => true, 'normalUrl' => $config->normalUrl.$taskId, 'ltiUrl' => $config->ltiUrl.$taskId]));
}

// why is there no such thing in the php library?
function deleteRecDirectory($dir) {
	if (!$dir || $dir == '/') return;
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

function deleteDirectory($ID) {
	$ID = intval($ID);
	if ($ID < 1) {
		die(json_encode(['success' => false, 'error' => 'invalid ID format (must be number)']));
	}
	deleteRecDirectory(__DIR__.'/files/checkouts/'.$ID);
	echo(json_encode(['success' => true]));
}


if ($_POST['action'] == 'checkoutSvn') {
	if (!isset($_POST['svnUrl'])) {
		die(json_encode(['success' => false, 'error' => 'missing svnUrl']));
	}
	$user = $_POST['svnUser'] ? $_POST['svnUser'] : $config->defaultSvnUser;
	$password = $_POST['svnPassword'] ? $_POST['svnPassword'] : $config->defaultSvnPassword;
	checkoutSvn($_POST['svnUrl'], $user, $password, $_POST['svnRev']);
} elseif ($_POST['action'] == 'saveResources') {
	if (!isset($_POST['metadata']) || !isset($_POST['resources']) || !isset($_POST['svnUrl']) || !isset($_POST['svnRev'])) {
		die(json_encode(['success' => false, 'error' => 'missing metada, resources, svnUrl or svnRev']));
	}
	saveResources($_POST['metadata'], $_POST['resources'], $_POST['svnUrl'], $_POST['svnRev']);
} elseif ($_POST['action'] == 'deletedirectory') {
	if (!isset($_POST['ID'])) {
		die(json_encode(['success' => false, 'error' => 'missing directory']));
	}
	deleteDirectory($_POST['ID']);
} else {
	echo(json_encode(['success' => false, 'error' => 'unknown action '.$_POST['action']]));	
}