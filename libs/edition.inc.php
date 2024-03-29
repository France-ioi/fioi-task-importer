<?php

require_once __DIR__.'/funcs.inc.php';
require_once __DIR__.'/editorApi.inc.php';


function getGitFolder($repo, $branch = null) {
    global $workingDir;
    $repoDir = trim($repo);
    $repoDir = trim($repoDir, '/');
    $repoDir = preg_replace('/[^a-zA-Z0-9]/', '_', $repoDir);
    if($branch !== null) {
        $repoDir .= '_' . $branch;
    }
    $repoDir = pathJoin($workingDir, 'files/repositories/', $repoDir);
    return $repoDir;
}

function getGitBranchForFolder($repo, $repoDir) {
    return 'editor-' . substr(md5($repoDir), 0, 8);
}

function checkRepositoryAllowed($repo) {
    global $config;
    if(!$config->git->allowedRepositories) {
        return true;
    }
    return in_array($repo, $config->git->allowedRepositories);
}


function setGitUser($repo, $username, $password) {
    $repoParse = parse_url($repo);
    if($username) { $repoParse['user'] = $username; }
    if($password) { $repoParse['pass'] = $password; }
    return unparse_url($repoParse);
}

function setGitBackendUser($repo, $username = null, $password = null) {
    global $config;
    if(isGitlab($repo)) {
        return setGitUser($repo, $config->git->gitlabUser ? $config->git->gitlabUser : $username, $config->git->gitlabPassword ? $config->git->gitlabPassword : $password);
    } else {
        return setGitUser($repo, $config->git->githubUser ? $config->git->githubUser : $username, $config->git->githubPassword ? $config->git->githubPassword : $password);
    }
}

function getGitMainBranch($repo) {
    $repoDir = getGitFolder($repo);
    $output = [];
    exec("cd " . $repoDir . " && git branch", $output);
    foreach($output as $line) {
        if(trim(substr($line, 2)) == 'master') {
            return 'master';
        }
        if(trim(substr($line, 2)) == 'main') {
            return 'main';
        }
    }
    // just return editor to avoid crashing
    return 'editor';
}


