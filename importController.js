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
                  return '/i18n/'+lng+'/'+ns+'.json';
                }
  };
window.i18next.init(i18nextOpts);
window.i18next.on('initialized', function (options) {
  window.i18nextOptions = options;
});


var app = angular.module('svnImport', ['jm.i18next']);

app.controller('importController', ['$scope', '$http', '$timeout', '$i18next', function($scope, $http, $timeout, $i18next) {
    $scope.lang = $i18next.options.lng;
    $scope.checkoutState = '';
    $scope.logList = [];

    $scope.tasksRemaining = [];
    $scope.curRev = null;
    $scope.curTaskUrl = '';
    $scope.curTask = null;
    $scope.curLog = null;
    $scope.curID = null;
    $scope.curData = [];

    $scope.changeLang = function() {
        $i18next.changeLanguage($scope.lang);
    };

    $scope.checkoutSvn = function() {
        // Checkout the SVN and get the list of tasks
        $scope.checkoutState = 'checkout_inprogress';
        $scope.checkoutMsg = '';
        $scope.tasksRemaining = [];
        $scope.curTaskUrl = '';

        if($scope.curID) {
            $scope.logList[0].state = 'task_cancelled';
            $scope.curLog.state = 'file_cancelled';
            $scope.curID = null;
        }

        // Unhighlight old tasks
        for(var i=0; i < $scope.logList.length; i++) {
            $scope.logList[i].active = false;
        }

        var values = $('#svn_form').serializeArray();
        var newValues = {};
        for (var i = 0; i < values.length; i++) {
            newValues[values[i].name] = values[i].value;
        }
        newValues.action = 'checkoutSvn';

        // Checkout the task and get data
        $http.post('savesvn.php', newValues, {responseType: 'json'}).then(function(res) {
            if (res.data.success && res.data.tasks) {
                $scope.checkoutState = 'checkout_import';
                $scope.tasksRemaining = res.data.tasks;
                $scope.curRev = res.data.revision;
                $scope.recImport();
            } else {
                $scope.checkoutState = 'checkout_error';
                $scope.checkoutMsg = res.data.error;
            }}, function() {
                $scope.checkoutState = 'checkout_request_failed';
            });
    };

    $scope.recImport = function() {
        // Start import of one task
        var curTask = $scope.tasksRemaining.shift();
        if(!curTask) {
            $scope.checkoutState = 'checkout_finished';
            return;
        }
        $scope.curTask = curTask;
        $scope.curData = [];
        $scope.logList.unshift({
            url: curTask.svnUrl,
            svnRev: $scope.curRev,
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
            isStatic: curFile.isStatic
            };
        $scope.curLog = curLog;
        $scope.logList[0].files.push(curLog);
        if(curFile.isStatic) {
            // Static file, we display it then continue
            curLog.state = 'file_static';
            curLog.url = curTask.staticUrl + curFile.filename;
            $scope.recTaskImport();
        } else {
            // TaskPlatform file, we fetch its resources
            curLog.state = 'file_loading';
            curLog.warnPaths = curFile.warnPaths;
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
            }
            foundLangs.push(newLang);

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
        $http.post('savesvn.php', {
            action: 'saveResources',
            data: $scope.curData,
            svnRev: $scope.curRev,
            svnUrl: $scope.curTask.svnUrl,
            dirPath: $scope.curTask.dirPath
            }).then(function(res) {
                // Success!
                if(res.data.success) {
                    log.state = 'task_success';
                    log.normalUrl = res.data.normalUrl;
                    log.ltiUrl = res.data.ltiUrl;
                    log.tokenUrl = res.data.tokenUrl;
                } else {
                    log.state = 'task_save_error';
                }
                $scope.recImport();
            }, function() {
                // Error
                log.state = 'task_save_error';
                $scope.recImport();
            });
    };
}]);
