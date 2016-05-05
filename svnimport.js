function setState(state, comment) {
	if (state == 'normal') {
		$('#state').html('');
	} else if (state == 'savesvn') {
		$('#state').html('checking out svn...');
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

function finishImport(ID) {
	setState('deleting');
	$.post('savesvn.php', {action: 'deletedirectory', ID: ID}, function(res) {
		if (res.success) {
			setState('success');	     				
		} else {
			setState('error', res.error);
		}
	}).fail(function() {
		setState('error');
    });
}

function showUrls(normalUrl, ltiUrl) {
	$('#ltiUrl').attr('href', ltiUrl);
	$('#ltiUrl').html(ltiUrl);
	$('#normalUrl').attr('href', normalUrl)
	$('#normalUrl').html(normalUrl);
	$('#result').show();
}

function getInfos(svnUrl, svnRev, success) {
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
		     				showUrls(res.normalUrl, res.ltiUrl);
							success();		     				
		     			} else {
		     				setState('error', res.error);
		     			}
		     		}).fail(function() {
				    	setState('error');
				    });
		     	}, function(errormsg) {setState('error', errormsg)});
		 	}, function(errormsg) {setState('error', errormsg)});
		}, function(errormsg) {setState('error', errormsg)});
  	}, true);
}

function saveSvn() {
	$('#result').hide();
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
    	if (res.ltiUrl) {
    		showUrls(res.normalUrl, res.ltiUrl);
    		return;
    	}
    	$('#taskIframe').attr('src',res.url);
    	console.error(res.url);
    	window.setTimeout(function() {
    		getInfos(newValues.svnUrl, res.revision, function() {
    			finishImport(res.ID);
    		});
    	}, 2000);
    }, 'json').fail(function() {
    	setState('error');
    });
};