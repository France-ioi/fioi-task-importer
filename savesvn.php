<?php

$workingDir = __DIR__;

require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'shared/taskEditor.php';

if(!$config->debug) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 1);
    error_reporting(0);
}

require_once 'libs/directories.inc.php';
require_once 'libs/git.inc.php';
require_once 'libs/resources.inc.php';
require_once 'libs/svn.inc.php';
require_once 'libs/urls.inc.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$request = json_decode(file_get_contents('php://input'), true);

if (!isset($request) || !isset($request['action'])) {
    die(json_encode(['success' => false, 'error' => 'missing action']));
}

if ($request['action'] == 'checkoutSvn' || $request['action'] == 'checkoutGit' || $request['action'] == 'updateCommon' || $request['action'] == 'updateLocalCommon') {
    if (!isset($request['svnUrl'])) {
        // TODO :: skip if action is updateCommon
        die(json_encode(['success' => false, 'error' => 'error_request']));
    }

    $svnRev = isset($request['svnRev']) ? $request['svnRev'] : '';

    $credentials = false;
    if(isset($request['token'])) {
        $credentials = userCredentials($request['token']);
    }

    if($request['action'] == 'checkoutSvn') {
        $user = $request['username'] ? $request['username'] : $config->defaultSvnUser;
        $password = $request['password'] ? $request['password'] : $config->defaultSvnPassword;
        if($credentials !== false) {
            $user = $credentials['username'];
            $password = $credentials['password'];
        }
        checkoutSvn($request['svnUrl'], $user, $password, $svnRev, isset($request['recursive']) && $request['recursive'], isset($request['noimport']) && $request['noimport'], isset($request['rewritecommon']) && $request['rewritecommon']);
    } elseif($request['action'] == 'checkoutGit') {
        $user = isset($request['gitUsername']) ? $request['gitUsername'] : null;
        $password = isset($request['gitPassword']) ? $request['gitPassword'] : null;
        $gitPath = isset($request['gitPath']) ? $request['gitPath'] : '';
        if($credentials !== false) {
            $user = $credentials['username'];
            $password = $credentials['password'];
        }
        checkoutGit($request['gitUrl'], $gitPath, $user, $password, isset($request['recursive']) && $request['recursive'], isset($request['noimport']) && $request['noimport'], isset($request['rewritecommon']) && $request['rewritecommon']);
    } elseif($request['action'] == 'updateCommon') {
        echo json_encode(updateCommon($user, $password));
    } elseif($request['action'] == 'updateLocalCommon') {
        $user = $request['username'] ? $request['username'] : $config->defaultSvnUser;
        $password = $request['password'] ? $request['password'] : $config->defaultSvnPassword;
        if($credentials !== false) {
            $user = $credentials['username'];
            $password = $credentials['password'];
        }
        echo json_encode(updateLocalCommon($request['svnUrl'], $user, $password));
    }
} elseif ($request['action'] == 'saveResources') {
    if (!isset($request['data']) || !isset($request['svnUrl']) || !isset($request['taskPath'])) {
        die(json_encode(['success' => false, 'error' => 'error_request']));
    }
    saveResources($request['data'], $request['taskPath'], $request['svnUrl'], $request['svnRev'], $request['dirPath']);
} elseif ($request['action'] == 'deletedirectory') {
    if (!isset($request['ID'])) {
        die(json_encode(['success' => false, 'error' => 'error_request']));
    }
    echo(json_encode(deleteDirectory($request['ID'])));
} else {
    echo(json_encode(['success' => false, 'error' => 'error_action']));
}
