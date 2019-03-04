<?php

// Functions for SVN handling

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../shared/connect.php';
require_once __DIR__.'/svn.inc.php';

function checkoutGit($repo, $subpath, $recursive, $noimport, $rewriteCommon) {
    global $config, $db, $workingDir;

    if($noimport || $recursive && $noimport) {
        // TODO :: adapt to recursive
        // not supported (yet)
        echo(json_encode([
            'success' => false,
            'error' => 'error_recno_unsupported'
            ]));
        return;
    }

    $baseTargetDir = $baseTargetDir = mt_rand(100000, mt_getrandmax());
    $subDir = $baseTargetDir . '/' . $subpath;

    // Create target checkout directory
    //mkdir($workingDir.'/files/repositories/'.$baseTargetDir, 0777, true);

    $success = true;
    try {
        exec("git clone " . $repo . " " . $workingDir.'/files/checkouts/'.$baseTargetDir);
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }

    $tasks = array();
    $taskDirs = listTaskDirs($subDir, $recursive);
    foreach($taskDirs as $taskDir) {
        $newTaskData = processDir($taskDir, $repo . '/' . $subpath, true);
        if(count($newTaskData['files']) > 0) {
            $tasks[] = $newTaskData;
        }
    }
    echo(json_encode(['success' => $success, 'tasks' => $tasks]));
}
