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
	} else if (state == 'static') {
		$('#state').html('static element was correctly imported!');
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

function showUrls(normalUrl, ltiUrl, tokenUrl) {
	$('#ltiUrl').attr('href', ltiUrl)
	    .html(ltiUrl);
	$('#normalUrl').attr('href', normalUrl)
	    .html(normalUrl);
    if (tokenUrl) {
        $('#tokenUrl').attr('href', tokenUrl);
        $('#tokenUrlP').show();
    } else {
        $('#tokenUrlP').hide();
    }
	$('#result').show();
}

function getInfos(svnUrl, svnRev, dirPath, success, error) {
	TaskProxyManager.getTaskProxy('taskIframe', function(task) {
		window.task = task;
		setState('getresources');
		var platform = new Platform(task);
		TaskProxyManager.setPlatform(task, platform);
		task.load({metadata:true, grader:true, solution:true, hints:true, editor:true, resources:true}, function() {
			task.getMetaData(function(metadata) {
		     	task.getResources(function(resources) {
		     		setState('saveresources');
		     		$.post('savesvn.php', {action: 'saveResources', resources: resources, metadata: metadata, svnRev: svnRev, svnUrl: svnUrl, dirPath: dirPath}, function(res) {
		     			if (res.success) {
							success(res.normalUrl, res.ltiUrl, res.tokenUrl);
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
    $('#taskWarning').text('');
    $('#taskWarning').hide();
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
        if (res.success && res.tasks) {
            recImport(res.tasks, newValues.svnUrl, res.revision);
        } else {
    		setState('error', res.error);
    		return;
    	}
    }, 'json').fail(function() {
    	setState('error', 'server request failed');
    });
};

function copyResults(name, urls) {
    var newHtml = '<hr /><p>'+name+': '+$("#state").html()+'</p>';
    var warning = $('#taskWarning').text();
    if(warning != '') {
        newHtml += '<p>'+warning+'</p>';
        $('#taskWarning').text('');
    }
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

    if (curTask.isstatic) {
        setState('static');
        showUrls(curTask.normalUrl, curTask.ltiUrl, curTask.tokenUrl);
        copyResults(curTask.ID, true);
        recImport(tasks, svnUrl, revision);
        return;
    } else if (curTask.ltiUrl) {
        setState('done');
    	showUrls(curTask.normalUrl, curTask.ltiUrl, curTask.tokenUrl);
    	return;
    }

    $('#curTask').html(curTask.ID+': ');
    setState('load');
    if (curTask.warnPaths) {
        $("#taskWarning").text('Warning: possible error in _common paths.');
        $("#taskWarning").show();
    }
    $('#taskIframe').attr('src',curTask.url);
    console.error(curTask.url);
    window.setTimeout(function() {
    	getInfos(curTask.svnUrl, revision, curTask.dirPath,
            function(normalUrl, ltiUrl, tokenUrl) {
                showUrls(normalUrl, ltiUrl, tokenUrl);
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
