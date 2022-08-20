<?php

require_once __DIR__ . '/funcs.inc.php';

function saveMarkdown($html, $headers, $gitRepo, $gitPath, $filename)
{
    global $config, $workingDir;

    $dirPath = md5($gitRepo) . '/' . md5(pathJoin($gitPath, $filename)) . '/';
    if (!file_exists(pathJoin($workingDir, 'files/checkouts/', $dirPath))) {
        mkdir(pathJoin($workingDir, 'files/checkouts/', $dirPath), 0777, true);
    }
    $filePath = $dirPath . 'index.html';
    $localFilePath = pathJoin($workingDir, 'files/checkouts/', $filePath);

    $title = isset($headers['title']) ? $headers['title'] : '';

    // Replace img src= in html by another path
    $imagesFound = [];
    $html = preg_replace_callback('/src="(.*?)"/', function ($matches) {
        $src = $matches[0];
        $src = preg_replace('/^(.*?)static/', '', $src);
        return 'src="/files/opentezos/static' . $src . '"';
    }, $html);


    $fullHtml = '<!DOCTYPE html><html><head>';
    $fullHtml .= '<meta charset="utf-8">';
    $fullHtml .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
    $fullHtml .= '<title>' . $title . '</title>';
    $fullHtml .= '<script src="/files/checkouts/_common/modules/ext/jquery/1.7/jquery.min.js"></script>
    <script src="/files/checkouts/_common/modules/ext/jschannel/jschannel.js"></script>
    <script src="/files/checkouts/_common/modules/integrationAPI.01/official/platform-pr.js"></script>
    <script src="/files/checkouts/_common/modules/pemFioi/static-task.js"></script>';
    $fullHtml .= '<script src="/markdown/dist/markdown-css.js"></script>';
    $fullHtml .= '</head><body>';
    $fullHtml .= '<h1>' . $title . '</h1>';
    $fullHtml .= $html;
    $fullHtml .= '</body></html>';

    file_put_contents($localFilePath, $fullHtml);

    return [
        'success' => true,
        'url' => $config->staticUrl . $filePath
    ];
}
