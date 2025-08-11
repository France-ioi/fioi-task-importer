<?php

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title ng-i18next="page_title">LTI SVN task importer</title>
  <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" type="text/css" rel="stylesheet">
  <link href="style.css" type="text/css" rel="stylesheet">
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/fontawesome.min.css" />
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/solid.min.css" />
  <link rel="stylesheet" href="node_modules/highlight.js/styles/github.min.css" />
  <link rel="stylesheet" type="text/css" href="node_modules/diff2html/bundles/css/diff2html.min.css" />

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
      'newEditorApiEndpoint' => $config->newEditorApiEndpoint,
      'notebookUrl' => $config->notebookUrl
      ]) ?>
  </script>
  <script type="text/javascript" src="node_modules/diff2html/bundles/js/diff2html-ui.min.js"></script>
  <script type="text/javascript" src="node_modules/angular/angular.min.js"></script>
  <script type="text/javascript" src="node_modules/angular-sanitize/angular-sanitize.min.js"></script>
  <script type="text/javascript" src="node_modules/i18next/i18next.min.js"></script>
  <script type="text/javascript" src="node_modules/i18next-xhr-backend/i18nextXHRBackend.min.js"></script>
  <script type="text/javascript" src="node_modules/ng-i18next/dist/ng-i18next.min.js"></script>
  <script type="text/javascript" src="node_modules/jquery/dist/jquery.min.js"></script>
  <script type="text/javascript" src="node_modules/jschannel/src/jschannel.js"></script>
  <script type="text/javascript" src="node_modules/pem-platform/task-xd-pr.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="markdown/dist/markdown-bundle.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="editApi.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript" src="importController.js<?=$config->urlArgs ?>"></script>
</head>
<body ng-app="svnImport" ng-controller="importController">
  <div class="container-fluid" ng-class="mainDivClass" ng-include="template">
  </div>
</body>
</html>