function updateGit($repo, $subdir, $username, $password) {
    global $config, $db, $workingDir;

    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $repoDir = getGitFolder($repo);
    $repoUrl = setGitBackendUser($repo, $username, $password);
    $repoBranch = getGitBranchForFolder($repo, $subdir);

    // If it exists, update
    try {
        if(is_dir($repoDir)) {
            exec("cd " . $repoDir . " && git checkout " . getGitMainBranch($repo));
            exec("cd " . $repoDir . " && git pull");
            exec("cd " . $repoDir . " && git branch " . $repoBranch);
            exec("cd " . $repoDir . " && git checkout " . $repoBranch);
        } else {
            // Otherwise, clone
            exec("git clone " . $repoUrl . " " . $repoDir, $output, $retval);
            if($retval != 0) { return ['success' => false, 'error' => 'Failed to clone repository']; }
            exec("cd " . $repoDir . " && git branch " . $repoBranch);
            exec("cd " . $repoDir . " && git checkout " . $repoBranch);            
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }

    $masterBranch = getGitMainBranch($repo);
    $historyInfo = getHistory($repo, '');
    if($historyInfo['masterAdditional'] > 0 && $historyInfo['editorAdditional'] == 0) {
        exec("cd " . $repoDir . " && git reset --hard $masterBranch");
    }

    return ['success' => true];
}

function prepareEdition($repo, $subdir) {
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $sessionId = uniqid();
    $sessionDir = getSessionDir($sessionId);

    $repoDir = getGitFolder($repo);
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');
    $localSubdir = pathJoin($repoDir, $subdir) . '/';
    @mkdir($sessionDir, 0777, true);
    dirCopy($localSubdir, $sessionDir);

    // Copy variables.json
    if(file_exists(pathJoin($repoDir, 'variables.json'))) {
        copy(pathJoin($repoDir, 'variables.json'), pathJoin($sessionDir, 'variables.json'));
    }

    $historyInfo = getHistory($repo, $subdir);

    return [
        'success' => true,
        'session' => $sessionId,
        'token' => 'testtoken',
        'masterSynced' => $historyInfo['masterAdditional'] == 0,
        'editorSynced' => $historyInfo['editorAdditional'] == 0,
        'masterBranch' => getGitMainBranch($repo),
        'taskEditor' => file_exists(pathJoin($localSubdir, 'task_editor.json'))
    ];

}


function commitEdition($repo, $subdir, $sessionId, $commitMsg, $username, $password) {
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }
    if(!$sessionId) {
        return ['success' => false, 'error' => 'No session ID provided'];
    }
    $sessionDir = getSessionDir($sessionId);

    $repoDir = getGitFolder($repo);
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');
    $localSubdir = pathJoin($repoDir, $subdir) . '/';

    exec("cd " . $repoDir . " && git checkout " . getGitBranchForFolder($repo, $subdir));

    // Remove variables.json
    if(file_exists(pathJoin($sessionDir, 'variables.json'))) {
        unlink(pathJoin($sessionDir, 'variables.json'));
    }
    dirCopy($sessionDir, $localSubdir);

    $repoUrl = setGitBackendUser($repo);

    exec("cd " . $repoDir . " && git add -A");
    exec("cd " . $repoDir . " && git commit --author=\"Editor <task-editor@france-ioi.org>\" -m " . escapeshellarg($commitMsg));
    exec("cd " . $repoDir . " && git push " . $repoUrl, $output, $retval);
    if($retval != 0) { return ['success' => false, 'error' => 'Failed to push, are the username/password correct?', 'output' => $output]; }

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
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $historyMaster = getBranchHistory($repo, $subdir, getGitMainBranch($repo));
    $historyEditor = getBranchHistory($repo, $subdir, getGitBranchForFolder($repo, $subdir));
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
    foreach($historyMaster as &$masterLog) {
        $found = false;
        foreach($historyEditor as $editorLog) {
            if($masterLog['hash'] == $editorLog['hash']) {
                $masterLog['master'] = true;
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
    return [
        'success' => true,
        'history' => $historyEditor,
        'historyMaster' => $historyMaster,
        'editorAdditional' => $editorAdditional,
        'masterAdditional' => $masterAdditional
    ];
}

function checkoutHashEdition($repo, $hash) {
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $repoDir = getGitFolder($repo);
    exec("cd " . $repoDir . " && git checkout " . $hash);
    return ['success' => true];
}

function getLastCommits($repo, $subdir, $username, $password) {
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $repoDir = getGitFolder($repo);

    $repoUrl = setGitBackendUser($repo, $username, $password);

    $masterBranch = getGitMainBranch($repo);

    exec("cd " . $repoDir . " && git checkout $masterBranch");
    exec("cd " . $repoDir . " && git pull");
    exec("cd " . $repoDir . " && git checkout " . getGitBranchForFolder($repo, $subdir));
    $output = [];
    exec("cd " . $repoDir . " && git log --pretty=format:'%H' -n 1 $masterBranch -- " . $subdir, $output);
    $master = $output[0];
    exec("cd " . $repoDir . " && git log --pretty=format:'%H' -n 1 " . getGitBranchForFolder($repo, $subdir) . " -- " . $subdir, $output);
    $editor = $output[0];
    return ['success' => !!($master && $editor), 'master' => $master, 'editor' => $editor];
}

function isGitlab($repo) {
    return strpos($repo, 'gitlab') !== false;
}

function publishEdition($repo, $subdir, $type, $title, $body, $username, $password) {
    global $config;

    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $repoDir = getGitFolder($repo);
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');
    
    $masterBranch = getGitMainBranch($repo);

    exec("cd " . $repoDir . " && git checkout $masterBranch");
    exec("cd " . $repoDir . " && git pull");
    exec("cd " . $repoDir . " && git checkout " . getGitBranchForFolder($repo, $subdir));

    if($type == 'merge') {
        exec("cd " . $repoDir . " && git merge $masterBranch");
        $repoUrl = setGitUser($repo, $username, $password);
        exec("cd " . $repoDir . " && git push " . $repoUrl, $output, $retval);

        return ['success' => true, 'merge' => true];
    }

    $branchId = uniqid();
    $branchId = 'publish-' . $branchId;

    exec("cd " . $repoDir . " && git branch $branchId");
    exec("cd " . $repoDir . " && git checkout $branchId");

    if($type == 'prod') {
        // Push directly to production
        exec("cd " . $repoDir . " && git merge $masterBranch", $output, $retval);
        if($retval != 0) { return ['success' => false, 'error' => 'Unable to merge.']; }
        exec("cd " . $repoDir . " && git checkout $masterBranch");
        exec("cd " . $repoDir . " && git merge $branchId");

        $repoUrl = setGitUser($repo, $username, $password);

        exec("cd " . $repoDir . " && git push " . $repoUrl, $output, $retval);
        if($retval != 0) { return ['success' => false, 'error' => 'Failed to push, are the username/password correct?']; }

        return ['success' => true, 'prod' => true];
    } else {
        $repoParse = parse_url($repo);
        $repoUrl = setGitBackendUser($repo, $username, $password);

        exec("cd " . $repoDir . " && git push " . $repoUrl, $output, $retval);

        if($type == 'mpr') {
            // Manual PR
            return ['success' => true, 'branch' => $branchId];
        }

        if(isGitlab($repo)) {
            $data = array(
                'title' => $title,
                'description' => $body,
                'source_branch' => $branchId,
                'target_branch' => $masterBranch
            );
            $data_string = json_encode($data);
            $repoId = substr($repoParse['path'], 1);
            $repoId = str_replace('/', '%2F', $repoId);
            $pw = $config->git->gitlabPassword ? $config->git->gitlabPassword : $password;
            $ch = curl_init('https://gitlab.com/api/v4/projects/' . $repoId . '/merge_requests?private_token=' . $pw);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0 PHP/script');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
            );
            $result = curl_exec($ch);
            // if curl failed
            if($result === false) {
                return ['success' => false, 'error' => curl_error($ch)];
            }
            if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 201) {
                return ['success' => false, 'error' => $result];
            }
            try {
                $result = json_decode($result, true);
            } catch(Exception $e) {
                return ['success' => false, 'error' => 'Failed to parse response from gitlab'];
            }

            return ['success' => true, 'prUrl' => $result['web_url']];
        } else {
            // Automatic PR
            $data = array(
                'title' => $title,
                'body' => $body,
                'head' => $branchId,
                'base' => $masterBranch
            );
            $data_string = json_encode($data);
            $ch = curl_init('https://api.github.com/repos' . $repoParse['path'] . '/pulls');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.0 PHP/script');
            $pw = $config->git->githubPassword ? $config->git->githubPassword : $password;
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string),
                'Authorization: Bearer ' . $pw
            ));
            $result = curl_exec($ch);
            // if curl failed
            if($result === false) {
                return ['success' => false, 'error' => curl_error($ch)];
            }
            if(curl_getinfo($ch, CURLINFO_HTTP_CODE) != 201) {
                return ['success' => false, 'error' => $result, 'path' => 'https://api.github.com/repos' . $repoParse['path'] . '/pulls'];
            }
            try {
                $result = json_decode($result, true);
            } catch(Exception $e) {
                return ['success' => false, 'error' => 'Failed to parse response from github'];
            }

            return ['success' => true, 'prUrl' => $result['html_url']];
        }
    }
}


function diffEdition($repo, $subdir, $hash, $target) {
    if(!checkRepositoryAllowed($repo)) {
        return ['success' => false, 'error' => 'Repository not allowed'];
    }

    $repoDir = getGitFolder($repo);
    $masterBranch = getGitMainBranch($repo);

    exec("cd " . $repoDir . " && git checkout $masterBranch");
    exec("cd " . $repoDir . " && git pull");
    exec("cd " . $repoDir . " && git checkout " . getGitBranchForFolder($repo, $subdir));
   
    if($target == 'master') {
        $target = $masterBranch;
    } elseif($target == 'editor') {
        $target = getGitBranchForFolder($repo, $subdir);
    }

    $output = [];
    exec("cd " . $repoDir . " && git diff $hash $target -- " . $subdir, $output);
    return ['success' => true, 'diff' => implode("\n", $output)];
}