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
  <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
  <script type="text/javascript" src="check.js<?=$config->urlArgs ?>" defer></script>
</head>
<body>
  <div class="container-fluid" id="app">
    <h1>Task checker</h1>
    <p>Use this page to check that a task or a task folder is valid.</p>
    <form class="form-inline" role="form" id="svn_form">
      <div class="row">
        <div id="form_cont" class="col-xs-12 col-lg-6">
          <div class="panel panel-primary">
            <div class="panel-heading">
              <h4>{{ translations['panel_svn'] }}</h4>
            </div>
            <div class="panel-body">
              <ul class="nav nav-tabs nav-justified">
                <li :class="repoType === 'svn' ? 'active' : ''"><a @click.prevent="switchType('svn')">SVN</a></li>
                <li :class="repoType === 'git' ? 'active' : ''"><a @click.prevent="switchType('git')">Git</a></li>
              </ul>
              <div v-if="repoType === 'svn'">
                <div class="form-group form-group-full">
                  <label for="svnUrl">{{ translations['label_svnurl'] }}</label><br>
                  <div class="input-group">
                    <span class="input-group-addon" style="font-weight:bold;" id="svnBaseUrl">{{ svnBaseUrl }}</span>
                    <input type="text" class="form-control" id="svnUrl" name="svnUrl" v-model="params.svnUrl">
                  </div>
                </div><br>
                <div class="form-group">
                  <label for="svnRev">{{ translations['label_svnrev'] }}</label><br>
                  <input type="text" class="form-control" name="svnRev" v-model="params.svnRev">
                </div><br>
                <div class="form-group" :class="loginRequired && !params.username && 'has-error'" v-if="!params.token">
                  <label for="username" class="control-label">{{ translations['label_username'] }}</label><br>
                  <input type="text" class="form-control" name="username" v-model="params.username">
                </div><br v-if="!params.token">
                <div class="form-group" :class="loginRequired && !params.password && 'has-error'" v-if="!params.token">
                  <label for="password" class="control-label">{{ translations['label_password'] }}</label><br>
                  <input type="password" class="form-control" name="password" v-model="params.password">
                </div><br v-if="!params.token">
                <div class="checkbox" v-if="!params.token">
                  <input type="checkbox" name="remember" v-model="params.remember"> <span>{{ translations['label_remember'] }}</span>
                </div>
                <br>
              </div>
              <div v-if="repoType === 'git'">
                <div class="form-group form-group-full">
                  <label for="gitUrl">{{ translations['label_giturl'] }}</label><br>
                  <input type="text" class="form-control" id="gitUrl" name="gitUrl" placeholder="https://github.com/..." v-model="params.gitUrl" value="https://github.com/France-ioi/bebras-tasks">
                </div><br>
                <div class="form-group form-group-full">
                  <label for="gitPath">{{ translations['label_gitpath'] }}</label><br>
                  <input type="text" class="form-control" name="gitPath" placeholder="path/to/task/" v-model="params.gitPath" value="module_testing/test-responsive-interface">
                </div><br>
                <div class="form-group form-group-full" v-if="!params.token">
                  <label for="gitUsername">{{ translations['label_git_username'] }}</label><br>
                  <input type="text" class="form-control" id="gitUsername" name="gitUsername" v-model="params.gitUsername">
                </div><br v-if="!params.token">
                <div class="form-group form-group-full" v-if="!params.token">
                  <label for="gitPassword">{{ translations['label_git_password'] }}</label><br>
                  <input type="password" class="form-control" id="gitPassword" name="gitPassword" v-model="params.gitPassword">
                </div><br>
                <div class="checkbox">
                  <input type="checkbox" name="gitRemember" v-model="params.gitRemember"> <span>{{ translations['label_remember'] }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <input type="submit" class="btn btn-primary" @click.prevent="check" :disabled="disableBtn" value="Check"/>
    </form>
    <hr/>
    <div v-if="checkoutState" class="alert" :class="'alert-' + checkoutState.status">
      <h4
        v-if="checkoutState.status && 'info' != checkoutState.status"
        class="alert-heading"
       >{{ translations['checkout_status_' + checkoutState.status] }}</h4>
      <b>
        <span>{{ translations[checkoutState.message] }}</span>
        <span v-if="checkoutMsg">{{ translations[checkoutMsg] }}</span>
      </b>
      <span v-if="tasksRemaining.length">
        <br/>
          {{ tasksRemaining.length }} <span>{{ translations['display_tasks_left'] }}</span>
      </span>
    </div>
<!--    <div ng-repeat="log in logList" ng-include="getTemplate(log)">-->
<!--    </div>-->
<!--    <div v-if="nbOldLogs">-->
<!--      <button v-if="hideOldLogs" class="btn btn-primary" @click.prevent="toggleOldLogs();" style="width: 100%;">{{ nbOldLogs-->
<!--        }} <span>{{ translations['display_nboldlogs"></span> <span class="glyphicon glyphicon-chevron-down'] }}</span></button>-->
<!--      <button v-if="!hideOldLogs" class="btn btn-primary" @click.prevent="toggleOldLogs();" style="width: 100%;"><span-->
<!--         >{{ translations['display_hideoldlogs"></span> <span class="glyphicon glyphicon-chevron-up'] }}</span></button>-->
<!--    </div>-->
<!---->
<!--    <iframe v-if="curTaskUrl" style="width:1px; height:1px;" id="taskIframe" src="{{ curTaskUrl }}"></iframe>-->
  </div>
</body>
</html>
