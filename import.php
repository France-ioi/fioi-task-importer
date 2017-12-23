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
  <link href="local.css" type="text/css" rel="stylesheet">
  <script type="text/javascript" src="bower_components/angular/angular.min.js"></script>
  <script type="text/javascript" src="bower_components/angular-sanitize/angular-sanitize.min.js"></script>
  <script type="text/javascript" src="bower_components/i18next/i18next.min.js"></script>
  <script type="text/javascript" src="bower_components/i18next-xhr-backend/i18nextXHRBackend.min.js"></script>
  <script type="text/javascript" src="bower_components/ng-i18next/dist/ng-i18next.min.js"></script>
  <script type="text/javascript" src="bower_components/jquery/dist/jquery.min.js"></script>
  <script type="text/javascript" src="bower_components/jschannel/src/jschannel.js"></script>
  <script type="text/javascript" src="bower_components/pem-platform/task-pr.js"></script>
  <script type="text/javascript" src="importController.js"></script>
</head>
<body ng-app="svnImport" ng-controller="importController">
  <div class="container-fluid">
    <h1 ng-i18next="page_maintitle"></h1>
    <div style="float: right;">
      <span ng-i18next="language"></span> :
      <select ng-model="lang" ng-change="changeLang();">
        <option value="en" selected>English</option>
        <option value="fr">Français</option>
      </select>
    </div>
    <p ng-i18next="page_description"></p>
    <form class="form-inline" role="form" id="svn_form">
      <div class="row">
        <div id="form_cont" class="col-xs-12 col-lg-6">
          <div class="panel panel-primary">
            <div class="panel-heading">
              <h4 ng-i18next="panel_svn"></h4>
            </div>
            <div class="panel-body">
              <div class="form-group">
                <label for="svnUrl" ng-i18next="label_svnurl"></label><br>
                <div class="input-group">
                  <span class="input-group-addon" style="font-weight:bold;"><?= $config->svnBaseUrl; ?></span>
                  <input type="text" style="width:300px;" class="form-control" id="svnUrl" name="svnUrl" value="<?= $config->svnExampleUrl; ?>">
                </div>
              </div><br>
              <div class="form-group">
                <label for="svnRev" ng-i18next="label_svnrev"></label><br>
                <input type="text" class="form-control" name="svnRev">
              </div><br>
              <div class="form-group">
                <label for="username" ng-i18next="label_username"></label><br>
                <input type="text" class="form-control" name="username">
              </div><br>
              <div class="form-group">
                <label for="password" ng-i18next="label_password"></label><br>
                <input type="password" class="form-control" name="password">
              </div><br>
            </div>
          </div>
        </div>
        <div class="col-sm-12 col-lg-6">
          <div class="panel panel-info">
            <div class="panel-heading">
              <h4 ng-i18next="panel_options"></h4>
            </div>
            <div class="panel-body">
              <div class="checkbox">
                <input type="checkbox" name="recursive"> <span ng-i18next="label_recimport"></span>
              </div><br>
              <div class="checkbox">
                <input type="checkbox" name="noimport"> <span ng-i18next="label_noimport"></span>
              </div><br>
              <div class="form-group">
                <label for="optLocale" ng-i18next="label_locale_en"></label><br>
                <select class="form-control" id="optLocale" ng-model="localeEn">
                  <option value="default" ng-i18next="default" selected></option>
                  <option value="gb" ng-i18next="locale_gb"></option>
                  <option value="us" ng-i18next="locale_us"></option>
                </select>
              </div><br>
              <div class="form-group">
                <label for="optTheme" ng-i18next="label_theme_lti"></label><br>
                <select class="form-control" id="optTheme" ng-model="theme">
                  <option value="none" ng-i18next="none" selected></option>
                  <option value="funtelecom">Telecom</option>
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>
      <input type="submit" class="btn btn-primary" ng-click="checkoutSvn();" ng-i18next="[value]label_submit"></input>
    </form>
    <hr />
    <div ng-if="checkoutState" class="alert alert-info">
      <b><span ng-i18next="{{ checkoutState }}"></span> <span ng-if="checkoutMsg" ng-i18next="{{ checkoutMsg }}"></span></b>
      <span ng-if="tasksRemaining.length">
        <br />{{ tasksRemaining.length }} <span ng-i18next="display_tasks_left"></span>
      </span>
    </div>
    <div ng-repeat="log in logList">
      <div class="panel" ng-class="{'panel-primary': log.active, 'panel-default': !log.active, 'hidden': !log.active && hideOldLogs}">
        <div class="panel-heading">
          <h3 class="panel-title">{{ log.url }} <small>(rev {{ log.svnRev }})</small></h3>
        </div>
        <div class="panel-body">
          <p ng-if="log.ltiUrl"><b ng-i18next="task_ltiurl"></b>: <a href="{{ makeUrl(log.ltiUrl) }}" target="_blank">{{ makeUrl(log.ltiUrl, null, true) }}</a></p>
          <ul ng-if="log.foundLangs.length > 1">
            <li ng-repeat="taskLang in log.foundLangs"><span ng-i18next="task_ltiurl_lang"></span> {{ taskLang }} : <a ng-href="{{ makeUrl(log.ltiUrl, taskLang, true) }}" target="_blank">{{ makeUrl(log.ltiUrl, taskLang, true) }}</a></li>
          </ul>
          <p ng-if="log.normalUrl"><b ng-i18next="task_normalurl"></b>: <a href="{{ log.normalUrl }}" target="_blank">{{ makeUrl(log.normalUrl) }}</a></p>
          <div ng-if="log.tokenUrl && log.foundLangs.length <= 1">
            <p><a class="btn btn-default" href="{{ log.tokenUrl }}" target="_blank" ng-i18next="task_test"></a> <i ng-i18next="task_test_description"></i></p>
          </div>
          <div ng-if="log.tokenUrl && log.foundLangs.length > 1">
            <span ng-repeat="taskLang in log.foundLangs"><a class="btn btn-default" ng-href="{{ makeUrl(log.tokenUrl, taskLang) }}" target="_blank"><span ng-i18next="task_test_lang"></span> {{ taskLang }}</a> </span>
            <p><i ng-i18next="task_test_description"></i></p>
          </div>
          <hr ng-if="log.normalUrl" />
          <p><span ng-i18next="status"></span>: <span ng-i18next="{{ log.state }}"></span></p>
          <p ng-if="log.doubleLang" ng-i18next="task_doublelang"></p>
          <p ng-if="log.foundLangsStr"><span ng-i18next="task_langs"></span> : {{ log.foundLangsStr }}.</p>
          <hr ng-if="log.files" />
          <div ng-repeat="file in log.files">
            <p><code>{{ file.name }}</code>: <span ng-i18next="{{ file.state }}"></span></p>
            <p ng-if="file.isStatic"><span ng-i18next="display_static"></span> <a href="{{ file.url }}">{{ file.url }}</a></p>
            <p ng-if="file.warnPaths" ng-i18next="display_warnpaths"></p>
          </div>
        </div>
      </div>
    </div>
    <div ng-if="nbOldLogs">
      <button ng-if="hideOldLogs" class="btn btn-primary" ng-click="toggleOldLogs();" style="width: 100%;">{{ nbOldLogs }} <span ng-i18next="display_nboldlogs"></span> <span class="glyphicon glyphicon-chevron-down"></span></button>
      <button ng-if="!hideOldLogs" class="btn btn-primary" ng-click="toggleOldLogs();" style="width: 100%;"><span ng-i18next="display_hideoldlogs"></span> <span class="glyphicon glyphicon-chevron-up"></span></button>
    </div>

    <iframe style="width:1px; height:1px;" id="taskIframe" src="{{ curTaskUrl }}"></iframe>
  </div>
</body>
</html>
