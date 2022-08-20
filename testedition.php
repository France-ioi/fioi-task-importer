<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Test edition</title>
    <script type="text/javascript" src="bower_components/jschannel/src/jschannel.js"></script>
    <script type="text/javascript">
        function openEditor() {
            var url = new URL(document.getElementById('url').value);
            url.searchParams.set('token', 'testtoken');
            url.searchParams.set('session', 'testsession');
            document.getElementById('editor').src = url;

            setTimeout(bindJschannel, 2000);
        }

        function getHeight() {
            window.chan.call({
                method: 'getHeight',
                params: {},
                success: function (height) {
                    document.getElementById('height').innerHTML = height;
                }
            });
        }
            
        function bindJschannel() {
            window.chan = Channel.build({
                window: document.getElementById('editor').contentWindow,
                origin: '*',
                scope: 'edition'
            });
            window.chan.call({
                method: 'getMetaData',
                params: {},
                success: function (data) {
                    document.getElementById('metadata').innerHTML = JSON.stringify(data, null, 2);
                }
            });
            setInterval(getHeight, 1000);
        }
    </script>
</head>
<body>
    <div style="display: flex; flex-direction: column; height: 98vh;">
        <div>
            <input type="text" id="url" style="width: 600px;">
            <button id="go" onclick="openEditor();">Open editor</button>
        </div>
        <div>
            <p>Height received : <span id="height"></span></p>
            <p>Metadata received : <span id="metadata"></span></p>
        </div>
        <div style="flex-grow: 1;">
            <iframe id="editor" src="" width="100%" height="100%">
        </div>
    </div>
</body>