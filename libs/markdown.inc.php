<?php

require_once __DIR__ . '/funcs.inc.php';

function saveMarkdown($html, $headers, $checkoutPath, $gitRepo, $gitPath, $filename)
{
    global $config, $markdownIds, $workingDir;

    $dirPath = md5($gitRepo) . '/' . md5(pathJoin($gitPath, $filename)) . '/';
    if (!file_exists(pathJoin($workingDir, 'files/checkouts/', $dirPath))) {
        mkdir(pathJoin($workingDir, 'files/checkouts/', $dirPath), 0777, true);
    }
    $filePath = pathJoin($dirPath, 'index.html');
    $localFilePath = pathJoin($workingDir, 'files/checkouts/', $filePath);

    $title = isset($headers['title']) ? $headers['title'] : '';

    if(!isset($markdownIds)) {
        if(file_exists(__DIR__ . '/../markdown_textid_to_id.json')) {
            $markdownIds = json_decode(file_get_contents(__DIR__ . '/../markdown_textid_to_id.json'), true);
        } else {
            $markdownIds = [];
        }
    }

    $localIds = [];
    if(isset($markdownIds[$gitRepo])) {
        $localIds = $markdownIds[$gitRepo];
    }
    $slug = isset($headers['slug']) ? $headers['slug'] : substr($gitPath, 4);

    // Find all src=""
    $imagesFound = [];
    preg_match_all('/src="([^"]+)"/', $html, $imagesFound);
    foreach($imagesFound[1] as $img) {
        $imgPath = pathJoin('files/checkouts/', $checkoutPath, $img);
        if (file_exists($imgPath)) {
            $imgDir = pathJoin($workingDir, 'files/checkouts/', $dirPath, dirname($img));
            if (!file_exists($imgDir)) {
                mkdir($imgDir, 0777, true);
            }
            copy($imgPath, pathJoin('files/checkouts/', $dirPath, $img));
            // Enable lazy loading
            $html = str_replace('src="' . $img . '"', 'lazysrc="' . $img . '"', $html);
        }
    }

    // Find all href=""
    $urlsFound = [];
    preg_match_all('/href="([^"]+)"/', $html, $urlsFound);
    foreach($urlsFound[1] as $url) {
        if(substr($url, 0, 4) == 'http') {
            continue;
        }
        if(substr($url, 0, 1) == '#') {
            // Local anchor
            $html = str_replace('href="' . $url . '"', 'title="Scroll to &quot;' . substr($url, 1) . '&quot;" onclick="platformScrollTo(\'' . $url . '\');"', $html);
            continue;
        }

        // uslug is for relative paths
        $uslug = getAbsolutePath(pathJoin(dirname($slug), $url));
        $urlHashExp = explode('#', $url);
        $urlHash = isset($urlHashExp[1]) ? $urlHashExp[1] : null;
        $targetUrl = $urlHashExp[0];
        $targetUrl = rtrim('/' . ltrim($targetUrl, '/'), '/');
        $uslug = rtrim('/' . ltrim($uslug, '/'), '/');
        if(($targetUrl == $slug || $uslug == $slug) && $urlHash) {
            // Local anchor
            $html = str_replace('href="' . $url . '"', 'title="Scroll to &quot;' . $urlHash . '&quot;" onclick="platformScrollTo(\'#' . $urlHash . '\');"', $html);
            continue;
        }
        if(isset($localIds[$targetUrl])) {
            $html = str_replace('href="' . $url . '"', 'title="Go to &quot;' . $targetUrl . '&quot;" onclick="platformOpenUrl({itemId: \'' . $localIds[$targetUrl] . '\'});"', $html);
        } elseif(isset($localIds[$uslug])) {
            $html = str_replace('href="' . $url . '"', 'title="Go to &quot;' . $uslug . '&quot;" onclick="platformOpenUrl({itemId: \'' . $localIds[$uslug] . '\'});"', $html);
        } elseif(count($localIds) > 0) {
            // ID not found, log
            file_put_contents(__DIR__.'/../logs/markdown_ids.log', date('Y-m-d H:i:s') . ' - repo=' . $gitRepo . ' / targetUrl=' . $targetUrl . ' / uslug=' . $uslug . "\n", FILE_APPEND);
        }
    }

    $fullHtml = '<!DOCTYPE html><html><head>';
    $fullHtml .= '<meta charset="utf-8">';
    $fullHtml .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
    $fullHtml .= '<title>' . $title . '</title>';
    $fullHtml .= '<script src="/markdown/dist/markdown-task.js"></script>';
    $fullHtml .= '<script type="text/javascript">
                    window.staticTaskOptions = { autoValidate: true, checkHideTitle: true };
                    window.json = { editorUrl: "' . $config->baseUrl . '?edition=true&display=frame&type=git&repo=' . urlencode($gitRepo) . '&path=' . urlencode($gitPath) . '&filename=' . urlencode($filename) . '" };
                  </script>';
    $fullHtml .= '';
    $fullHtml .= '</head><body>';
    $fullHtml .= '<h1>' . $title . '</h1>';
    $fullHtml .= $html;
    $fullHtml .= '</body></html>';

    file_put_contents($localFilePath, $fullHtml);

    return [
        'success' => true,
        'url' => $config->staticUrl . $filePath,
        'cfUrl' => $config->cfUrl . $filePath
    ];
}
