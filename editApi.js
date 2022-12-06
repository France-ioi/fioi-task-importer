(function () {

    // TODO :: Maybe export all to a library?
    var editionApiParams = {
        baseApiUrl: null,
        token: null,
        sessionId: null
    }

    function setupEditionApi(baseApiUrl, token, sessionId) {
        editionApiParams = {
            baseApiUrl: baseApiUrl,
            token: token,
            sessionId: sessionId
        };
    }

    function apiRequest(path, opts, callback) {
        opts['headers'] = {
            'Authorization': 'Bearer ' + editionApiParams.token
        }
        opts['mode'] = 'cors';
        fetch(editionApiParams.baseApiUrl + editionApiParams.sessionId + '/' + path, opts).then(callback);
    }

    function getList(callback) {
        apiRequest('list', { method: 'GET' }, (data) => { data.json().then(callback); });
    }

    function fileExists(filename) {
        return fileList.indexOf(filename) != -1;
    }

    function getFile(filename, callback) {
        if (!fileExists(filename)) {
            callback(null);
            return;
        }
        apiRequest('file/' + filename, { method: 'GET' }, callback);
    }

    function putFile(filename, content, callback) {
        apiRequest('file/' + filename, { method: 'PUT', body: content }, callback);
    }

    function deleteFile(filename, callback) {
        if (!fileExists(filename)) {
            callback(true);
            return;
        }
        apiRequest('file/' + filename, { method: 'DELETE' }, callback);
    }

    var fileList = [];
    var imageCache = {};
    var fileCache = {};
    var blobCache = {};

    function refreshFileList(callback) {
        getList((data) => {
            fileList = data;
            imageCache = {};
            if (callback) { callback(data); }
        });
    }

    function getImageContent(filename, callback) {
        if (imageCache[filename]) {
            callback(imageCache[filename]);
            return;
        }
        getFile(filename, (data) => {
            if (data) {
                data.blob().then((blob) => {
                    // List of mime types by image extension
                    var img_mime = {
                        'jpg': 'image/jpeg',
                        'jpeg': 'image/jpeg',
                        'png': 'image/png',
                        'gif': 'image/gif',
                        'tiff': 'image/tiff',
                        'svg': 'image/svg+xml',
                    };
                    var mime = img_mime[filename.split('.').pop()];
                    var uri = URL.createObjectURL(blob.slice(0, blob.size, mime));
                    imageCache[filename] = uri;
                    callback(uri);
                });
            } else {
                callback(null);
            }
        });
    }

    function getFileContent(filename, callback) {
        if (fileCache[filename]) {
            callback(fileCache[filename]);
            return;
        }
        getFile(filename, (data) => {
            if (data) {
                data.text().then((text) => {
                    fileCache[filename] = text;
                    callback(text);
                });
            } else {
                callback(null);
            }
        });
    }

    function getFileBlob(filename, callback) {
        if (fileCache[filename]) {
            callback(fileCache[filename]);
            return;
        }
        getFile(filename, (data) => {
            if (data) {
                data.blob().then((blob) => {
                    blobCache[filename] = blob;
                    callback(blob);
                });
            } else {
                callback(null);
            }
        });
    }

    window.editionApi = {
        setupEditionApi: setupEditionApi,
        putFile: putFile,
        deleteFile: deleteFile,
        refreshFileList: refreshFileList,
        getImageContent: getImageContent,
        getFileContent: getFileContent,
        getFileBlob: getFileBlob
    }
})();