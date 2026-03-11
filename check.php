<?php

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Task Checker</title>
  <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" type="text/css" rel="stylesheet">
  <link href="style.css" type="text/css" rel="stylesheet">
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/fontawesome.min.css" />
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/solid.min.css" />
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
  <script type="text/javascript" src="node_modules/jschannel/src/jschannel.js"></script>
  <script type="text/javascript" src="node_modules/pem-platform/task-xd-pr.js<?=$config->urlArgs ?>"></script>
  <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
  <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <script type="text/babel" src="check.js<?=$config->urlArgs ?>"></script>
</head>
<body>
  <div id="app"></div>
</body>
</html>
