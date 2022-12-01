<?php

require_once __DIR__.'/funcs.inc.php';
require_once __DIR__.'/editorApi.inc.php';


function getGitFolder($repo) {
    global $workingDir;
    $repoDir = trim($repo);
    $repoDir = trim($repoDir, '/');
    $repoDir = preg_replace('/[^a-zA-Z0-9]/', '_', $repoDir);
    $repoDir = pathJoin($workingDir, 'files/repositories/', $repoDir);
    return $repoDir;
}


function updateGit($repo, $username, $password) {
    global $config, $db, $workingDir;

    $repoDir = getGitFolder($repo);

    $repoParse = parse_url($repo);
    if($username) { $repoParse['user'] = $username; }
    if($password) { $repoParse['pass'] = $password; }
    $repoUrl = unparse_url($repoParse);

    // If it exists, update
    try {
        if(is_dir($repoDir)) {
            exec("cd " . $repoDir . " && git branch editor");
            exec("cd " . $repoDir . " && git checkout editor");
        } else {
            // Otherwise, clone
            exec("git clone " . $repoUrl . " " . $repoDir, $output, $retval);
            if($retval != 0) { return ['success' => false, 'error' => 'Failed to clone repository']; }
            exec("cd " . $repoDir . " && git branch editor");
            exec("cd " . $repoDir . " && git checkout editor");            
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $historyInfo = getHistory($repo, '');
    if($historyInfo['masterAdditional'] > 0 && $historyInfo['editorAdditional'] == 0) {
        exec("cd " . $repoDir . " && git reset --hard master");
    }

    return ['success' => true];
}

function prepareEdition($repo, $subdir) {
    $sessionId = uniqid();
    $sessionDir = getSessionDir($sessionId);

    $repoDir = getGitFolder($repo);
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');
    $subdir = pathJoin($repoDir, $subdir) . '/';
    @mkdir($sessionDir, 0777, true);
    dirCopy($subdir, $sessionDir);

    $historyInfo = getHistory($repo, $subdir);

    return [
        'success' => true,
        'session' => $sessionId,
        'token' => 'testtoken',
        'masterSynced' => $historyInfo['masterAdditional'] == 0,
        'editorSynced' => $historyInfo['editorAdditional'] == 0
    ];

}


function commitEdition($repo, $subdir, $sessionId, $username, $password) {
    if(!$sessionId) {
        return ['success' => false, 'error' => 'No session ID provided'];
    }
    if(!$username) {
        return ['success' => false, 'error' => 'No username provided'];
    }
    $sessionDir = getSessionDir($sessionId);

    $repoDir = getGitFolder($repo);
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');
    $subdir = pathJoin($repoDir, $subdir) . '/';
    dirCopy($sessionDir, $subdir);

    $repoParse = parse_url($repo);
    if($username) { $repoParse['user'] = $username; }
    if($password) { $repoParse['pass'] = $password; }
    $repoUrl = unparse_url($repoParse);

    exec("cd " . $repoDir . " && git add -A");
    exec("cd " . $repoDir . " && git commit --author=\"Editor <task-editor@france-ioi.org>\" -m \"Editor commit for $username\"");
    exec("cd " . $repoDir . " && git push " . $repoUrl, $output, $retval);
    if($retval != 0) { return ['success' => false, 'error' => 'Failed to push, are the username/password correct?']; }

    return ['success' => true];
}


function closeSession($sessionId) {
    $sessionDir = getSessionDir($sessionId);
    deleteRecDirectory($sessionDir);
    echo(json_encode(['success' => true]));
}


function getBranchHistory($repo, $subdir, $branch) {
    $output = [];
    $repoDir = getGitFolder($repo);
    exec("cd " . $repoDir . " && git log --pretty=format:'%h %at %s (by %an)' " . $branch . " -- " . $subdir, $output);
    $logs = [];
    foreach($output as $outline) {
        $parts = explode(' ', $outline, 3);
        $logs[] = [
            'hash' => $parts[0],
            'date' => $parts[1],
            'message' => $parts[2],
        ];
    }
    return $logs;
}

function getHistory($repo, $subdir) {
    $historyMaster = getBranchHistory($repo, $subdir, 'master');
    $historyEditor = getBranchHistory($repo, $subdir, 'editor');
    $editorAdditional = 0;
    foreach($historyEditor as &$editorLog) {
        $found = false;
        foreach($historyMaster as $masterLog) {
            if($masterLog['hash'] == $editorLog['hash']) {
                $found = true;
                break;
            }
        }
        if($found) {
            $editorLog['master'] = true;
            break;
        } else {
            $editorAdditional += 1;
        }
    }
    unset($editorLog);
    $masterAdditional = 0;
    foreach($historyMaster as $masterLog) {
        $found = false;
        foreach($historyEditor as $editorLog) {
            if($masterLog['hash'] == $editorLog['hash']) {
                $found = true;
                break;
            }
        }
        if($found) {
            break;
        } else {
            $masterAdditional++;
        }
    }
    return ['success' => true, 'history' => $historyEditor, 'editorAdditional' => $editorAdditional, 'masterAdditional' => $masterAdditional];
}

function checkoutHashEdition($repo, $hash) {
    $repoDir = getGitFolder($repo);
    exec("cd " . $repoDir . " && git checkout " . $hash);
    return ['success' => true];
}