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
        $markdownIds = json_decode(file_get_contents(__DIR__ . '/../markdown_textid_to_id.json'), true);
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
            $html = str_replace('href="' . $url . '"', 'onclick="platformScrollTo(\'' . $url . '\');"', $html);
            continue;
        }

        // uslug is for relative paths
        $uslug = getAbsolutePath(pathJoin(dirname($slug), $url));
        $targetUrl = explode('#', $url)[0];
        $targetUrl = rtrim('/' . ltrim($targetUrl, '/'), '/');
        $uslug = rtrim('/' . ltrim($uslug, '/'), '/');
        if(isset($localIds[$targetUrl])) {
            $html = str_replace('href="' . $url . '"', 'onclick="platformOpenUrl({item_id: \'' . $localIds[$targetUrl] . '\'});"', $html);
        } elseif(isset($localIds[$uslug])) {
            $html = str_replace('href="' . $url . '"', 'onclick="platformOpenUrl({item_id: \'' . $localIds[$uslug] . '\'});"', $html);
        } else {
            // for debug
            // $html = str_replace('href="' . $url . '"', 'onclick="platformOpenUrl({not_found: \'' . $url . '\', not_found_slug: \'' . $uslug . '\'});"', $html);
        }
    }

    $fullHtml = '<!DOCTYPE html><html><head>';
    $fullHtml .= '<meta charset="utf-8">';
    $fullHtml .= '<meta http-equiv="X-UA-Compatible" content="IE=edge">';
    $fullHtml .= '<title>' . $title . '</title>';
    $fullHtml .= '<script src="/files/checkouts/_common/modules/ext/jquery/1.7/jquery.min.js"></script>
                  <script src="/files/checkouts/_common/modules/ext/jschannel/jschannel.js"></script>
                  <script src="/files/checkouts/_common/modules/integrationAPI.01/official/platform-pr.js"></script>
                  <script src="/files/checkouts/_common/modules/pemFioi/static-task.js"></script>';
    $fullHtml .= '<script type="text/javascript">window.staticTaskOptions = { autoValidate: true };</script>';
    $fullHtml .= '<script src="/markdown/dist/markdown-css.js"></script>';
    $fullHtml .= '</head><body>';
    $fullHtml .= '<h1>' . $title . '</h1>';
    $fullHtml .= $html;
    $fullHtml .= '</body></html>';

    file_put_contents($localFilePath, $fullHtml);

    return [
        'success' => true,
        'url' => $config->staticUrl . $filePath,
        'images' => $imagesFound[0]
    ];
}
