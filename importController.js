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

var jschannel = null;
var isInIframe = window.parent !== window;
if (isInIframe) {
    // Create channel with parent
    jschannel = Channel.build({
        window: window.parent,
        origin: '*',
        scope: 'importer'
    });
}


function localStorageGetItem() {
    try {
        return localStorage.getItem.apply(localStorage, arguments);
    } catch (e) {
        return;
    }
}

function localStorageSetItem() {
    try {
        return localStorage.setItem.apply(localStorage, arguments);
    } catch (e) {
        return;
    }
}

function taskGraderLangToCodecastLang(baseLang) {
    baseLang = baseLang.toLocaleLowerCase();
    if (baseLang == 'python3') {
        return 'python';
    }

    return baseLang;
}


var app = angular.module('svnImport', ['jm.i18next']);

app.controller('importController', ['$scope', '$http', '$timeout', '$i18next', '$sce', '$interval', function ($scope, $http, $timeout, $i18next, $sce, $interval) {
    $scope.template = 'templates/full.html' + config.urlArgs;
    $scope.mainDivClass = '';

    if (isInIframe) {
        $scope.mainDivClass = "container-iframe";
    }

    $scope.config = window.config;
    $scope.options = {lang: $i18next.options.lng};
    $scope.defaultParams = null;
    $scope.checkoutState = null;
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
        token: QueryString.token ? QueryString.token : null,
        recursive: true
    };

    $scope.tasksRemaining = [];
    $scope.curRev = null;
    $scope.curTaskUrl = '';
    $scope.curTask = null;
    $scope.curLog = null;
    $scope.curID = null;
    $scope.curData = [];
    $scope.disableSolutionsEvaluationCount = 0;

    $scope.edition = {
        path: '',
        editorUrl: ''
    };

    $scope.changeLang = function() {
        $i18next.changeLanguage($scope.options.lang);
        localStorageSetItem('lang', $scope.options.lang);
    };

    $scope.toggleOldLogs = function() {
        $scope.hideOldLogs = !$scope.hideOldLogs;
    };

    $scope.switchType = function(newType) {
        $scope.repoType = newType;
        localStorageSetItem('repoType', newType);
    };

    $scope.saveParams = function() {
        $scope.defaultParams = {
            recursive: !!$scope.params.recursive,
            noimport: !!$scope.params.noimport,
            rewritecommon: !!$scope.params.rewritecommon,
            disableSolutionsEvaluation: !!$scope.params.disableSolutionsEvaluation,
            localeEn: $scope.params.localeEn,
            theme: $scope.params.theme
            };
        localStorageSetItem('defaultParams', JSON.stringify($scope.defaultParams));
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
        if (!url) { url = ''; }
        url = url.replace(/ /g, '%20');
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

    $scope.makeNotebookUrl = function (path) {
        return config.notebookUrl + encodeURIComponent(path);
    }

    $scope.getTemplate = function(log) {
        // Return template for each log type
        return 'templates/logImport.html' + config.urlArgs;
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
        $scope.checkoutState = {
            status: 'info',
            message: 'checkout_common_update',
        };
        $scope.checkoutMsg = '';

        var params = Object.assign({}, $scope.params);
        params.action = 'updateCommon';
        $http.post('savesvn.php', params, {responseType: 'json'}).then(function(res) {
            if (res.data.success) {
                $scope.checkoutState = {
                    status: 'success',
                    message: 'checkout_common_updated',
                };
            } else {
                $scope.checkoutState = {
                    status: 'danger',
                    message: 'checkout_common_failed',
                };
            }}, function() {
                $scope.checkoutState = {
                    status: 'danger',
                    message: 'checkout_common_failed',
                };
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

    $scope.initCheckout = function () {
        $scope.checkoutState = {
            status: 'info',
            message: 'checkout_inprogress',
        };
        $scope.checkoutMsg = '';
        $scope.tasksRemaining = [];
        $scope.curTaskUrl = '';
        $scope.showLogin = false;
        $scope.loginRequired = false;
        $scope.disableBtn = true;
        $scope.disableSolutionsEvaluationCount = 0;
    }

    $scope.checkoutSvn = function() {
        // Checkout the SVN and get the list of tasks

        if((!$scope.params.username || !$scope.params.password) && !$scope.params.token) {
            $scope.showLogin = true;
            $scope.loginRequired = true;
            if(jschannel) { jschannel.notify({method: 'syncError'}); }
            return;
        }

        $scope.initCheckout();
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
            localStorageSetItem('username', $scope.params.username);
            localStorageSetItem('password', $scope.params.password);
        }

        // Filter path for double slashes
        $scope.params.svnUrl = $scope.params.svnUrl.replace(/\/+/g, '/');
        if($scope.params.svnUrl[0] == '/') {
            $scope.params.svnUrl = $scope.params.svnUrl.substr(1);
        }

        function onFail(res) {
            $scope.checkoutState = {
                status: 'danger',
                message: 'checkout_error',
            };
            $scope.checkoutMsg = res.data.error;
            $scope.showLogin = true;
            $scope.ready = null;
            $scope.disableBtn = false;
        }

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'checkout_request_failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
        }

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

        $scope.initCheckout();
        $scope.ready = {checkout: false};
        // Allows to check we're still in the same import request
        var ready = $scope.ready;

        localStorageSetItem('gitUrl', $scope.params.gitUrl);

        if($scope.params.gitRemember) {
            localStorageSetItem('gitUsername', $scope.params.gitUsername);
            localStorageSetItem('gitPassword', $scope.params.gitPassword);
        }

        // Filter paths for double slashes
        $scope.params.gitUrl = $scope.params.gitUrl.substr(0, 10) + $scope.params.gitUrl.substr(10).replace(/\/+/g, '/');
        $scope.params.gitPath = $scope.params.gitPath.replace(/\/+/g, '/');
        if($scope.params.gitPath[0] == '/') {
            $scope.params.gitPath = $scope.params.gitPath.substr(1);
        }

        function onFail(res) {
            $scope.checkoutState = {
                status: 'danger',
                message: 'checkout_error',
            };
            $scope.checkoutMsg = res.data.error;
            $scope.ready = null;
            $scope.disableBtn = false;
        }

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'checkout_request_failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
        }

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'checkoutGit';
        $http.post('savesvn.php', params1, {responseType: 'json'}).then(function(res) {
            if (res.data.success && res.data.tasks) {
                $scope.tasksRemaining = res.data.tasks;
                $scope.curRev = res.data.revision;
                $scope.disableBtn = false;
                $http.get(res.data.baseTarget + 'variables.json').then(function (res) {
                    $scope.markdownVariables = res.data;
                    $scope.recImportWhenReady('checkout', ready);
                }, function () {
                    $scope.markdownVariables = null;
                    $scope.recImportWhenReady('checkout', ready);
                });
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
        $scope.checkoutState = {
            status: 'info',
            message: 'checkout_import',
        };
        $scope.recImport();
    };

    $scope.recImport = function() {
        // Start import of one task
        var curTask = $scope.tasksRemaining.shift();
        if(!curTask) {
            if (!$scope.checkoutState || 'info' === $scope.checkoutState.status) {
                $scope.checkoutState = {
                    status: 'success',
                    message: 'checkout_finished',
                };
            }
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
            isMarkdown: curFile.isMarkdown,
            isNotebook: curFile.isNotebook
            };
        $scope.curLog = curLog;
        $scope.logList[0].files.push(curLog);
        if(curFile.isStatic) {
            // Static file, we display it then continue
            curLog.url = curTask.staticUrl + curFile.filename;
            curLog.warnPaths = curFile.warnPaths;
            curLog.commonRewritten = curFile.commonRewritten;
            curLog.gitRepo = curTask.gitRepo;
            curLog.gitPath = curTask.gitPath;

            if (curFile.hasSolutionsToCheck && !$scope.params.disableSolutionsEvaluation) {
                $scope.curTaskUrl = $sce.trustAsResourceUrl(curTask.staticUrl + curFile.filename + '?xd=true');
                curLog.state = 'file_static_fetching_resources';
                $timeout(() => $scope.fetchResources(function (metadata, resources) {
                    curLog.state = 'file_static';
                    $scope.checkCorrectSolutions(null, resources, $scope.logList[0], function () {
                        $scope.notifyLink({
                            url: $scope.makeUrl(curLog.url, curTask.urlArgs),
                            task: curTask.svnUrl
                        });
                        $scope.recImport();
                    });
                }), 2000);
            } else {
                curLog.state = 'file_static';
                $scope.notifyLink({
                    url: $scope.makeUrl(curLog.url, curTask.urlArgs),
                    task: curTask.svnUrl
                });
                $scope.recTaskImport();
            }
        } else if (curFile.isMarkdown) {
            // Markdown file, we load and convert it
            curLog.state = 'file_loading';
            $scope.convertMarkdown(curTask, curFile);
        } else if (curFile.isNotebook) {
            // Notebook file, we don't need to do anything
            curLog.state = 'file_done';
            curLog.url = curTask.gitPath + '/' + curFile.filename;
            $scope.recTaskImport();
            //$scope.curLog.url = res.data.url;
        } else {
            // TaskPlatform file, we fetch its resources
            curLog.state = 'file_loading';
            curLog.warnPaths = curFile.warnPaths;
            curLog.commonRewritten = curFile.commonRewritten;
            $scope.curTaskUrl = $sce.trustAsResourceUrl(curTask.baseUrl + curFile.filename + '?xd=true');
            $timeout(() => $scope.fetchResources(function (metadata, resources) {
                $scope.curLog.state = 'file_done';
                $scope.curData.push({filename: $scope.curLog.name, resources: resources, metadata: metadata});
            }), 2000);
        }
    };

    $scope.fetchResources = function(callback) {
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
        $scope.importTask(thisID, function (task) {
            task.getMetaData(function(metadata) {
                if(thisID != $scope.curID) { return; }
                task.getResources(function(resources) {
                    if(thisID != $scope.curID) { return; }
                    $scope.curID = null;
                    callback(metadata, resources);
                    $timeout.cancel(getInfosTimeout);
                    $scope.$apply($scope.recTaskImport);
                }, throwError);
            }, throwError);
        }, throwError)
    };

    $scope.importTask = function (thisID, success, error) {
        TaskProxyManager.getTaskProxy('taskIframe', function(task) {
            if(thisID != $scope.curID) { return; }
            window.task = task;

            $scope.curLog.state = 'file_resources';

            var platform = new Platform(task);
            TaskProxyManager.setPlatform(task, platform);
            task.load({metadata: true, grader: true, solution: true, hints: true, editor: true, resources: true}, function() {
                if(thisID != $scope.curID) { return; }
                success(task);
            }, error);
        }, true);
    }

    $scope.checkCorrectSolutions = function(codecastUrl, resources, log, callback) {
        if (!resources.correct_solutions || 0 === resources.correct_solutions.length || $scope.params.disableSolutionsEvaluation) {
            callback();
            return;
        }

        log.correctSolutionsStatus = 'loading';
        log.correctSolutionsResults = [];

        window.Platform.prototype.getTaskParams = function(key, defaultValue, success, error) {
            var res = {minScore: 0, maxScore: 100, randomSeed: 0, noScore: 0, readOnly: false, options: {}};
            if (key) {
                if (key !== 'options' && key in res) {
                    res = res[key];
                } else if (res.options && key in res.options) {
                    res = res.options[key];
                } else {
                    res = (typeof defaultValue !== 'undefined') ? defaultValue : null;
                }
            }
            success(res);
        };

        if (null !== codecastUrl) {
            $scope.curTaskUrl = $sce.trustAsResourceUrl(codecastUrl);
            $scope.loadCorrectSolutionIframe(function () {
                $scope.onCorrectSolutionsLoaded(log, resources, callback);
            }, callback);
        } else {
            $scope.onCorrectSolutionsLoaded(log, resources, callback);
        }
    };

    $scope.loadCorrectSolutionIframe = function (callback, cancel) {
        $timeout(function () {
            var thisID = Math.random() * 1000000000 + 0;
            $scope.curID = thisID + 0;

            function throwError() {
                if(thisID != $scope.curID) { return; }
                $timeout.cancel(getInfosTimeout);
                cancel();
            }

            // Allow 20 seconds to import the task
            var getInfosTimeout = $timeout(function () {
                if(thisID != $scope.curID) { return; }
                $scope.curID = null;
                cancel();
            }, 20000);

            $scope.importTask(thisID, function () {
                $timeout.cancel(getInfosTimeout);
                callback();
            }, throwError);
        }, 2000);
    };

    $scope.onCorrectSolutionsLoaded = function (log, resources, callback) {
        log.correctSolutionsStatus = 'loaded';
        log.correctSolutionsToCheck = resources.correct_solutions;
        log.correctSolutionsCurrent = 0;
        $scope.$applyAsync();
        $scope.checkNextSolution(log, callback);
    }

    $scope.checkNextSolution = function (log, callback) {
        var correctSolution = log.correctSolutionsToCheck[log.correctSolutionsCurrent];
        var solutionCode = correctSolution.solution;
        var solutionLanguage = correctSolution.language;
        var fileName = correctSolution.id.replace(/\$TASK_PATH\//g, '');
        log.correctSolutionsResults[log.correctSolutionsCurrent] = {status: 'loading', fileName: fileName};

        var document = ('blockly' === solutionLanguage || 'scratch' === solutionLanguage) ? {
            type: 'block',
            content: {
                blockly: solutionCode,
            },
        } : {
            type: 'text',
            lines: solutionCode.split("\n"),
        };

        var answerObject = {
            platform: taskGraderLangToCodecastLang(solutionLanguage),
            document: document,
            fileName: fileName,
            version: '7.4.0', // Must be a Codecast version compatible with this answer format
        };
        if (correctSolution.level) {
            answerObject = {[correctSolution.level]: answerObject};
        }
        var constructedAnswer = JSON.stringify(answerObject);

        window.task.gradeAnswer(constructedAnswer, null, function (score) {
            var levelCoefficients = {
                basic: 0.25,
                easy: 0.5,
                medium: 0.75,
            };
            if (Array.isArray(score)) { score = score[0]; }
            if (correctSolution.level && correctSolution.level in levelCoefficients) {
                score = score / levelCoefficients[correctSolution.level];
            }

            var status = 'failed';
            if (Array.isArray(correctSolution.grade)) {
                if (score >= correctSolution.grade[0] && score <= correctSolution.grade[1]) {
                    status = 'success';
                }
            } else {
                if (score === correctSolution.grade) {
                    status = 'success';
                }
            }
            if ('failed' === status) {
                $scope.disableSolutionsEvaluationCount++;
                $scope.checkoutState = {
                    status: 'warning',
                    message: $scope.disableSolutionsEvaluationCount + ' ' + $i18next.t('check_correct_solution_failed'),
                };
            }

            log.correctSolutionsResults[log.correctSolutionsCurrent].status = status;
            log.correctSolutionsResults[log.correctSolutionsCurrent].obtainedScore = score;
            log.correctSolutionsResults[log.correctSolutionsCurrent].expectedScore = correctSolution.grade;
            log.correctSolutionsCurrent++;
            $scope.$applyAsync();
            if (log.correctSolutionsToCheck.length - 1 >= log.correctSolutionsCurrent) {
                $scope.checkNextSolution(log, callback);
            } else {
                callback();
            }
        }, function (e) {
            console.error("Error encountered during answer evaluation", e);
            log.correctSolutionsResults[log.correctSolutionsCurrent].status = 'errored';
        })
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

                    $scope.checkCorrectSolutions(res.data.normalUrl, $scope.curData[0].resources, log, function () {
                        // Notify parent of link
                        $scope.notifyLink({
                            url: res.data.normalUrl,
                            ltiUrl: res.data.ltiUrl,
                            testUrl: res.data.tokenUrl,
                            task: log.url
                        });
                        $scope.recImport();
                    });
                } else {
                    log.state = 'task_save_error';
                    log.error = res.data.error;
                    $scope.recImport();
                }
            }, function() {
                // Error
                log.state = 'task_save_error';
                log.error = true;
                $scope.recImport();
            });
    };

    $scope.convertQuiz = function(markdown) {
        var answers = [];
        var html = '';
        var remainingText = markdown.body;

        window.temp1 = remainingText;
        var titleMatch = remainingText.match(/^\s#+\s+.*?\n/);
        if (titleMatch) {
            remainingText = remainingText.substring(titleMatch[0].length);
        }

        var questionRegex = /^(.*?\+\+\+ {"tags".*?\+\+\+)(.*)$/s;
        var statementRegex = /^(.*?)```{admonition} Question\n(.*?)```/s;
        var solutionRegex = /\+\+\+ {"tags": \["solution"\]}\n(.*?)\+\+\+/s;
            
        var questionMatch;
        while(questionMatch = questionRegex.exec(remainingText)) {
            var questionText = questionMatch[1].trim();
            var question = {
                statement: '',
                answers: [],
                solution: '',
                type: "single"
            };

            var statementMatch = statementRegex.exec(questionText);
            var beforeStatement = statementMatch[1].trim();
            if (beforeStatement) {
                beforeStatement = beforeStatement.replace(/^\+\+\+\s*$/gm, '');
                html += "<div class=\"intro\">" + mdcompiler.compileMarkdown(beforeStatement, $scope.markdownVariables) + "</div>";
            }

            var fullStatement = statementMatch[2].trim();
            var statementLines = fullStatement.split('\n');
            var statement = '';
            var inAnswer = false;
            for(var i = 0; i < statementLines.length; i++) {
                var line = statementLines[i];
                if(line.match(/^- \S\)/)) {
                    // This is an answer line
                    inAnswer = true;
                    var answer = line.substring(4).trim();
                    question.answers.push(answer);
                } else if(inAnswer && line.trim() == '') {
                    inAnswer = false;
                } else if(inAnswer) {
                    // This is a continuation of the answer
                    question.answers[question.answers.length - 1] += ' ' + line.trim();
                } else if(line.trim() == '_Select all answers that apply_') {
                    question.type = 'multiple';
                } else if(line.trim() == '_Select a single answer_') {
                    question.type = 'single';
                } else {
                    statement += line + '\n';
                }
            }
            question.statement = "<statement>" + mdcompiler.compileMarkdown(statement, $scope.markdownVariables) + "</statement>";
            for(var i = 0; i < question.answers.length; i++) {
                question.statement += "<answer>" + mdcompiler.compileMarkdown(question.answers[i], $scope.markdownVariables) + "</answer>";
            }

            // Extract answers from the question text (assuming they are bullet points or similar)
            var solutionLines = solutionRegex.exec(questionText)[1].trim().split('\n');
            var solution = '';
            for(var i = 0; i < solutionLines.length; i++) {
                var line = solutionLines[i];
                var solutionMatch = line.match(/^solution: (.*)$/);
                if(solutionMatch) {
                    var solutionIds = solutionMatch[1].trim();

                    question.solution = solutionIds.match(/(\S)\)/g).map(function (sol) {
                        return parseInt(sol.charCodeAt(0) - 97);
                    });
                } else {
                    solution += line + '\n';
                }
            }
            question.statement += "<solution>" + mdcompiler.compileMarkdown(solution, $scope.markdownVariables) + "</solution>";
            question.statement = "<question type='" + question.type + "'>" + question.statement + "</question>";
            html += question.statement;
            answers.push(question.type == 'single' ? question.solution[0] : question.solution);

            remainingText = questionMatch[2];
        }

        return {html: html, answers: answers};
    }

    $scope.convertMarkdown = function (curTask, curFile) {
        // Convert a markdown file to HTML
        var url = curTask.baseUrl + curFile.filename;
        var log = $scope.logList[0];
        $http.get(url).then(function (res) {
            var markdown = mdcompiler.parseHeader(res.data);
            var isQuiz = markdown.headers.type == 'quiz';
            var quizAnswers = null;
            if(isQuiz) {
                var quiz = $scope.convertQuiz(markdown);
                var html = quiz.html;
                quizAnswers = quiz.answers;
            } else {
                var html = mdcompiler.compileMarkdown(markdown.body, $scope.markdownVariables);
            }
            $scope.curLog.state = 'file_done';
            log.state = 'task_sending';
            $http.post('savesvn.php', {
                action: 'saveMarkdown',
                headers: markdown.headers,
                html: html,
                isQuiz: isQuiz,
                quizAnswers: quizAnswers,
                dirPath: curTask.dirPath,
                gitRepo: curTask.gitRepo,
                gitPath: curTask.gitPath,
                taskPath: $scope.curTask.taskPath,
                filename: curFile.filename
            }).then(function (res) {
                log.state = 'task_success';
                $scope.curLog.url = res.data.url;
                $scope.curLog.cfUrl = res.data.cfUrl;
                $scope.recTaskImport();
            })
        });
    }

    $scope.displayFull = function() {
        $scope.template = 'templates/full.html' + config.urlArgs;
    };


    $scope.initParams = function() {
        // Restore saved parameters, handle GET arguments
        if (localStorageGetItem('lang')) {
            $scope.options.lang = localStorageGetItem('lang');
            $scope.changeLang();
        }

        if (localStorageGetItem('repoType')) { $scope.repoType = localStorageGetItem('repoType'); }
        if (localStorageGetItem('gitUrl')) { $scope.params.gitUrl = localStorageGetItem('gitUrl'); }

        if (localStorageGetItem('defaultParams')) {
            $scope.defaultParams = JSON.parse(localStorageGetItem('defaultParams'));
            for(var opt in $scope.defaultParams) {
                $scope.params[opt] = $scope.defaultParams[opt];
            }
        }

        if (localStorageGetItem('username')) {
            $scope.params.username = localStorageGetItem('username');
            if (localStorageGetItem('password')) {
                $scope.params.password = localStorageGetItem('password');
            }
            $scope.params.remember = true;
        }

        if (localStorageGetItem('gitUsername')) {
            $scope.params.gitUsername = localStorageGetItem('gitUsername');
            if (localStorageGetItem('gitPassword')) {
                $scope.params.gitPassword = localStorageGetItem('gitPassword');
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
        if(QueryString.disableSolutionsEvaluation) { $scope.params.disableSolutionsEvaluation = !!QueryString.disableSolutionsEvaluation; }
        if(QueryString.localeEn) { $scope.params.localeEn = QueryString.localeEn; }
        if(QueryString.theme) { $scope.params.theme = QueryString.theme; }
        if(QueryString.display == 'frame') { 
            $scope.template = 'templates/frame.html' + config.urlArgs;
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

    function getEditionEndpoint(endpoint) {
        if (config.newEditionEndpoint) {
            return config.newEditionEndpoint + endpoint;
        } else {
            return 'savesvn.php';
        }
    }

    function getEditorApiEndpoint() {
        return config.newEditorApiEndpoint || window.location.origin + '/edition/';
    }

    $scope.checkoutEdit = function () {
        $scope.edition.lastTemplate = $scope.template;
        //$scope.template = 'templates/edition.html';
        //$scope.edition.ready = false;
        $scope.checkoutState = {
            status: 'info',
            message: 'Checking out Git repository...',
        };
        $scope.showLogin = false;
        localStorageSetItem('gitUrl', $scope.params.gitUrl);

        if ($scope.params.gitRemember) {
            localStorageSetItem('gitUsername', $scope.params.gitUsername);
            localStorageSetItem('gitPassword', $scope.params.gitPassword);
        }

        // Filter paths for double slashes
        $scope.params.gitUrl = $scope.params.gitUrl.substr(0, 10) + $scope.params.gitUrl.substr(10).replace(/\/+/g, '/');
        $scope.params.gitPath = $scope.params.gitPath || '';
        $scope.params.gitPath = $scope.params.gitPath.replace(/\/+/g, '/');
        if ($scope.params.gitPath[0] == '/') {
            $scope.params.gitPath = $scope.params.gitPath.substr(1);
        }

        function onFail(err) {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Error checking out',
            };
            if (err) { $scope.checkoutState.message += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        }

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Checkout request failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'checkoutEdition';
        $http.post(getEditionEndpoint('checkoutEdition'), params1, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            $scope.prepareEdition();
        }, onRequestFail);
    }

    $scope.editionCommit = function () {
        $scope.edition.saveInfo.committing = true;
        function onFail(err) {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Error pushing the commit',
            };
            if (err) { $scope.checkoutState.message += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        }

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Checkout request failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        }

        // Checkout the task and get data
        var params1 = Object.assign({}, $scope.params);
        params1.action = 'commitEdition';
        params1.session = $scope.edition.session;
        params1.commitMsg = $scope.edition.saveInfo.commitMessage;
        $http.post(getEditionEndpoint('commitEdition'), params1, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            $scope.edition.saveInfo.committing = false;
            $scope.edition.saveInfo.committed = true;
            $scope.compareLastCommits(true);
            $timeout(function () { $scope.closeEditionPopup(); }, 3000);
        }, onRequestFail);
    }

    $scope.createEditionChannel = function () {
        if ($scope.edition.channel) {
            $scope.edition.channel.destroy();
        }
        $scope.edition.channel = Channel.build({
            window: document.getElementById('edition-iframe').contentWindow,
            origin: '*',
            scope: 'editor'
        });

        $scope.edition.channel.bind('saved', function () {
            $scope.$apply($scope.editionEditorSaved);
        });
    }

    $scope.prepareEdition = function () {
        $scope.checkoutState = {
            status: 'info',
            message: 'Preparing edition session...',
        };
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'prepareEdition';

        function onFail(err) {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Error starting edition session',
            };
            if (err) { $scope.checkoutState.message += ': ' + err; }
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        };

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'Prepare edition request failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
            $scope.showLogin = true;
        }

        $http.post(getEditionEndpoint('prepareEdition'), params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res.data.error);
                return;
            }
            $scope.edition.session = res.data.session;
            $scope.edition.token = res.data.token;
            $scope.edition.path = params2.gitPath + ' (from ' + params2.gitUrl + ')';
            $scope.edition.masterBranch = res.data.masterBranch;
            $scope.edition.history = {};
            $scope.edition.diff = {};
            $scope.edition.taskEditor = res.data.taskEditor;
            $scope.startEdition();
        }, onRequestFail);
    }

    $scope.startEdition = function () {
        if ($scope.edition.editorUrl) {
            $scope.edition.editorUrl = null;
            $timeout($scope.startEdition, 100);
            return;
        }
        $scope.checkoutState = null;
        $scope.template = 'templates/edition.html' + config.urlArgs;
        $scope.edition.ready = true;
        $scope.edition.isGitlab = $scope.params.gitUrl.indexOf('gitlab.com') != -1;
        $scope.edition.filename = $scope.params.filename || '';
        $scope.edition.externalUrl = $scope.params.gitUrl + '/' + ($scope.edition.isGitLab ? '-/' : '') + 'tree/' + $scope.edition.masterBranch + '/' + $scope.params.gitPath;

        if ($scope.edition.taskEditor) {
            var url = config.editors.taskEditor;
            $scope.edition.target = 'task';
        } else {
            var url = config.editors.markdownEditor;
            $scope.edition.target = $scope.edition.filename;
        }
        url += '?session=' + $scope.edition.session;
        url += '&token=' + $scope.edition.token
        url += '&api=' + encodeURIComponent(getEditorApiEndpoint());
        var depthSplit = $scope.params.gitPath.split('/');
        var depth = depthSplit.length;
        if (depthSplit[depthSplit.length - 1] == '') {
            depth--;
        }
        url += '&depth=' + depth;
        if ($scope.edition.filename) {
            url += '&filename=' + encodeURIComponent($scope.edition.filename);
        }
        $scope.edition.editorUrl = $sce.trustAsResourceUrl(url);

        $timeout($scope.createEditionChannel, 100);
        $scope.compareLastCommits();
        $interval($scope.compareLastCommits, 300000);
    }

    $scope.compareLastCommits = function (editionSaved) {
        if (!$scope.edition || !$scope.edition.ready) {
            if ($scope.compareLastCommitsInterval) {
                $interval.cancel($scope.compareLastCommitsInterval);
            }
            return;
        }

        $scope.editionGetHistory();

        var params2 = Object.assign({}, $scope.params);
        params2.action = 'getLastCommits';

        $http.post(getEditionEndpoint('getLastCommits'), params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                return;
            }
            if (!$scope.edition.lastCommits) {
                $scope.edition.lastCommits = {
                    origMaster: res.data.master,
                    origEditor: res.data.editor
                };
            }
            if (editionSaved) {
                $scope.edition.lastCommits.origEditor = res.data.editor;
            }
            $scope.edition.lastCommits.curMaster = res.data.master;
            $scope.edition.lastCommits.curEditor = res.data.editor;
        });
    }

    $scope.editionInfo = function () {
        $scope.edition.infoShow = true;
    }

    $scope.editionEditorSave = function () {
        if (!$scope.edition.saveInfo || $scope.edition.saveInfo.editorSaving) {
            return;
        }
        $scope.showLogin = false;
        $scope.edition.channel.notify({
            method: 'save',
            params: {}
        });
        $scope.edition.saveInfo.editorSaving = true;
    }

    $scope.editionEditorSaved = function () {
        if (!$scope.edition.saveInfo) { return; }
        $scope.edition.saveInfo.editorSaving = false;
        if ($scope.edition.saveInfo.doSave) {
            $scope.editionCommit();
        }
    }

    $scope.editionSave = function () {
        $scope.edition.saveInfo = {
            show: true,
            commitMessage: '',
            masterCommits: $scope.getHistoryUntilSplit($scope.edition.history.masterCommits)
        }
        $timeout(function () {
            document.getElementById('edition-save-message').focus();
        });
        $scope.editionEditorSave();
    }

    $scope.editionPublish = function () {
        if ($scope.edition.publishType != 'pr' && $scope.edition.publishType != 'mpr') {
            $scope.edition.publishType = 'pr';
        }
        if (!$scope.edition.publishInfo) {
            $scope.edition.publishInfo = {
                prTitle: 'Submission for editor changes',
                prBody: 'Submission for changes made from the editor',
                commits: $scope.getHistoryUntilSplit($scope.edition.history.commits)
            };
        }
        $scope.edition.publishInfo.show = true;
        $scope.edition.publishInfo.status = [];
        $scope.edition.publishInfo.doSave = false;
        $scope.makeDiffUI($scope.edition.history.commits[0].hash, 'master', 'edition-publish-diff');
    }

    $scope.editionMerge = function () {
        if ($scope.edition.publishType != 'prod' && $scope.edition.publishType != 'merge') {
            $scope.edition.publishType = 'prod';
        }
        if (!$scope.edition.mergeInfo) {
            $scope.edition.mergeInfo = {
                editorCommits: $scope.getHistoryUntilSplit($scope.edition.history.commits),
                masterCommits: $scope.getHistoryUntilSplit($scope.edition.history.masterCommits)
            };
        }
        $scope.edition.mergeInfo.show = true;
        $scope.makeDiffUI($scope.edition.history.commits[0].hash, 'master', 'edition-merge-diff');
    }

    $scope.editionDoSave = function () {
        if (!$scope.edition.saveInfo.commitMessage) {
            return;
        }
        $scope.edition.saveInfo.doSave = true;
        if ($scope.edition.saveInfo.editorSaving) {
            return;
        }
        $scope.editionCommit();
    }

    $scope.editionDoPublish = function () {
        $scope.edition.publishInfo.done = false;
        $scope.edition.publishInfo.error = false;
        $scope.edition.publishInfo.prUrl = null;
        $scope.edition.publishInfo.mprUrl = null;
        $scope.edition.publishInfo.publishing = true;

        var params2 = Object.assign({}, $scope.params);
        params2.action = 'publishEdition';
        params2.type = $scope.edition.publishType;
        params2.prTitle = $scope.edition.publishInfo.prTitle;
        params2.prBody = $scope.edition.publishInfo.prBody;

        $http.post(getEditionEndpoint('publishEdition'), params2, { responseType: 'json' }).then(function (res) {
            $scope.edition.publishInfo.publishing = false;
            if (!res.data.success) {
                $scope.edition.publishInfo.error = true;
                return;
            }

            $scope.edition.publishInfo.done = true;
            if (params2.type == 'mpr') {
                $scope.edition.publishInfo.mprUrl = $scope.makeDiffUrl(res.data.branch, 'pr');
            } else if (params2.type == 'pr') {
                $scope.edition.publishInfo.prUrl = res.data.prUrl;
            }

        }, function () {
            $scope.edition.publishInfo.publishing = false;
            $scope.edition.publishInfo.error = true;
        });
    }

    $scope.editionDoMerge = function () {
        $scope.edition.mergeInfo.done = false;
        $scope.edition.mergeInfo.error = false;
        $scope.edition.mergeInfo.merging = true;

        var params2 = Object.assign({}, $scope.params);
        params2.action = 'publishEdition';
        params2.type = $scope.edition.publishType;
        params2.prTitle = '';
        params2.prBody = '';

        $http.post(getEditionEndpoint('publishEdition'), params2, { responseType: 'json' }).then(function (res) {
            $scope.edition.mergeInfo.merging = false;
            if (!res.data.success) {
                $scope.edition.mergeInfo.error = true;
                return;
            }

            $scope.edition.mergeInfo.done = true;
            if (params2.type == 'prod') {
                $scope.edition.mergeInfo.showImport = true;
            } else if (params2.type == 'merge') {
                $timeout(function () {
                    $scope.closeEditionPopup();
                    $scope.prepareEdition();
                }, 3000);
            }

        }, function () {
            $scope.edition.mergeInfo.publishing = false;
            $scope.edition.mergeInfo.error = true;
        });
    }

    $scope.editionDoImport = function () {
        $scope.editionCancel();
        $scope.checkoutGit();
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

    $scope.getHistoryUntilSplit = function (commits) {
        var i = 0;
        for (i = 0; i < commits.length; i++) {
            if (commits[i].master) {
                break;
            }
        }
        return commits.slice(0, i);
    }

    $scope.editionHistory = function () {
        $scope.edition.history.show = true;
        $scope.edition.history.loading = true;
        $scope.editionGetHistory(function () {
            $scope.edition.history.loading = false;
            $scope.edition.history.editedHash = $scope.edition.oldVersion || $scope.edition.history.commits[0].hash
            $scope.edition.history.selected = $scope.edition.history.editedHash;
        });
    }

    $scope.editionGetHistory = function (callback) {
        var params2 = Object.assign({}, $scope.params);
        params2.action = 'historyEdition';

        function onFail() {
            $scope.edition.status = 'Error fetching history';
            $scope.ready = null;
            $scope.disableBtn = false;
        };

        function onRequestFail() {
            $scope.checkoutState = {
                status: 'danger',
                message: 'History request failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
        }

        $http.post(getEditionEndpoint('historyEdition'), params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res);
                return;
            }
            if (!$scope.edition.history) { $scope.edition.history = {}; }
            $scope.edition.history.commits = res.data.history;
            $scope.edition.history.masterCommits = res.data.historyMaster;
            $scope.edition.history.editorAdditional = res.data.editorAdditional;
            $scope.edition.history.masterAdditional = res.data.masterAdditional

            $scope.edition.history.commits.map(function (x) {
                var d = new Date(x.date * 1000);
                x.date = d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
            });

            $scope.edition.history.allCommits = [];
            var masterAdditionalCommits = $scope.getHistoryUntilSplit($scope.edition.history.masterCommits)
            masterAdditionalCommits.map(function (x) {
                x.fromMaster = true;
                var d = new Date(x.date * 1000);
                x.date = d.toLocaleDateString() + ' ' + d.toLocaleTimeString();
            });

            for (var i = 0; i < $scope.edition.history.commits.length; i++) {
                var c = $scope.edition.history.commits[i];
                if (c.master) {
                    $scope.edition.history.allCommits = $scope.edition.history.allCommits.concat(masterAdditionalCommits);
                }
                $scope.edition.history.allCommits.push(c);
            }

            if (callback) { callback(); }
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
            $scope.checkoutState = {
                status: 'danger',
                message: 'History request failed',
            };
            $scope.ready = null;
            $scope.disableBtn = false;
        }

        $http.post(getEditionEndpoint('checkoutHashEdition'), params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res);
                return;
            }
            $scope.closeEditionPopup();
            $scope.prepareEdition();
        }, onRequestFail);
    }

    $scope.editionRevert = function () {
        if (!confirm('Are you sure you want to revert to the last saved version?')) {
            return;
        }
        $scope.prepareEdition();
    }

    $scope.makeDiffUrl = function (hash, type) {
        var url = $scope.params.gitUrl;
        if ($scope.edition.isGitlab) {
            url += '/-';
        }
        if (type == 'commit') {
            return url + '/commit/' + hash;
        } else if (type == 'pr') {
            if ($scope.edition.isGitlab) {
                return url + '/merge_requests/new?merge_request%5Bsource_branch%5D=' + hash + '&merge_request%5Btarget_branch%5D=' + $scope.edition.masterBranch;
            } else {
                return url + '/compare/' + hash + '...' + $scope.edition.masterBranch + '?expand=1';
            }
        } else if (type == 'master') {
            return url + '/compare/' + hash + '...' + $scope.edition.masterBranch;
        } else {
            return url + '/compare/' + hash + '...' + type;
        }
    }

    $scope.makeDiffUI = function (hash, target, containerId) {
        $scope.edition.diff.status = null;
        $scope.edition.diff.selected = hash;
        for (var i = 0; i < $scope.edition.history.commits.length; i++) {
            if ($scope.edition.history.commits[i].hash == hash) {
                var selectedCommit = $scope.edition.history.commits[i];
                $scope.edition.diff.selected = selectedCommit.date + ' - ' + selectedCommit.message;
                break;
            }
        }
        $scope.edition.diff.target = target == 'master' ? 'production' : 'edition';
        var container = document.getElementById(containerId);
        container.innerHTML = '';

        var params2 = Object.assign({}, $scope.params);
        params2.action = 'diffEdition';
        params2.hash = hash;
        params2.target = target;

        function onFail() {
            $scope.edition.diff.status = 'error';
        };

        $scope.edition.diff.status = 'loading';
        $http.post(getEditionEndpoint('diffEdition'), params2, { responseType: 'json' }).then(function (res) {
            if (!res.data.success) {
                onFail(res);
                return;
            }

            if (!res.data.diff) {
                $scope.edition.diff.status = 'none';
                return;
            }

            $scope.edition.diff.status = 'success';

            // Extract modified files from git diff
            $scope.edition.diff.modified = [];
            var diffLines = res.data.diff.split('\n');
            for (var i = 0; i < diffLines.length; i++) {
                var line = diffLines[i];
                if (line.indexOf('diff --git') == 0) {
                    var file = line.split(' b/')[1];
                    $scope.edition.diff.modified.push(file);
                }
            }

            var cfg = { drawFileList: true, matching: 'lines' };
            var d2hu = new Diff2HtmlUI(container, res.data.diff, cfg);

            // Hack to make diff2html display the files summary by default
            d2hu.getHashTag = function() { return 'files-summary-show'; };

            d2hu.draw();
            d2hu.highlightCode();
        }, onFail);
    }

    $scope.getDiffModifiedStr = function () {
        if(!$scope.edition.diff || !$scope.edition.diff.modified) { return ''; }
        // If multiple files end in .md, display a warning
        $scope.edition.diff.modified = ['docs/intro.md', 'docs/abc.md', 'docs/def.md'];
        var mdCount = 0;
        for (var i = 0; i < $scope.edition.diff.modified.length; i++) {
            if ($scope.edition.diff.modified[i].endsWith('.md')) {
                mdCount++;
            }
        }
        if (mdCount > 1) {
            return 'Note: multiple Markdown files were changed in this set of modifications.';
        }
        return '';

    }

    $scope.closeDiffUI = function () {
        $scope.edition.diff = {};
    }

    $scope.closeEditionPopup = function () {
        $scope.edition.infoShow = false;
        $scope.edition.fileManager = null;
        if ($scope.edition.history) {
            $scope.edition.history.show = false;
        }
        if ($scope.edition.saveInfo) {
            $scope.edition.saveInfo.show = false;
        }
        if ($scope.edition.publishInfo) {
            $scope.edition.publishInfo.show = false;
        }
        if ($scope.edition.mergeInfo) {
            $scope.edition.mergeInfo.show = false;
        }
    }

    function editionFmUpdate(callback) {
        editionApi.refreshFileList(function (data) {
            var tree = { folders: [], files: [], name: $scope.edition.path };
            var treePointers = {};
            for (var i = 0; i < data.length; i++) {
                var pathSplit = data[i].split('/');
                var curTree = tree;
                var curPath = null;
                for (var j = 0; j < pathSplit.length - 1; j++) {
                    curPath = j > 0 ? curPath + '/' + pathSplit[j] : pathSplit[j];
                    if (!treePointers[curPath]) {
                        treePointers[curPath] = { folders: [], files: [], name: pathSplit[j], path: curPath };
                        curTree.folders.push(treePointers[curPath]);
                    }
                    curTree = treePointers[curPath];
                }
                curTree.files.push({ name: pathSplit[pathSplit.length - 1], path: data[i] });
            }
            $scope.edition.fileManager.filelist = data;
            $scope.edition.fileManager.root = tree;
            $scope.$apply(callback);
        });
    }
    $scope.editionFmUpdate = editionFmUpdate;

    $scope.editionFileManager = function () {
        if ($scope.edition.fileManager) {
            $scope.edition.fileManager = null;
            return;
        }
        $scope.edition.fileManager = {};
        editionApi.setupEditionApi(
            getEditorApiEndpoint(),
            $scope.edition.token,
            $scope.edition.session);
        editionFmUpdate();
    }

    $scope.imageCache = {};

    $scope.editionFmSelectedIsImage = function () {
        return $scope.edition.fileManager && $scope.edition.fileManager.selectedPath && /\.(gif|jpg|jpeg|tiff|png)$/i.test($scope.edition.fileManager.selectedPath);
    }

    var editionFmFilenameChanged = false;
    $scope.editionFmFilenameChange = function () {
        $scope.edition.fileManager.uploadSuccessful = false;
        editionFmFilenameChanged = true;
    }

    $scope.editionFmAddFile = function () {
        document.getElementById('edition-fm-add-input').click();
    }

    $scope.editionFmUpload = function () {
        if (!document.getElementById('edition-fm-add-input').files[0]) { return; }
        var filename = document.getElementById('edition-fm-add-input').files[0].name;
        if (!filename) { return; }
        while (editionApi.fileExists(filename)) {
            filename = '_' + filename;
        }

        editionApi.putFile(filename, document.getElementById('edition-fm-add-input').files[0], function () {
            $scope.edition.fileManager.uploadSuccessful = true;
            $scope.edition.fileManager.renamingPath = $scope.edition.fileManager.lastUploaded = $scope.edition.fileManager.newPath = filename;
            editionFmUpdate(function () {
                setTimeout(function () {
                    $('.edition-fm').scrollTop($('.treeview-item-uploaded')[0].offsetTop);
                }, 100);
            });
        });
    }

    $scope.initParams();
}]);

