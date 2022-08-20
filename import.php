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
  <?php
if($config->localCssUrl) {
  echo '  <link href="' . $config->localCssUrl . '" type="text/css" rel="stylesheet">';
}
?>
  <script type="text/javascript" src="bower_components/angular/angular.min.js"></script>
  <script type="text/javascript" src="bower_components/angular-sanitize/angular-sanitize.min.js"></script>
  <script type="text/javascript" src="bower_components/i18next/i18next.min.js"></script>
  <script type="text/javascript" src="bower_components/i18next-xhr-backend/i18nextXHRBackend.min.js"></script>
  <script type="text/javascript" src="bower_components/ng-i18next/dist/ng-i18next.min.js"></script>
  <script type="text/javascript" src="bower_components/jquery/dist/jquery.min.js"></script>
  <script type="text/javascript" src="bower_components/jschannel/src/jschannel.js"></script>
  <script type="text/javascript" src="bower_components/pem-platform/task-pr.js"></script>
  <script type="text/javascript" src="/markdown/dist/markdown-bundle.js"></script>
  <script type="text/javascript" src="importController.js<?=$config->urlArgs ?>"></script>
  <script type="text/javascript">
    var config = <?=json_encode(['svnBaseUrl' => $config->svnBaseUrl, 'svnExampleUrl' => $config->svnExampleUrl, 'urlArgs' => $config->urlArgs]) ?>
  </script>
</head>
<body ng-app="svnImport" ng-controller="importController">
  <div class="container-fluid" ng-include="template">
  </div>
</body>
</html>
