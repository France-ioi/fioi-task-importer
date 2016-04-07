function setState(state, comment) {
	if (state == 'normal') {
		$('#state').html('');
	} else if (state == 'savesvn') {
		$('#state').html('checking out svn...');
	} else if (state == 'getresources') {
		$('#state').html('get task resources...');
	} else if (state == 'saveresources') {
		$('#state').html('save resources in db...');
	} else if (state == 'saveresources') {
		$('#state').html('import success!');
	} else if (state == 'error') {
		$('#state').html('error: '+comment);
	}
}

function getInfos() {
	TaskProxyManager.getTaskProxy('taskIframe', function(task) {
		window.task = task;
		setState('getresources');
		var platform = new Platform(task);
		TaskProxyManager.setPlatform(task, platform);
		task.load({metadata:true, grader:true, solution:true, hints:true, editor:true, resources:true}, function() {
			task.getMetaData(function(metadata) {
		     	task.getResources(function(resources) {
		     		setState('saveresources');
		     		$.post('savesvn.php', {action: 'saveResources', resources: resources, metadata: metadata}, function(res) {
		     			if (res.success) {
		     				setState('success');
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
    	$('#taskIframe').attr('src',res.dir);
    	window.setTimeout(function() {
    		getInfos();
    	}, 0);
    }, 'json').fail(function() {
    	setState('error');
    });
};