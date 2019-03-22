<?php

// Functions for SVN handling

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../shared/connect.php';
require_once __DIR__.'/../shared/QuizzeServer.php';

$quizzeServer = null;
if($config->bebrasServerModules->quizze_url) {
    $quizzeServer = new QuizzeServer([
        'url' => $config->bebrasServerModules->quizze_url
    ]);
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

function setSvnParameters($user, $password) {
    svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME,             $user);
    svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD,             $password);
    svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true); // <--- Important for certificate issues!
    svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE,              true);
    svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE,                true);
}


function listTaskDirs($dir, $recursive) {
    global $workingDir;

    $filenames = scandir($workingDir.'/files/checkouts/'.$dir.'/');
    foreach($filenames as $filename) {
        if(preg_match('/^index.*\.html/', $filename) === 1 && file_exists($workingDir.'/files/checkouts/'.$dir.'/'.$filename)) {
            return array($dir);
        }
    }

    // No task file has been found
    if(!$recursive) {
        die(json_encode(['success' => false, 'error' => 'error_noindex']));
    }

    $taskDirs = array();
    foreach(scandir($workingDir.'/files/checkouts/'.$dir) as $elem) {
        $elemPath = $dir.'/'.$elem;
        if(is_dir($workingDir.'/files/checkouts/'.$elemPath) && $elem != '.' && $elem != '..') {
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

function checkCommon($path, $depth, $rewriteCommon) {
    // Checks the _common paths; returns false if everything is fine
    $handle = fopen($path, 'r');
    $wrong = false;
    $targetPath = str_repeat('../', $depth) . '_common';
    $fileBuffer = '';
    while(!feof($handle)) {
        $line = fgets($handle);
        if(preg_match('/= *[\'"]([^=]+)_common/', $line, $matches) && !is_dir(dirname($path) . '/' . $matches[1] . '/_common')) {
            if($rewriteCommon) {
                $line = preg_replace('/(= *[\'"])[^=]+_common/', '\1' . $targetPath, $line);
                $wrong = true;
            } else {
                // At least one path is wrong
                return true;
            }
        }
        if($rewriteCommon) { $fileBuffer .= $line; }
    }
    fclose($handle);
    if($rewriteCommon && $wrong) {
        $handle = fopen($path, 'w');
        fwrite($handle, $fileBuffer);
        fclose($handle);
    }
    return $wrong;
}

function processDir($taskDir, $baseSvnFirst, $rewriteCommon) {
    global $config, $quizzeServer, $workingDir;

    // Remove first component of the path
    $taskDirExpl = explode('/', $taskDir);
    $taskDirCompl = implode('/', array_slice($taskDirExpl, 1));
    $taskSvnDir = $baseSvnFirst . '/' . $taskDirCompl;

    $indexList = [];
    $taskDirMoved = false;

    $filenames = scandir($workingDir.'/files/checkouts/'.$taskDir.'/');

    $depth = count(array_filter($taskDirExpl, function($path) { return $path != '.'; })) - count(array_filter($taskDirExpl, function($path) { return $path == '..'; }));

    $urlArgs = [];
    $hasLti = false;
    foreach($filenames as $filename) {
        if(preg_match('/index.*\.html/', $filename) !== 1) {
            continue;
        }
        if(!file_exists($workingDir.'/files/checkouts/'.$taskDir.'/'.$filename)) {
            continue;
        }
        if($isStatic = checkStatic($workingDir.'/files/checkouts/'.$taskDir.'/'.$filename)) {
            if(!$taskDirMoved) {
                // Move task to a static location
                $targetDir = md5($taskSvnDir). '/' . $taskDirCompl;
                $targetFsDir = $workingDir.'/files/checkouts/'.$targetDir;
                mkdir($targetFsDir, 0777, true);
                deleteRecDirectory($targetFsDir);
                rename($workingDir.'/files/checkouts/'.$taskDir, $targetFsDir);
                $taskDir = $targetDir;
                $taskDirMoved = true;
            }
            $graderFilePath = $targetFsDir.'/grader_data.js';
            if($quizzeServer && file_exists($graderFilePath)) {
                // TODO :: fetch the ID from the task JSON
                $quizzeId = 'quizze-' . md5($taskSvnDir);
                $quizzeServer->write($quizzeId, $graderFilePath);
                unlink($graderFilePath);
                $urlArgs['taskID'] = $quizzeId;
                $hasLti = true;
            }
        }
        $newIndex = [
            'filename' => $filename,
            'isStatic' => $isStatic,
            'depth' => $depth
            ];
        $newIndex[$rewriteCommon ? 'commonRewritten' : 'warnPaths'] = checkCommon($workingDir.'/files/checkouts/'.$taskDir.'/'.$filename, $depth, $rewriteCommon);
        $indexList[] = $newIndex;
    }
    $taskData = [
        'dirPath' => $taskDir,
        'ID' => $taskDir,
        'svnUrl' => $taskSvnDir,
        'baseUrl' => $config->baseUrl.'/files/checkouts/'.$taskDir.'/',
        'staticUrl' => $config->staticUrl.$taskDir.'/',
        'urlArgs' => $urlArgs,
        'files' => $indexList,
        'hasLti' => $hasLti
        ];

    if(file_exists($workingDir.'/files/checkouts/'.$taskDir.'/ref_lang.txt')) {
        $taskData['refLang'] = trim(file_get_contents($workingDir.'/files/checkouts/'.$taskDir.'/ref_lang.txt'));
    }

    return $taskData;
}

function updateCommon($user, $password) {
    global $workingDir;

    setSvnParameters($user, $password);
    try {
        svn_update($workingDir.'/files/checkouts/_common/');
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
    return ['success' => true];
}

function updateLocalCommon($subdir, $user, $password) {
    global $config, $workingDir;

    $baseSvnDir = trim($subdir);
    $baseSvnDir = trim($baseSvnDir, '/');

    // Create target checkout directory
    $baseSvnExpl = explode('/', $baseSvnDir);
    $baseSvnFirst = array_shift($baseSvnExpl);
    setSvnParameters($user, $password);

    // Get _local_common
    $localCommonSvn = $baseSvnFirst.'/_local_common';
    $localCommonDir = $workingDir.'/files/checkouts/local/'.$baseSvnFirst;
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

    // Always a success
    return ['success' => true, 'localCommon' => $localCommonExists];
}

function checkoutSvn($subdir, $user, $password, $userRevision, $recursive, $noimport, $rewriteCommon) {
    global $config, $db, $workingDir;

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
    mkdir($workingDir.'/files/checkouts/'.$baseTargetDir, 0777, true);

    setSvnParameters($user, $password);
    $success = true;
    $url = $config->svnBaseUrl.$baseSvnDir;
    try {
        if ($userRevision) {
            $success = svn_checkout($url, $workingDir.'/files/checkouts/'.$baseTargetDir, $userRevision);
            $revision = $userRevision;
        } else {
            $success = svn_checkout($url, $workingDir.'/files/checkouts/'.$baseTargetDir);
            $revision = getLastRevision($workingDir.'/files/checkouts/'.$baseTargetDir);
        }
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
    if (!$success) {
        die(json_encode(['success' => false, 'error' => 'error_checkout']));
    }

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
        $newTaskData = processDir($taskDir, $baseSvnFirst, $rewriteCommon);
        if(count($newTaskData['files']) > 0) {
            $tasks[] = $newTaskData;
        }
    }
    echo(json_encode(['success' => $success, 'tasks' => $tasks, 'revision' => $revision]));
}