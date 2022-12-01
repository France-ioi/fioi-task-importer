<?php

// TODO :: cleanup sessions

require_once __DIR__.'/funcs.inc.php';

function checkToken($token, $sessionId) {
    // TODO
    return ($token == 'testtoken');
}

function getSessionDir($sessionId) {
    $workingDir = realpath(__DIR__.'/../');
    return pathJoin($workingDir, "files/sessions", $sessionId);
}

function getSessionFilePath($sessionId, $path) {
    $sessionDir = getSessionDir($sessionId);
    $file = realpath("$sessionDir/$path");
    if (substr($file, 0, strlen($sessionDir)) != $sessionDir) {
        header("HTTP/1.0 403 Forbidden");
        die();
    }
    return $file;
}

function recursiveDirList($path, $prefix) {
    $list = [];
    foreach(scandir($path) as $file) {
        if($file == "." || $file == "..") {
            continue;
        }
        $subpath = "$path/$file";
        if(is_dir($subpath)) {
            $list = array_merge($list, recursiveDirList($subpath, "$prefix/$file"));
        } else {
            $list[] = ltrim("$prefix/$file", '/');
        }
    }
    return $list;

}

function getList($sessionId) {
    return recursiveDirList(getSessionDir($sessionId), '');
}

function getFile($sessionId, $path) {
    $file = getSessionFilePath($sessionId, $path);
    if(!file_exists($file)) {
        header("HTTP/1.0 404 Not Found");
        die();
    }
    header('Content-Type: ' . mime_content_type($file));
    echo file_get_contents($file);
    die();
}

function putFile($sessionId, $path, $start, $truncate) {
    $file = getSessionFilePath($sessionId, $path);

    $dir = dirname($file);
    if(!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }

    $fp = fopen($file, $truncate ? 'w' : 'r+');
    fseek($fp, $start);
    fwrite($fp, file_get_contents('php://input'));
    fclose($fp);
    die();
}

function deleteFile($sessionId, $path) {
    $file = getSessionFilePath($sessionId, $path);
    if(!file_exists($file)) {
        header("HTTP/1.1 404 Not Found");
        die();
    }
    unlink($file);
    die();
}

function handleEditorApi() {
    // if(!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    //     header("HTTP/1.1 401 Unauthorized");
    //     header('WWW-Authenticate: Bearer');
    //     die();
    // }

    // $authorization = $_SERVER['HTTP_AUTHORIZATION'];
    // if(substr($authorization, 0, 7) != 'Bearer ') {
    //     header('HTTP/1.1 401 Unauthorized');
    //     header('WWW-Authenticate: Bearer');
    //     die();
    // }
    // $token = substr($authorization, 7);
    $token = 'testtoken';

    $uriSplit = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), 5);
    if($uriSplit[1] != 'edition' || count($uriSplit) < 4) {
        header('HTTP/1.1 404 Not Found');
        die();
    }
    $sessionId = $uriSplit[2];

    if(!checkToken($token, $sessionId)) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Bearer');
        die();
    }

    $action = $uriSplit[3];

    if($action == 'list' && count($uriSplit) == 4) {
        $list = getList($sessionId);
        header('Content-Type: application/json');
        echo json_encode($list);
        die();
    }
    
    if($action == 'file' && count($uriSplit) == 5) {
        $path = $uriSplit[4];
        if($_SERVER['REQUEST_METHOD'] == 'GET') {
            $fileContent = getFile($sessionId, $path);
        } else if($_SERVER['REQUEST_METHOD'] == 'PUT') {
            $start = 0;
            $truncate = 0;
            if(isset($_GET['start'])) {
                $start = $_GET['start'];
            }
            if(isset($_GET['truncate'])) {
                $truncate = $_GET['truncate'];
            }
            if($truncate == 1 && $start != 0) {
                header('HTTP/1.1 400 Bad Request');
                die();
            }
            putFile($sessionId, $path, $start, $truncate);
            die();
        } else if($_SERVER['REQUEST_METHOD'] == 'DELETE') {
            deleteFile($sessionId, $path);
            die();
        } else if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
            die();
        } else {
            header('HTTP/1.1 405 Method Not Allowed');
            die();
        }
    }

    header('HTTP/1.1 404 Not Found');
    die();
}