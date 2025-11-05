<?php

// Functions for saving resources for a TaskPlatform task

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../shared/connect.php';

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

function saveTask($metadata, $sTaskPath, $subdir, $revision, $resources) {
    global $db;
    $authors = (isset($metadata['authors']) && count($metadata['authors'])) ? join(',', $metadata['authors']) : '';
    $sSupportedLangProg = (isset($metadata['supportedLanguages']) && count($metadata['supportedLanguages'])) ? join(',', $metadata['supportedLanguages']) : '*';
    $sEvalTags = (isset($metadata['evaluationTags']) && count($metadata['evaluationTags'])) ? join(',', $metadata['evaluationTags']) : '';
    $bUserTests = isset($metadata['hasUserTests']) && $metadata['hasUserTests'] ? 1 : 0;
    $bUseLatex = isset($metadata['useLatex']) && $metadata['useLatex'] ? 1 : 0;
    $sEvalResultOutputScript = isset($metadata['evalOutputScript']) ? $metadata['evalOutputScript'] : null;
    $sScriptAnimation = '';
    foreach ($resources['task'] as $i => $resource) {
        if ($resource['type'] == 'javascript' && isset($resource['id']) && $resource['id'] == 'animation' && isset($resource['content'])) {
            $sScriptAnimation = $resource['content'];
            break;
        }
    }
    $bHasSubtasks = isset($metadata['subtasks']);
    $stmt = $db->prepare("
        INSERT INTO tm_tasks
        (sTextId, sSupportedLangProg, sEvalTags, sAuthor, bUserTests, bUseLatex, sTaskPath, sRevision, sEvalResultOutputScript, sScriptAnimation, bHasSubtasks)
        VALUES (:id, :langprog, :evaltags, :authors, :bUserTests, :bUseLatex, :sTaskPath, :revision, :sEvalResultOutputScript, :sScriptAnimation, :bHasSubtasks)
        ON DUPLICATE KEY UPDATE
        sSupportedLangProg = VALUES(sSupportedLangProg), sAuthor = VALUES(sAuthor), bUserTests = VALUES(bUserTests), bUseLatex = VALUES(bUseLatex), sTaskPath = VALUES(sTaskPath), sRevision = VALUES(sRevision), sEvalResultOutputScript = VALUES(sEvalResultOutputScript), sScriptAnimation = VALUES(sScriptAnimation), bHasSubtasks = VALUES(bHasSubtasks);
        ");
    $stmt->execute([
        'id' => $metadata['id'],
        'langprog' => $sSupportedLangProg,
        'evaltags' => $sEvalTags,
        'authors' => $authors,
        'bUserTests' => $bUserTests,
        'bUseLatex' => $bUseLatex,
        'sTaskPath' => $sTaskPath,
        'revision' => $revision,
        'sEvalResultOutputScript' => $sEvalResultOutputScript,
        'sScriptAnimation' => $sScriptAnimation,
        'bHasSubtasks' => $bHasSubtasks ? 1 : 0,
        ]);
    $stmt = $db->prepare('select ID from tm_tasks where sTaskPath = :sTaskPath and sRevision = :revision');
    $stmt->execute(['sTaskPath' => $sTaskPath, 'revision' => $revision]);
    $taskId = $stmt->fetchColumn();
    if (!$taskId) {
        die(json_encode(['success' => false, 'impossible to find id for '.$metadata['id']]));
    }
    return $taskId;
}

function saveStrings($taskId, $resources, $metadata, $dirPath) {
    global $config, $db, $workingDir;
    $statement = null;
    $solution = null;
    $css = null;
    $imagesRes = [];
    $files = array();

    $baseSvnFirst = explode('/', $dirPath)[0];
    $localCommonDir = $workingDir.'/files/checkouts/local/'.$baseSvnFirst;
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
        if ($resource['type'] == 'html' && isset($resource['content'])) {
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
    if(!isset($metadata['title'])) {
        $metadata['title'] = isset($resources['title']) ? $resources['title'] : "";
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
                $hint = trim($resource['content']);
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

    // Clear all old samples
    $deleteQuery = "DELETE FROM tm_tasks_tests WHERE idTask = :idTask AND (sGroupType = 'Example' OR sGroupType = 'Evaluation');";
    $stmt = $db->prepare($deleteQuery);
    $stmt->execute(['idTask' => $taskId]);

    foreach ($resources['task'] as $i => $resource) {
        if ($resource['type'] == 'sample' && isset($resource['name']) && $resource['name']) {
            $stmt = $db->prepare($insertQuery);
            $stmt->execute(['idTask' => $taskId, 'sGroupType' => 'Example', 'sName' => $resource['name'], 'sInput' => $resource['inContent'], 'sOutput' => $resource['outContent']]);
        }
    }
    if (!isset($resources['grader'])) {
        return;
    }
    foreach ($resources['grader'] as $i => $resource) {
        if ($resource['type'] == 'sample' && isset($resource['name']) && $resource['name']) {
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
    // Old subtasks aren't deleted to not break old submissions, they are
    // rather put as inactive with bActive = 0
    global $db;

    if(!isset($metadata['subtasks'])) {
        // No more subtasks
        $stmt = $db->prepare("UPDATE tm_tasks_subtasks SET bActive = 0 WHERE idTask = :idTask;");
        $stmt->execute(['idTask' => $taskId]);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM tm_tasks_subtasks WHERE idTask = :idTask AND bActive = 1 ORDER BY iRank ASC;");
    $stmt->execute(['idTask' => $taskId]);

    // Check whether subtasks are the same
    $iRank = 0;
    $ok = true;
    foreach($metadata['subtasks'] as $subtask) {
        $oldSubtask = $stmt->fetch();
        $ok = ($oldSubtask['iRank'] == $iRank
            && $oldSubtask['name'] == $subtask['name']
            && $oldSubtask['comments'] == $subtask['comments']
            && $oldSubtask['iPointsMax'] == $subtask['gradeMax']);
        if(!$ok) { break; }
        $iRank += 1;
    }
    $ok = $ok && ($stmt->fetch() == false);

    if($ok) { return; }

    // Subtasks are different
    $stmt = $db->prepare("UPDATE tm_tasks_subtasks SET bActive = 0 WHERE idTask = :idTask;");
    $stmt->execute(['idTask' => $taskId]);

    $iRank = 0;
    foreach($metadata['subtasks'] as $subtask) {
        $stmt = $db->prepare("INSERT INTO tm_tasks_subtasks (idTask, iRank, name, comments, iPointsMax, bActive) VALUES (:idTask, :iRank, :name, :comments, :iPointsMax, 1);");
        $stmt->execute(['idTask' => $taskId, 'iRank' => $iRank, 'name' => $subtask['name'], 'comments' => $subtask['comments'], 'iPointsMax' => $subtask['gradeMax']]);
        $iRank += 1;
    }
}

function saveResources($data, $sTaskPath, $subdir, $revision, $dirPath, $acceptMovedTasks) {
    global $config, $db;
    $subdir = trim($subdir);
    $subdir = trim($subdir, '/');

    $metadata = $data[0]['metadata'];
    $resources = $data[0]['resources'];

    if (!isset($metadata['id']) || !isset($metadata['language'])) {
        die(json_encode(['success' => false, 'error' => 'task_error_metadata']));
    }
    $textId = $metadata['id'];

    // Get information for the path
    $stmt = $db->prepare("SELECT * FROM tm_tasks WHERE sTextId = :sTextId;");
    $stmt->execute(['sTextId' => $textId]);
    $taskInfo = $stmt->fetch();

    // Check whether task has already been imported
    $alreadyImported = false;
    $differentTask = false;
    if ($taskInfo) {
        $taskId = $taskInfo['ID'];
        if ($taskInfo['sTaskPath'] == $sTaskPath && $taskInfo['sRevision'] == $revision) {
            $alreadyImported = true;
        } elseif ($taskInfo['sTaskPath'] != $sTaskPath) {
            // Task with same ID but different path
            $differentTask = true;
            if(!$acceptMovedTasks) {
                die(json_encode(['success' => false, 'error' => 'task_error_moved']));
            }
        }
    }

    if(!$alreadyImported) {
        $taskId = saveTask($metadata, $sTaskPath, $subdir, $revision, $resources);
        // limits
        if(isset($metadata['limits'])) {
            saveLimits($taskId, $metadata['limits']);
        }
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

    // Check whether it's a Codecast task
    $codecast = false;
    foreach($resources['task'] as $resource) {
        if($resource['type'] == 'javascript' && isset($resource['id']) && substr($resource['id'], 0, 8) == 'codecast') {
            $codecast = true;
            break;
        }
    }

    $normalUrl = $config->normalUrl.$taskId;
    $ltiUrl = $config->ltiUrl.$taskId;
    if($codecast) {
        $normalUrl = $config->codecastUrl.$taskId;
        $ltiUrl = $config->codecastLtiUrl.$taskId;
    }

    echo(json_encode([
        'success' => true,
        'ID' => $taskId,
        'normalUrl' => $normalUrl,
        'tokenUrl' => addToken($normalUrl),
        'ltiUrl' => $ltiUrl,
        ]));
}
