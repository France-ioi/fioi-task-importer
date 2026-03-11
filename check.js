const { useState } = React;

const language = 'en';

async function fetchTranslations() {
    const url = 'i18n/' + language + '/svnimport.json';
    try {
        const response = await fetch(url);
        return await response.json();
    } catch (error) {
        console.error('Error loading translations: ', error);
        return {};
    }
}

function localStorageGetItem(key) {
    try {
        return localStorage.getItem(key);
    } catch (e) {
        return null;
    }
}

function localStorageSetItem(key, value) {
    try {
        return localStorage.setItem(key, value);
    } catch (e) {
        return;
    }
}

function getInitialRepoType() {
    return localStorageGetItem('repoType') || 'svn';
}

function getInitialParams() {
    const params = {
        svnUrl: config.svnExampleUrl,
        localeEn: 'default',
        theme: 'none',
        acceptMovedTasks: false,
        username: '',
        password: '',
        token: null,
    };

    if (localStorageGetItem('gitUrl')) {
        params.gitUrl = localStorageGetItem('gitUrl');
    }
    if (localStorageGetItem('defaultParams')) {
        const defaultParams = JSON.parse(localStorageGetItem('defaultParams'));
        for (var opt in defaultParams) {
            params[opt] = defaultParams[opt];
        }
    }
    if (localStorageGetItem('username')) {
        params.username = localStorageGetItem('username');
        if (localStorageGetItem('password')) {
            params.password = localStorageGetItem('password');
        }
        params.remember = true;
    }
    if (localStorageGetItem('gitUsername')) {
        params.gitUsername = localStorageGetItem('gitUsername');
        if (localStorageGetItem('gitPassword')) {
            params.gitPassword = localStorageGetItem('gitPassword');
        }
        params.gitRemember = true;
    }
    return params;
}

