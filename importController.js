window.i18next.use(window.i18nextXHRBackend);
var i18nextOpts = {
  lng: 'en',
  fallbackLng: ['en', 'fr'],
  fallbackNS: 'svnimport',
  ns: ['svnimport'],
  };
i18nextOpts['backend'] = {
  'allowMultiLoading': false,
  'loadPath': function (lng, ns) {
                  return '/i18n/'+lng+'/'+ns+'.json'+config.urlArgs;
                }
  };
window.i18next.init(i18nextOpts);
window.i18next.on('initialized', function (options) {
  window.i18nextOptions = options;
});

// from http://stackoverflow.com/questions/979975/
var QueryString = function () {
  // This function is anonymous, is executed immediately and
  // the return value is assigned to QueryString!
  var query_string = {};
  var query = window.location.search.substring(1);
  var vars = query.split("&");
  for (var i=0;i<vars.length;i++) {
    var pair = vars[i].split("=");
        // If first entry with this name
    if (typeof query_string[pair[0]] === "undefined") {
      query_string[pair[0]] = decodeURIComponent(pair[1]);
        // If second entry with this name
    } else if (typeof query_string[pair[0]] === "string") {
      var arr = [ query_string[pair[0]],decodeURIComponent(pair[1]) ];
      query_string[pair[0]] = arr;
        // If third or later entry with this name
    } else {
      query_string[pair[0]].push(decodeURIComponent(pair[1]));
    }
  }
  return query_string;
}();

// Create channel with parent
var jschannel = window.parent !== window ? Channel.build({
    window: window.parent,
    origin: '*',
    scope: 'importer'
    }) : null;


var app = angular.module('svnImport', ['jm.i18next']);

