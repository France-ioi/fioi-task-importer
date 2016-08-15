var svnImportTasks = [];

function setState(state, comment) {
	if (state == 'normal') {
		$('#state').html('');
	} else if (state == 'savesvn') {
		$('#state').html('checking out svn...');
	} else if (state == 'load') {
		$('#state').html('loading task...');
	} else if (state == 'getresources') {
		$('#state').html('get task resources...');
	} else if (state == 'saveresources') {
		$('#state').html('save resources in db...');
	} else if (state == 'error') {
		$('#state').html('error: '+comment);
	} else if (state == 'deleting') {
		$('#state').html('deleting svn temporary dir...');
	} else if (state == 'success') {
		$('#state').html('task was correctly imported!');
	}
}

function finishImport(ID, success, error) {
	setState('deleting');
	$.post('savesvn.php', {action: 'deletedirectory', ID: ID}, function(res) {
		if (res.success) {
            success();
		} else {
            error(res.error);
		}
	}).fail(function() {
        error('');
    });
}

function showUrls(normalUrl, ltiUrl) {
	$('#ltiUrl').attr('href', ltiUrl);
	$('#ltiUrl').html(ltiUrl);
	$('#normalUrl').attr('href', normalUrl)
	$('#normalUrl').html(normalUrl);
	$('#result').show();
}

function getInfos(svnUrl, svnRev, success, error) {
	TaskProxyManager.getTaskProxy('taskIframe', function(task) {
		window.task = task;
		setState('getresources');
		var platform = new Platform(task);
		TaskProxyManager.setPlatform(task, platform);
		task.load({metadata:true, grader:true, solution:true, hints:true, editor:true, resources:true}, function() {
			task.getMetaData(function(metadata) {
		     	task.getResources(function(resources) {
		     		setState('saveresources');
		     		$.post('savesvn.php', {action: 'saveResources', resources: resources, metadata: metadata, svnRev: svnRev, svnUrl: svnUrl}, function(res) {
		     			if (res.success) {
							success(res.normalUrl, res.ltiUrl);
		     			} else {
                            error(res.error);
		     			}
		     		}).fail(function() {
				    	error('unable to load/save resources');
				    });
		     	}, error);
            }, error);
        }, error);
  	}, true);
}

function saveSvn() {
	$('#result').hide();
	$('#recResults').html('');
    $('#curTask').html('');
	setState('savesvn');
    var values = $('#svn_form').serializeArray();
    var newValues = {};
    for (var i = 0; i < values.length; i++) {
    	newValues[values[i].name] = values[i].value;
    }
    newValues.action = 'checkoutSvn';
    $.post('savesvn.php', newValues, function(res) {
    	if (!res.success) {
    		setState('error', res.error);
    		return;
    	}
/*    	if (res.ltiUrl) {
    		showUrls(res.normalUrl, res.ltiUrl);
    		return;
    	}*/
        if (res.url) {
            $('#curTask').html(res.ID+': ');
            setState('load');
        	$('#taskIframe').attr('src',res.url);
        	console.error(res.url);
        	window.setTimeout(function() {
        		getInfos(newValues.svnUrl, res.revision,
                    function(normalUrl, ltiUrl) {
                        showUrls(normalUrl, ltiUrl);
                        finishImport(res.ID,
                            function () {setState('success')},
                            function (errormsg) {setState('error', errormsg)}
                        );
                    },
        		    function (errormsg) {setState('error', errormsg)});
        	}, 2000);
        } else if (res.tasks) {
            recImport(res.tasks, newValues.svnUrl, res.revision);
        }
    }, 'json').fail(function() {
    	setState('error');
    });
};

function copyResults(name, urls) {
    var newHtml = '<hr /><p>'+name+': '+$("#state").html()+'</p>';
    if(urls) {
        newHtml += $('#result').html();
        $('#result').hide();
    }
    $('#recResults').prepend(newHtml);
    $('#recResults a').removeAttr('id');
};

function recImport(tasks, svnUrl, revision) {
    if (tasks.length == 0) {
        $('#curTask').html('');
        setState('normal');
        return;
    }
    var curTask = tasks.shift();
    $('#curTask').html(curTask.ID+': ');
    setState('load');
    $('#taskIframe').attr('src',curTask.url);
    console.error(curTask.url);
    window.setTimeout(function() {
    	getInfos(curTask.svnUrl, revision,
            function(normalUrl, ltiUrl) {
                showUrls(normalUrl, ltiUrl);
                finishImport(curTask.ID,
                    function() {
                        setState('success');
                        copyResults(curTask.ID, true);
                        recImport(tasks, svnUrl, revision);
                    },
                    function(errormsg) {
                        setState('error', (errormsg ? errormsg : "couldn't clean up") + ' (<a href="' + curTask.url + '">index.html</a>)');
                        copyResults(curTask.ID, true);
                        recImport(tasks, svnUrl, revision);
                    });
            },
    	    function(errormsg) {
                setState('error', (errormsg ? errormsg : "couldn't load/save resources") + ' (<a href="' + curTask.url + '">index.html</a>)');
                copyResults(curTask.ID, false);
                recImport(tasks, svnUrl, revision);
            });
    }, 2000);
};