function App({ translations }) {
    const t = key => translations[key] || key;

    const [repoType, setRepoType] = useState(getInitialRepoType);
    const [params, setParams] = useState(getInitialParams);
    const [loginRequired, setLoginRequired] = useState(true);
    const [disableBtn, setDisableBtn] = useState(false);
    const [checkoutState, setCheckoutState] = useState(null);
    const [checkoutMsg] = useState(null);
    const [tasksRemaining, setTasksRemaining] = useState([]);

    function updateParam(key, value) {
        setParams(prev => ({ ...prev, [key]: value }));
    }

    async function check() {
        console.log('init check');
        const taskList = await checkoutSvn();
        console.log('taskList', taskList);
    }

    async function checkoutSvn() {
        if ((!params.username || !params.password) && !params.token) {
            setLoginRequired(true);
            if (typeof jschannel !== 'undefined' && jschannel) {
                jschannel.notify({ method: 'syncError' });
            }
            return;
        }

        if (params.remember && !params.token) {
            localStorageSetItem('username', params.username);
            localStorageSetItem('password', params.password);
        }

        const cleanedSvnUrl = params.svnUrl.replace(/\/+/g, '/').replace(/^\//, '');
        const cleanedParams = { ...params, svnUrl: cleanedSvnUrl };
        setParams(cleanedParams);

        setCheckoutState({ status: 'info', message: 'checkout_inprogress' });
        setTasksRemaining([]);
        setLoginRequired(false);
        setDisableBtn(true);

        let tasks;

        try {
            const res = await fetch('savesvn.php', {
                method: 'POST',
                body: JSON.stringify({ ...cleanedParams, action: 'checkoutSvn' }),
            });
            const data = await res.json();
            if (data.success && data.tasks) {
                tasks = data.tasks;
            } else {
                setCheckoutState({ status: 'danger', message: 'checkout_error' });
                setDisableBtn(false);
                return;
            }
        } catch (e) {
            setCheckoutState({ status: 'danger', message: 'checkout_request_failed' });
            setDisableBtn(false);
            return;
        }

        try {
            const res = await fetch('savesvn.php', {
                method: 'POST',
                body: JSON.stringify({ ...cleanedParams, action: 'updateLocalCommon' }),
            });
            const data = await res.json();
            if (!data.success) {
                setCheckoutState({ status: 'danger', message: 'checkout_error' });
                setDisableBtn(false);
            }
        } catch (e) {
            setCheckoutState({ status: 'danger', message: 'checkout_request_failed' });
            setDisableBtn(false);
        }

        return tasks;
    }

    return (
        <div className="container-fluid">
            <h1>Task checker</h1>
            <p>Use this page to check that a task or a task folder is valid.</p>
            <form className="form-inline" role="form" id="svn_form">
                <div className="row">
                    <div id="form_cont" className="col-xs-12 col-lg-6">
                        <div className="panel panel-primary">
                            <div className="panel-heading">
                                <h4>{t('panel_svn')}</h4>
                            </div>
                            <div className="panel-body">
                                <ul className="nav nav-tabs nav-justified">
                                    <li className={repoType === 'svn' ? 'active' : ''}>
                                        <a href="#" onClick={e => { e.preventDefault(); setRepoType('svn'); }}>SVN</a>
                                    </li>
                                    <li className={repoType === 'git' ? 'active' : ''}>
                                        <a href="#" onClick={e => { e.preventDefault(); setRepoType('git'); }}>Git</a>
                                    </li>
                                </ul>

                                {repoType === 'svn' && (
                                    <div>
                                        <div className="form-group form-group-full">
                                            <label htmlFor="svnUrl">{t('label_svnurl')}</label><br/>
                                            <div className="input-group">
                                                <span className="input-group-addon" style={{fontWeight: 'bold'}}>{config.svnBaseUrl}</span>
                                                <input type="text" className="form-control" id="svnUrl" name="svnUrl"
                                                    value={params.svnUrl || ''}
                                                    onChange={e => updateParam('svnUrl', e.target.value)} />
                                            </div>
                                        </div><br/>
                                        <div className="form-group">
                                            <label htmlFor="svnRev">{t('label_svnrev')}</label><br/>
                                            <input type="text" className="form-control" name="svnRev"
                                                value={params.svnRev || ''}
                                                onChange={e => updateParam('svnRev', e.target.value)} />
                                        </div><br/>
                                        {!params.token && (<>
                                            <div className={'form-group' + (loginRequired && !params.username ? ' has-error' : '')}>
                                                <label className="control-label">{t('label_username')}</label><br/>
                                                <input type="text" className="form-control" name="username"
                                                    value={params.username || ''}
                                                    onChange={e => updateParam('username', e.target.value)} />
                                            </div><br/>
                                            <div className={'form-group' + (loginRequired && !params.password ? ' has-error' : '')}>
                                                <label className="control-label">{t('label_password')}</label><br/>
                                                <input type="password" className="form-control" name="password"
                                                    value={params.password || ''}
                                                    onChange={e => updateParam('password', e.target.value)} />
                                            </div><br/>
                                            <div className="checkbox">
                                                <input type="checkbox" name="remember"
                                                    checked={params.remember || false}
                                                    onChange={e => updateParam('remember', e.target.checked)} />
                                                {' '}<span>{t('label_remember')}</span>
                                            </div><br/>
                                        </>)}
                                    </div>
                                )}

                                {repoType === 'git' && (
                                    <div>
                                        <div className="form-group form-group-full">
                                            <label htmlFor="gitUrl">{t('label_giturl')}</label><br/>
                                            <input type="text" className="form-control" id="gitUrl" name="gitUrl"
                                                placeholder="https://github.com/..."
                                                value={params.gitUrl || 'https://github.com/France-ioi/bebras-tasks'}
                                                onChange={e => updateParam('gitUrl', e.target.value)} />
                                        </div><br/>
                                        <div className="form-group form-group-full">
                                            <label htmlFor="gitPath">{t('label_gitpath')}</label><br/>
                                            <input type="text" className="form-control" name="gitPath"
                                                placeholder="path/to/task/"
                                                value={params.gitPath || 'module_testing/test-responsive-interface'}
                                                onChange={e => updateParam('gitPath', e.target.value)} />
                                        </div><br/>
                                        {!params.token && (<>
                                            <div className="form-group form-group-full">
                                                <label htmlFor="gitUsername">{t('label_git_username')}</label><br/>
                                                <input type="text" className="form-control" id="gitUsername" name="gitUsername"
                                                    value={params.gitUsername || ''}
                                                    onChange={e => updateParam('gitUsername', e.target.value)} />
                                            </div><br/>
                                            <div className="form-group form-group-full">
                                                <label htmlFor="gitPassword">{t('label_git_password')}</label><br/>
                                                <input type="password" className="form-control" id="gitPassword" name="gitPassword"
                                                    value={params.gitPassword || ''}
                                                    onChange={e => updateParam('gitPassword', e.target.value)} />
                                            </div><br/>
                                        </>)}
                                        <div className="checkbox">
                                            <input type="checkbox" name="gitRemember"
                                                checked={params.gitRemember || false}
                                                onChange={e => updateParam('gitRemember', e.target.checked)} />
                                            {' '}<span>{t('label_remember')}</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
                <input type="submit" className="btn btn-primary"
                    onClick={e => { e.preventDefault(); check(); }}
                    disabled={disableBtn}
                    value="Check" />
            </form>
            <hr/>
            {checkoutState && (
                <div className={'alert alert-' + checkoutState.status}>
                    {checkoutState.status && checkoutState.status !== 'info' && (
                        <h4 className="alert-heading">{t('checkout_status_' + checkoutState.status)}</h4>
                    )}
                    <b>
                        <span>{t(checkoutState.message)}</span>
                        {checkoutMsg && <span>{t(checkoutMsg)}</span>}
                    </b>
                    {tasksRemaining.length > 0 && (
                        <span><br/>{tasksRemaining.length} <span>{t('display_tasks_left')}</span></span>
                    )}
                </div>
            )}
        </div>
    );
}

async function main() {
    const translations = await fetchTranslations();
    const root = ReactDOM.createRoot(document.getElementById('app'));
    root.render(<App translations={translations} />);
}

main();