app.controller('importController', ['$scope', '$http', '$timeout', '$i18next', '$sce', function ($scope, $http, $timeout, $i18next, $sce) {
    $scope.template = 'templates/full.html';

    $scope.options = {lang: $i18next.options.lng};
    $scope.defaultParams = null;
    $scope.checkoutState = '';
    $scope.logList = [];
    $scope.hideOldLogs = true;
    $scope.nbOldLogs = 0;
    $scope.showLogin = true;
    $scope.repoType = 'svn';

    $scope.svnBaseUrl = config.svnBaseUrl;
    $scope.params = {
        svnUrl: config.svnExampleUrl,
        localeEn: 'default',
        theme: 'none',
        acceptMovedTasks: false,
        token: QueryString.token ? QueryString.token : null
        };

    $scope.tasksRemaining = [];
    $scope.curRev = null;
    $scope.curTaskUrl = '';
    $scope.curTask = null;
    $scope.curLog = null;
    $scope.curID = null;
    $scope.curData = [];

    $scope.edition = {
        path: '',
        editorUrl: ''
    };

    $scope.changeLang = function() {
        $i18next.changeLanguage($scope.options.lang);
        localStorage.setItem('lang', $scope.options.lang);
    };

    $scope.toggleOldLogs = function() {
        $scope.hideOldLogs = !$scope.hideOldLogs;
    };

    $scope.switchType = function(newType) {
        $scope.repoType = newType;
        localStorage.setItem('repoType', newType);
    };

    $scope.saveParams = function() {
        $scope.defaultParams = {
            recursive: !!$scope.params.recursive,
            noimport: !!$scope.params.noimport,
            rewritecommon: !!$scope.params.rewritecommon,
            localeEn: $scope.params.localeEn,
            theme: $scope.params.theme
            };
        localStorage.setItem('defaultParams', JSON.stringify($scope.defaultParams));
    };

    $scope.removeToken = function() {
        $scope.params.token = null;
    };

    $scope.getParamsDisabled = function() {
        if(!$scope.defaultParams) { return false; }
        var same = true;
        for(var opt in $scope.defaultParams) {
            if(typeof($scope.defaultParams[opt]) == 'boolean') {
                same = same && (!$scope.defaultParams[opt] == !$scope.params[opt]);
            } else {
                same = same && ($scope.defaultParams[opt] == $scope.params[opt]);
            }
        }
        return same;
    };

    $scope.makeUrl = function(url, urlArgs, lang, lti) {
        var args = [];
        for(var arg in urlArgs) {
            args.push(arg + '=' + encodeURIComponent(urlArgs[arg]));
        }
        if(lang == 'en' && $scope.params.localeEn != 'default') {
            args.push('sLocale=' + lang + '_' + $scope.params.localeEn);
        } else if(lang) {
            args.push('sLocale=' + lang);
        }
        if(lti && $scope.params.theme != 'none') {
            args.push('theme=' + $scope.params.theme);
        }
        if(args.length) {
            if(url.indexOf('?') == -1) {
                url += '?';
            } else {
                url += '&';
            }
            url += args.join('&');
        }
        return url;
    };

    $scope.makeLtiUrl = function(url, urlArgs) {
        var taskUrl = $scope.makeUrl(url, urlArgs);
        return $scope.makeUrl("https://lti.algorea.org/", {taskUrl: taskUrl}, null, true);
    };

    $scope.getTemplate = function(log) {
        // Return template for each log type
        return 'templates/logImport.html';
    };

    $scope.notifyLink = function(linkParams) {
        if(!jschannel) { return; }
        jschannel.notify({
            method: 'link',
            params: linkParams});
    };

    $scope.sanitizeInput = function() {
        function san(val) {
            return (val && val.replace(/\\/g, '/')) || '';
        }
        function sanUrl(val) {
            return san(val).replace(/\/$/, '').replace(/\.git$/, '');
        }
        $scope.params.svnUrl = san($scope.params.svnUrl);
        $scope.params.gitPath = san($scope.params.gitPath);
        $scope.params.gitUrl = sanUrl($scope.params.gitUrl);
    };

    $scope.updateCommon = function() {
        // Update _common
        $scope.checkoutState = 'checkout_common_update';
        $scope.checkoutMsg = '';

        var params = Object.assign({}, $scope.params);
        params.action = 'updateCommon';
        $http.post('savesvn.php', params, {responseType: 'json'}).then(function(res) {
            if (res.data.success) {
                $scope.checkoutState = 'checkout_common_updated';
            } else {
                $scope.checkoutState = 'checkout_common_failed';
            }}, function() {
                $scope.checkoutState = 'checkout_common_failed';
            });

    };

    $scope.checkout = function() {
        // Unhighlight old tasks
        $scope.nbOldLogs = 0;
        for(var i=0; i < $scope.logList.length; i++) {
            $scope.logList[i].active = false;
            $scope.nbOldLogs += 1;
        }

        if($scope.curID) {
            $scope.logList[0].state = 'task_cancelled';
            $scope.curLog.state = 'file_cancelled';
            $scope.curID = null;
        }

        $scope.sanitizeInput();

        if($scope.repoType == 'svn') {
            $scope.checkoutSvn();
        } else if($scope.repoType == 'git') {
            $scope.checkoutGit();
        }
    }

    $scope.checkoutSvn = function() {
        // Checkout the SVN and get the list of tasks

        if((!$scope.params.username || !$scope.params.password) && !$scope.params.token) {
            $scope.showLogin = true;
            $scope.loginRequired = true;
            if(jschannel) { jschannel.notify({method: 'syncError'}); }
            return;
        }

        $scope.checkoutState = 'checkout_inprogress';
        $scope.checkoutMsg = '';
        $scope.tasksRemaining = [];
        $scope.curTaskUrl = '';
        $scope.showLogin = false;
        $scope.loginRequired = false;
        $scope.disableBtn = true;
        $scope.ready = {checkout: false, local_common: false};
        // Allows to check we're still in the same import request
        var ready = $scope.ready;

        if($scope.curID) {
            $scope.logList[0].state = 'task_cancelled';
            $scope.curLog.state = 'file_cancelled';
            $scope.curID = null;
        }

        // Save credentials
        if($scope.params.remember && !$scope.params.token) {
            localStorage.setItem('username', $scope.params.username);
            localStorage.setItem('password', $scope.params.password);
        }

        // Filter path for double slashes
        $scope.params.svnUrl = $scope.params.svnUrl.replace(/\/+/g, '/');
        if($scope.params.svnUrl[0] == '/') {
            $scope.params.svnUrl = $scope.params.svnUrl.substr(1);
        }

        function onFail(res) {
            $scope.checkoutState = 'checkout_error';
            $scope.checkoutMsg = res.data.error;
            $scope.showLogin = true;
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        function onRequestFail() {
            $scope.checkoutState = 'checkout_request_failed';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'checkoutSvn';
        $http.post('savesvn.php', params1, {responseType: 'json'}).then(function(res) {
            if (res.data.success && res.data.tasks) {
                $scope.tasksRemaining = res.data.tasks;
                $scope.curRev = res.data.revision;
                $scope.disableBtn = false;
                $scope.recImportWhenReady('checkout', ready);
            } else {
                onFail(res);
            }}, onRequestFail);

        // Update _local_common
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'updateLocalCommon';
        $http.post('savesvn.php', params2, {responseType: 'json'}).then(function(res) {
            if (res.data.success) {
                $scope.recImportWhenReady('local_common', ready);
            } else {
                onFail(res);
            }}, onRequestFail);
    };

    $scope.checkoutGit = function() {
        // Checkout the git repository and get the list of tasks

        $scope.checkoutState = 'checkout_inprogress';
        $scope.checkoutMsg = '';
        $scope.tasksRemaining = [];
        $scope.curTaskUrl = '';
        $scope.showLogin = false;
        $scope.loginRequired = false;
        $scope.disableBtn = true;
        $scope.ready = {checkout: false};
        // Allows to check we're still in the same import request
        var ready = $scope.ready;

        localStorage.setItem('gitUrl', $scope.params.gitUrl);

        if($scope.params.gitRemember) {
            localStorage.setItem('gitUsername', $scope.params.gitUsername);
            localStorage.setItem('gitPassword', $scope.params.gitPassword);
        }

        // Filter paths for double slashes
        $scope.params.gitUrl = $scope.params.gitUrl.substr(0, 10) + $scope.params.gitUrl.substr(10).replace(/\/+/g, '/');
        $scope.params.gitPath = $scope.params.gitPath.replace(/\/+/g, '/');
        if($scope.params.gitPath[0] == '/') {
            $scope.params.gitPath = $scope.params.gitPath.substr(1);
        }

        function onFail(res) {
            $scope.checkoutState = 'checkout_error';
            $scope.checkoutMsg = res.data.error;
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        function onRequestFail() {
            $scope.checkoutState = 'checkout_request_failed';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'checkoutGit';
        $http.post('savesvn.php', params1, {responseType: 'json'}).then(function(res) {
            if (res.data.success && res.data.tasks) {
                $scope.tasksRemaining = res.data.tasks;
                $scope.curRev = res.data.revision;
                $scope.disableBtn = false;
                $scope.recImportWhenReady('checkout', ready);
            } else {
                onFail(res);
            }}, onRequestFail);
    };

    $scope.recImportWhenReady = function(step, ready) {
        // Check all requests have completed before proceeding

        // Did a request fail?
        if(!$scope.ready) { return; }

        // Are we still in the same import request?
        if(ready !== $scope.ready) { return; }

        // Check everything is ready
        $scope.ready[step] = true;
        for(var x in $scope.ready) {
            if(!$scope.ready[x]) { return; }
        }

        // Everything is ready, proceed
        $scope.checkoutState = 'checkout_import';
        $scope.recImport();
    };

    $scope.recImport = function() {
        // Start import of one task
        var curTask = $scope.tasksRemaining.shift();
        if(!curTask) {
            $scope.checkoutState = 'checkout_finished';
            if(jschannel) { jschannel.notify({method: 'syncFinished'}); }
            return;
        }
        $scope.curTask = curTask;
        if(curTask.imported) {
            $scope.logList.unshift({
                type: 'import',
                url: curTask.svnUrl,
                svnRev: $scope.curRev,
                state: 'task_noimport',
                active: true,
                normalUrl: curTask.normalUrl,
                ltiUrl: curTask.ltiUrl,
                hasLti: curTask.hasLti,
                tokenUrl: curTask.tokenUrl,
                urlArgs: curTask.urlArgs,
                foundLangs: []
                });
            $scope.notifyLink({
                url: curTask.normalUrl,
                ltiUrl: curTask.ltiUrl,
                testUrl: curTask.tokenUrl,
                task: curTask.svnUrl
                });
            $scope.recImport();
            return;
        }
        $scope.curData = [];
        $scope.logList.unshift({
            type: 'import',
            url: curTask.svnUrl,
            urlArgs: curTask.urlArgs,
            hasLti: curTask.hasLti,
            tokenUrl: curTask.tokenUrl,
            svnRev: $scope.curRev,
            taskPath: curTask.taskPath,
            state: 'task_loading',
            active: true,
            files: []
            });
        $scope.recTaskImport();
    };

    $scope.recTaskImport = function() {
        // Start import of one file of the task
        var curTask = $scope.curTask;
        if(curTask.files.length == 0) {
            $scope.endTaskImport();
            return;
        }

        var curFile = curTask.files.shift();
        var curLog = {
            name: curFile.filename,
            isStatic: curFile.isStatic || curFile.isMarkdown,
            isMarkdown: curFile.isMarkdown
            };
        $scope.curLog = curLog;
        $scope.logList[0].files.push(curLog);
        if(curFile.isStatic) {
            // Static file, we display it then continue
            curLog.state = 'file_static';
            curLog.url = curTask.staticUrl + curFile.filename;
            curLog.warnPaths = curFile.warnPaths;
            curLog.commonRewritten = curFile.commonRewritten;
            curLog.gitRepo = curTask.gitRepo;
            curLog.gitPath = curTask.gitPath;

            $scope.notifyLink({
                url: $scope.makeUrl(curLog.url, curTask.urlArgs),
                task: curTask.svnUrl
                });
            $scope.recTaskImport();
        } else if (curFile.isMarkdown) {
            // Markdown file, we load and convert it
            curLog.state = 'file_loading';
            $scope.convertMarkdown(curTask, curFile);
        } else {
            // TaskPlatform file, we fetch its resources
            curLog.state = 'file_loading';
            curLog.warnPaths = curFile.warnPaths;
            curLog.commonRewritten = curFile.commonRewritten;
            $scope.curTaskUrl = curTask.baseUrl + curFile.filename;
            $timeout($scope.fetchResources, 2000);
        }
    };

    $scope.fetchResources = function() {
        // Import the informations from the task contained in the iframe
        var thisID = Math.random() * 1000000000 + 0;
        $scope.curID = thisID + 0;

        function throwError() {
            if(thisID != $scope.curID) { return; }
            $scope.curLog.state = 'file_error';
            $timeout.cancel(getInfosTimeout);
            $scope.recTaskImport();
        };

        // Allow 20 seconds to import the task
        var getInfosTimeout = $timeout(function () {
            if(thisID != $scope.curID) { return; }
            $scope.curLog.state = 'file_timeout';
            $scope.curID = null;
            $scope.recTaskImport();
        }, 20000);

        // Fetch resources from task
        TaskProxyManager.getTaskProxy('taskIframe', function(task) {
            if(thisID != $scope.curID) { return; }
            window.task = task;

            $scope.curLog.state = 'file_resources';

            var platform = new Platform(task);
            TaskProxyManager.setPlatform(task, platform);
            task.load({metadata: true, grader: true, solution: true, hints: true, editor: true, resources: true}, function() {
                if(thisID != $scope.curID) { return; }
                task.getMetaData(function(metadata) {
                    if(thisID != $scope.curID) { return; }
                    task.getResources(function(resources) {
                        if(thisID != $scope.curID) { return; }

                        // Success!
                        $scope.curID = null;
                        $scope.curLog.state = 'file_done';
                        $scope.curData.push({filename: $scope.curLog.name, resources: resources, metadata: metadata});
                        $timeout.cancel(getInfosTimeout);
                        $scope.$apply($scope.recTaskImport);
                    }, throwError);
                }, throwError);
            }, throwError);
        }, true);

    };

    $scope.endTaskImport = function() {
        // Finish task import by sending resources (if any)
        var log = $scope.logList[0];
        if($scope.curData.length == 0) {
            log.state = 'task_import_error';
            $scope.recImport();
            return;
        }

        var foundLangs = [];
        for(var i=0; i<$scope.curData.length; i++) {
            var data = $scope.curData[i];
            var newLang = data.metadata['language'];
            if(foundLangs.indexOf(newLang) > -1) {
                $scope.logList[0].doubleLang = true;
            } else {
                foundLangs.push(newLang);
            }

            // Determine which file is the reference
            if($scope.curTask.refLang && data.metadata['language'] == $scope.curTask.refLang) {
                // Put this data first
                $scope.curData.splice(i, 1);
                $scope.curData.unshift(data);
            }
        }
        $scope.logList[0].foundLangs = foundLangs;
        $scope.logList[0].foundLangsStr = foundLangs.join(', ');

        $scope.logList[0].state = 'task_sending';
        var curRev = $scope.curRev ? $scope.curRev : Math.floor((new Date()).getTime() / 1000);
        $http.post('savesvn.php', {
            action: 'saveResources',
            data: $scope.curData,
            svnRev: curRev,
            svnUrl: $scope.curTask.svnUrl,
            taskPath: $scope.curTask.taskPath,
            dirPath: $scope.curTask.dirPath,
            acceptMovedTasks: $scope.params.acceptMovedTasks
            }).then(function(res) {
                // Success!
                if(res.data.success) {
                    log.state = 'task_success';
                    log.normalUrl = res.data.normalUrl;
                    log.ltiUrl = res.data.ltiUrl;
                    log.tokenUrl = res.data.tokenUrl;

                    // Notify parent of link
                    $scope.notifyLink({
                        url: res.data.normalUrl,
                        ltiUrl: res.data.ltiUrl,
                        testUrl: res.data.tokenUrl,
                        task: log.url
                        });
                } else {
                    log.state = 'task_save_error';
                    log.error = res.data.error;
                }
                $scope.recImport();
            }, function() {
                // Error
                log.state = 'task_save_error';
                log.error = true;
                $scope.recImport();
            });
    };

    $scope.convertMarkdown = function (curTask, curFile) {
        // Convert a markdown file to HTML
        var url = curTask.baseUrl + curFile.filename;
        var log = $scope.logList[0];
        $http.get(url).then(function (res) {
            var markdown = mdcompiler.parseHeader(res.data);
            var html = mdcompiler.compileMarkdown(markdown.body);
            $scope.curLog.state = 'file_done';
            log.state = 'task_sending';
            $http.post('savesvn.php', {
                action: 'saveMarkdown',
                headers: markdown.headers,
                html: html,
                dirPath: curTask.dirPath,
                gitRepo: curTask.gitRepo,
                gitPath: curTask.gitPath,
                taskPath: $scope.curTask.taskPath,
                filename: curFile.filename
            }).then(function (res) {
                log.state = 'task_success';
                $scope.curLog.url = res.data.url;
                $scope.recTaskImport();
            })
        });
    }

    $scope.displayFull = function() {
        $scope.template = 'templates/full.html';
    };

    $scope.initParams = function() {
        // Restore saved parameters, handle GET arguments
        if(localStorage.getItem('lang')) {
            $scope.options.lang = localStorage.getItem('lang');
            $scope.changeLang();
        }

        if(localStorage.getItem('repoType')) { $scope.repoType = localStorage.getItem('repoType'); }
        if(localStorage.getItem('gitUrl')) { $scope.params.gitUrl = localStorage.getItem('gitUrl'); }

        if(localStorage.getItem('defaultParams')) {
            $scope.defaultParams = JSON.parse(localStorage.getItem('defaultParams'));
            for(var opt in $scope.defaultParams) {
                $scope.params[opt] = $scope.defaultParams[opt];
            }
        }

        if(localStorage.getItem('username')) {
            $scope.params.username = localStorage.getItem('username');
            if(localStorage.getItem('password')) {
                $scope.params.password = localStorage.getItem('password');
            }
            $scope.params.remember = true;
        }

        if(localStorage.getItem('gitUsername')) {
            $scope.params.gitUsername = localStorage.getItem('gitUsername');
            if(localStorage.getItem('gitPassword')) {
                $scope.params.gitPassword = localStorage.getItem('gitPassword');
            }
            $scope.params.gitRemember = true;
        }

        // Handle GET arguments
        if(QueryString.type) { $scope.repoType = QueryString.type == 'git' ? 'git' : 'svn'; }
        if(QueryString.repo) { $scope.params.gitUrl = QueryString.repo; }
        if(QueryString.path) {
            if($scope.repoType == 'git') {
                $scope.params.gitPath = QueryString.path;
            } else {
                $scope.params.svnUrl = QueryString.path;
            }
        }
        if (QueryString.filename) { $scope.params.filename = QueryString.filename; }
        if(QueryString.username) { $scope.params.username = QueryString.username; }
        if(QueryString.password) { $scope.params.password = QueryString.password; }
        if(QueryString.revision) { $scope.params.revision = QueryString.revision; }
        if(QueryString.recursive) { $scope.params.recursive = QueryString.recursive; }
        if(QueryString.noimport) { $scope.params.noimport = QueryString.noimport; }
        if(QueryString.rewritecommon) { $scope.params.rewritecommon = QueryString.rewritecommon; }
        if(QueryString.localeEn) { $scope.params.localeEn = QueryString.localeEn; }
        if(QueryString.theme) { $scope.params.theme = QueryString.theme; }
        if(QueryString.display == 'frame') { 
            $scope.template = 'templates/frame.html';
        }

        if(QueryString.autostart) {
            $scope.checkout();
        } else if (QueryString.edition) {
            $scope.checkoutEdit();
        }
    };

    $scope.bindChannel = function() {
        if(!window.jschannel) { return; }
        jschannel.bind('syncRepository', function() {
            $scope.$apply($scope.checkoutSvn);
            });
    };
    $scope.bindChannel();

    $scope.checkoutEdit = function () {
        $scope.edition.lastTemplate = $scope.template;
        //$scope.template = 'templates/edition.html';
        //$scope.edition.ready = false;
        $scope.checkoutState = 'Checking out Git repository...';
        $scope.showLogin = false;
        localStorage.setItem('gitUrl', $scope.params.gitUrl);

        if ($scope.params.gitRemember) {
            localStorage.setItem('gitUsername', $scope.params.gitUsername);
            localStorage.setItem('gitPassword', $scope.params.gitPassword);
        }

        // Filter paths for double slashes
        $scope.params.gitUrl = $scope.params.gitUrl.substr(0, 10) + $scope.params.gitUrl.substr(10).replace(/\/+/g, '/');
        $scope.params.gitPath = $scope.params.gitPath || '';
        $scope.params.gitPath = $scope.params.gitPath.replace(/\/+/g, '/');
        if ($scope.params.gitPath[0] == '/') {
            $scope.params.gitPath = $scope.params.gitPath.substr(1);
        }

        function onFail(err) {
            $scope.checkoutState = 'Error checking out';
            if (err) { $scope.checkoutState += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        function onRequestFail() {
            $scope.checkoutState = 'Checkout request failed';
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'checkoutEdition';
        $http.post('savesvn.php', params1, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            $scope.prepareEdition();
        }, onRequestFail);
    }

    $scope.editionCommit = function () {
        $scope.edition.saving = false;

        function onFail(err) {
            $scope.checkoutState = 'Error pushing the commit';
            if (err) { $scope.checkoutState += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        function onRequestFail() {
            $scope.checkoutState = 'Checkout request failed';
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'commitEdition';
        params1.session = $scope.edition.session;
        $http.post('savesvn.php', params1, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            if ($scope.edition.saveCallback) {
                $scope.edition.saveCallback();
                $scope.edition.saveCallback = null;
            }
        }, onRequestFail);
    }

    $scope.createEditionChannel = function () {
        $scope.edition.channel = Channel.build({
            window: document.getElementById('edition-iframe').contentWindow,
            origin: '*',
            scope: 'editor'
        });

        $scope.edition.channel.bind('saved', function () {
            $scope.$apply($scope.editionCommit);
        });
    }

    $scope.prepareEdition = function () {
        $scope.checkoutState = 'Preparing edition session...';
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'prepareEdition';

        function onFail(err) {
            $scope.checkoutState = 'Error starting edition session';
            if (err) { $scope.checkoutState += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        function onRequestFail() {
            $scope.checkoutState = 'Prepare edition request failed';
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        $http.post('savesvn.php', params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            $scope.edition.session = res.data.session;
            $scope.edition.token = res.data.token;
            $scope.edition.path = params2.gitPath + ' (from ' + params2.gitUrl + ')';
            $scope.startEdition();
        }, onRequestFail);
    }

    $scope.startEdition = function () {
        $scope.checkoutState = null;
        $scope.template = 'templates/edition.html';
        $scope.edition.ready = true;
        var url = 'https://fioi2.mblockelet.info/markdown-editor/'; // TODO
        url += '?session=' + $scope.edition.session;
        url += '&token=' + $scope.edition.token
        url += '&api=' + encodeURIComponent(window.location.origin + '/edition/');
        if ($scope.params.filename) {
            url += '&filename=' + encodeURIComponent($scope.params.filename);
        }
        $scope.edition.editorUrl = $sce.trustAsResourceUrl(url);
        $timeout($scope.createEditionChannel, 100);
    }

    $scope.editionSave = function (mode) {
        $scope.showLogin = false;
        $scope.edition.channel.notify({
            method: 'save',
            params: {}
        });
        $scope.edition.saving = true;
        if (mode == 'close') {
            $scope.edition.saveCallback = function () {
                $scope.editionCancel();
            };
        } else if (mode == 'import') {
            $scope.edition.saveCallback = function () {
                $scope.editionCancel();
                $scope.params.recursive = true;
                $scope.checkout();
            };
        }
    }

    $scope.editionCancel = function () {
        $scope.template = $scope.edition.lastTemplate;
        $scope.edition = {
            ready: false,
            status: '',
            session: null,
            token: null,
            editorUrl: null,
            saving: false
        };
    }

    $scope.editionHistory = function () {
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'historyEdition';

        function onFail() {
            $scope.edition.status = 'Error fetching history';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        function onRequestFail() {
            $scope.checkoutState = 'History request failed';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        $http.post('savesvn.php', params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res);
                return;
            }
            $scope.edition.history = {
                commits: res.data.history,
                editorAdditional: res.data.editorAdditional,
                masterAdditional: res.data.masterAdditional
            };
            $scope.edition.history.editedHash = $scope.edition.oldVersion || $scope.edition.history.commits[0].hash
            $scope.edition.history.selected = $scope.edition.history.editedHash;
            $scope.edition.history.commits.map(function (x) {
                var d = new Date(x.date * 1000);
                x.date = d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
            });
        }, onRequestFail);
    }

    $scope.editionHistoryRestore = function (first) {
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'checkoutHashEdition';

        if (first) {
            $scope.edition.oldVersion = null;
            params2.hash = 'HEAD';
        } else {
            $scope.edition.oldVersion = $scope.edition.history.selected;
            params2.hash = $scope.edition.history.selected;
        }

        function onFail() {
            $scope.edition.status = 'Error fetching history';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        function onRequestFail() {
            $scope.checkoutState = 'History request failed';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        $http.post('savesvn.php', params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res);
                return;
            }
            $scope.closeEditionHistory();
            $scope.prepareEdition();
        }, onRequestFail);
    }

    $scope.makeDiffUrl = function (hash, type) {
        var isGitlab = $scope.params.gitUrl.indexOf('gitlab.com') != -1;
        var url = $scope.params.gitUrl;
        if (isGitlab) {
            url += '/-';
        }
        if (type == 'commit') {
            return url + '/commit/' + hash;
        } else {
            return url + '/compare/' + type + '...' + hash;
        }
    }

    $scope.closeEditionHistory = function () {
        $scope.edition.history = null;
    }

    $scope.initParams();
}]);
