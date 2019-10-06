<?php

// Functions for local directories handling

function deleteRecDirectory($dir) {
    // TODO :: Fix the directory specification and logic
    global $workingDir;

    if (!$dir || substr($dir, 0, strlen($workingDir.'/files/checkouts')) != $workingDir.'/files/checkouts') return;
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

function zipAdd($zip, $curPath, $curPrefix) {
    // Recursive part of zipDirectory
    foreach(scandir($curPath) as $elem) {
        if(!is_dir($curPath . $elem)) {
            $zip->addFile($curPath . $elem, $curPrefix . $elem);
        } elseif($elem != '.' && $elem != '..' && $elem != '.svn') {
            $zip->addEmptyDir($curPrefix . $elem);
            zipAdd($zip, $curPath . $elem . '/', $curPrefix . $elem . '/');
        }
    }
}

function zipDirectory($zipPath, $dirPath, $prefix) {
    // Make a zip of $dirPath in $zipPath, replacing setting $prefix as the base folder
    $zip = new ZipArchive();
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    zipAdd($zip, $dirPath, $prefix);
    $zip->close();
}

function deleteDirectory($path) {
    global $workingDir;

    $firstDir = explode('/', $path);
    $firstDir = $firstDir[0];
    $ID = intval($firstDir);
    if ($ID < 1) {
        die(json_encode(['success' => false, 'error' => 'error_request']));
    }
    //deleteRecDirectory($workingDir.'/files/checkouts/'.$ID);
    return ['success' => true];
}