app.directive('treeview', function () {
    return {
        restrict: 'E',
        scope: {
            node: '='
        },
        templateUrl: 'templates/treeview.html' + config.urlArgs,
        link: function (scope, element, attrs) {
            scope.edition = scope.$parent.edition;
            scope.editionFmUpdate = scope.$parent.editionFmUpdate;
            scope.getClass = function (name) {
                if (/\.(gif|jpg|jpeg|tiff|png)$/i.test(name)) {
                    return 'fas fa-file-image';
                }
                if (/\.(md)$/i.test(name)) {
                    return 'fas fa-file-lines';
                }
                return 'fas fa-file';
            }

            scope.editionFmDownload = function (itemPath) {
                editionApi.getFileBlob(itemPath, function (blob) {
                    var link = document.createElement('a');
                    var filename = itemPath.split('/');
                    filename = filename[filename.length - 1];
                    link.download = filename;
                    link.href = URL.createObjectURL(blob);
                    link.click();
                    URL.revokeObjectURL(link.href);
                });
            }

            scope.editionFmDelete = function (itemPath) {
                if (!confirm("Are you sure you want to delete " + itemPath + "?")) {
                    return;
                }
                editionApi.deleteFile(itemPath, function () {
                    scope.editionFmUpdate();
                });
            }

            scope.editionFmRename = function (itemPath) {
                scope.edition.fileManager.lastUploaded = null;
                if (!scope.edition.fileManager.renamingPath || scope.edition.fileManager.renamingPath != itemPath) {
                    scope.edition.fileManager.renamingPath = itemPath;
                    scope.edition.fileManager.newPath = itemPath;
                    return;
                }
                if (!scope.edition.fileManager.newPath || scope.edition.fileManager.newPath == scope.edition.fileManager.renamingPath) {
                    scope.edition.fileManager.renamingPath = null;
                    return;
                }
                editionApi.getFileBlob(scope.edition.fileManager.renamingPath, function (blob) {
                    editionApi.putFile(scope.edition.fileManager.newPath, blob, function () {
                        editionApi.deleteFile(scope.edition.fileManager.renamingPath, function () {
                            scope.editionFmUpdate();
                        });
                    });
                });
            }

        }
    }
});
