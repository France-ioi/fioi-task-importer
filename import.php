<?php

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title ng-i18next="page_title">LTI SVN task importer</title>
  <link href="bower_components/bootstrap/dist/css/bootstrap.min.css" type="text/css" rel="stylesheet">
  <link href="style.css" type="text/css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/fontawesome.min.css" integrity="sha512-giQeaPns4lQTBMRpOOHsYnGw1tGVzbAIHUyHRgn7+6FmiEgGGjaG0T2LZJmAPMzRCl+Cug0ItQ2xDZpTmEc+CQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/solid.min.css" integrity="sha512-6mc0R607di/biCutMUtU9K7NtNewiGQzrvWX4bWTeqmljZdJrwYvKJtnhgR+Ryvj+NRJ8+NnnCM/biGqMe/iRA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.13.1/styles/github.min.css" />
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/diff2html/bundles/css/diff2html.min.css" />

  <?php
if($config->localCssUrl) {
  echo '  <link href="' . $config->localCssUrl . '" type="text/css" rel="stylesheet">';
}
?>
  <script type="text/javascript">
    var config = <?=json_encode([
      'svnBaseUrl' => $config->svnBaseUrl,
      'svnExampleUrl' => $config->svnExampleUrl,
      'urlArgs' => $config->urlArgs,
      'editors' => $config->editors,
      'newEditionEndpoint' => $config->newEditionEndpoint,
      'newEditorApiEndpoint' => $config->newEditorApiEndpoint
      ]) ?>
  </script>
  <script type="text/javascript" src="/diff2html-ui.min.js"></script>
  <script type="text/javascript" src="bower_components/angular/angular.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/angular-sanitize/angular-sanitize.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/i18next/i18next.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/i18next-xhr-backend/i18nextXHRBackend.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/ng-i18next/dist/ng-i18next.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/jquery/dist/jquery.min.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/jschannel/src/jschannel.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="bower_components/pem-platform/task-pr.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="/markdown/dist/markdown-bundle.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="editApi.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="importController.js<?=$config->urlArgs ?>"></script>
</head>
<body ng-app="svnImport" ng-controller="importController">
  <div class="container-fluid" ng-class="mainDivClass" ng-include="template">
  </div>
</body>
</html>
