<?php

require_once __DIR__ . '/funcs.inc.php';

function saveMarkdownQuiz($html, $headers, $checkoutPath, $gitRepo, $gitPath, $filename, $quizAnswers)
{
    global $config, $markdownIds, $workingDir;

    $dirPath = md5($gitRepo) . '/' . md5(pathJoin($gitPath, $filename)) . '/';
    if (!file_exists(pathJoin($workingDir, 'files/checkouts/', $dirPath))) {
        mkdir(pathJoin($workingDir, 'files/checkouts/', $dirPath), 0777, true);
    }
    $filePath = pathJoin($dirPath, 'index.html');
    $localFilePath = pathJoin($workingDir, 'files/checkouts/', $filePath);

    $title = isset($headers['title']) ? $headers['title'] : '';
    $sideurl = isset($headers['notebook']) ? "https://static-items.algorea.org/jupyter/v0/notebooks/index.html?sLocale=en&path=" . urlencode($headers['notebook']) : '';

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

    $options = [
        "graderUrl" => "https://static-items.algorea.org/bsm/quiz",
        "score_calculation_formula" => "default",
        "score_calculation" => ["formula" => "default"],
        "feedback_on_wrong_choices" => "all"
    ];
    try {
        if(isset($headers['options'])) {
            $options = array_merge($options, json_decode($headers['options'], true));
        }
    } catch (Exception $e) {}
    if($sideurl) {
        $options['sideUrl'] = $sideurl;
    }

    $fullHtml = '<!DOCTYPE html><html><head>';
    $fullHtml .= '<meta charset="utf-8">';
    $fullHtml .= '<title>' . $title . '</title>';
    $fullHtml .= '<script type="text/javascript">
            var stringsLanguage = "en";
            var quiz_settings = ';
    $fullHtml .= json_encode($options);
    $fullHtml .= ';';
    $fullHtml .= 'var task_data_info = {"markdown":false};
            var quiz_question_types = {"single":true,"multiple":true};
                  </script>';
    $fullHtml .= '<script class="remove" type="text/javascript" src="/files/checkouts/modules/pemFioi/importModules-1.4-mobileFirst.js" id="import-modules"></script>
        <script class="remove" type="text/javascript" src="/files/checkouts/modules/pemFioi/quiz2/loader.js"></script>
        <script class="remove" type="text/javascript">
            var json = {"id":"quiz","authors":[],"language":"fr"};
            var modulesPath = "/files/checkouts/modules/";
            loadQuizModules();
        </script>
        <script class="remove" type="text/javascript" src="grader_data.js"></script>';

    $fullHtml .= '<style>statement { color: #1E22AA !important; }</style>';
    $fullHtml .= '</head><body dir="ltr">';
    $fullHtml .= '<div dir="auto" id="task" style="display: none">';
    $fullHtml .= '<div dir="auto" class="intro"></div>';
    $fullHtml .= '<div dir="auto" class="taskContent">';
    $fullHtml .= $html;
    $fullHtml .= '</div></div>';
    $fullHtml .= '</body></html>';

    file_put_contents($localFilePath, $fullHtml);

    $graderData = 'window.Quiz.grader.data = ' . json_encode($quizAnswers) . ';';

    if(isset($headers['feedback']) && $headers['feedback'] == 'probabl') {
        $graderData .= 'window.Quiz.grader.feedback = function(score) {
  if(score < 50) {
    return "You lack some training on this topic.";
  } else if(score < 100) {
    return "You are almost there!";
  } else {
    return "You are ready for certification!";
  }
}';
    }
    file_put_contents(str_replace('index.html', 'grader_data.js', $localFilePath), $graderData);

    return [
        'success' => true,
        'url' => $config->staticUrl . $filePath
    ];
}
