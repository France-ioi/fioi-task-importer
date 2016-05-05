<?php

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>LTI SVN task importer</title>
    <link href="bower_components/bootstrap/dist/css/bootstrap.min.css" type="text/css" rel="stylesheet">
    <link href="local.css" type="text/css" rel="stylesheet">
    <script type="text/javascript" src="bower_components/jquery/dist/jquery.min.js"></script>
    <script type="text/javascript" src="bower_components/jschannel/src/jschannel.js"></script>
    <script type="text/javascript" src="bower_components/pem-platform/task-pr.js"></script>
    <script type="text/javascript" src="svnimport.js"></script>
  </head>
  <body>
    <h1>Import de sujets</h1>
    <p>Cette page vous permet d'importer un sujet créé sur le svn france-ioi dans la plateforme d'exercices. Une fois le sujet importé, cette page vous donnera les URL pour inclure l'exercice dans un environnement LTI ou la plateforme Algoréa.</p>
    <div id="form_cont">
        <form class="form-inline" role="form" id="svn_form">
            <div class="form-group">
                <label for="svnUrl">svn address:</label><br>
                  <div class="input-group">
                    <span class="input-group-addon" style="font-weight:bold;"><?= $config->svnBaseUrl; ?></span>
                    <input type="text" style="width:300px;" class="form-control" id="svnUrl" name="svnUrl" value="Examples/min3nombres/">
                  </div>
            </div><br>
            <div class="form-group">
                <label for="svnRev">svn revision (leave empty for HEAD):</label><br>
                <input type="text" class="form-control" name="svnRev">
            </div><br>
            <div class="form-group">
                <label for="svnUser">svn user:</label><br>
                <input type="text" class="form-control" name="svnUser">
            </div><br>
            <div class="form-group">
                <label for="svnPassword">svn password:</label><br>
                <input type="password" class="form-control" name="svnPassword">
            </div><br>
        </form>
        <button class="btn btn-default" onclick="saveSvn()">Import svn files</button>
    </div>
    <div id="state"></div>
    <div id="result" style="display:none;">
        <p><strong>Url pour LTI :</strong> <a href="" id="ltiUrl"></a></p>
        <p><strong>Url pour la plateforme Algoréa :</strong> <a href="" id="normalUrl"></a></p>
    </div>
    <iframe style="width:1px;height:1px;" id="taskIframe" src=""></iframe>
    <!--<button class="btn btn-default" onclick="getInfos()">getResources</button> -->
  </body>
</html>