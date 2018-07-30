<?php

// Functions for local directories handling

function deleteRecDirectory($dir) {
    // TODO :: Fix the directory specification and logic
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
    return ['success' => true];
}

