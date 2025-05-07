const {createApp} = Vue;

const language = 'en';

async function fetchTranslations() {
    const url = 'i18n/' + language + '/svnimport.json';
    try {
        const response = await fetch(url);

        return await response.json();
    } catch (error) {
        console.error('Error loading translations: ', error);
    }
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

async function main() {
    const translations = await fetchTranslations();

    createApp({
        data() {
            return {
                repoType: 'svn',
                mainDivClass: '',
                defaultParams: null,
                svnBaseUrl: config.svnBaseUrl,
                params: {
                    svnUrl: config.svnExampleUrl,
                    localeEn: 'default',
                    theme: 'none',
                    acceptMovedTasks: false,
                    username: '',
                    password: '',
                    token: null,
                },
                loginRequired: true,
                disableBtn: false,
                checkoutState: null,
                checkoutMsg: null,
                translations,
            };
        },
        mounted() {
            this.initParams();
        },
        methods: {
            switchType(newType) {
                this.repoType = newType;
            },
            initParams() {
                if (localStorageGetItem('repoType')) {
                    this.repoType = localStorageGetItem('repoType');
                }
                if (localStorageGetItem('gitUrl')) {
                    this.params.gitUrl = localStorageGetItem('gitUrl');
                }

                if (localStorageGetItem('defaultParams')) {
                    this.defaultParams = JSON.parse(localStorageGetItem('defaultParams'));
                    for (var opt in this.defaultParams) {
                        this.params[opt] = this.defaultParams[opt];
                    }
                }

                if (localStorageGetItem('username')) {
                    this.params.username = localStorageGetItem('username');
                    if (localStorageGetItem('password')) {
                        this.params.password = localStorageGetItem('password');
                    }
                    this.params.remember = true;
                }

                if (localStorageGetItem('gitUsername')) {
                    this.params.gitUsername = localStorageGetItem('gitUsername');
                    if (localStorageGetItem('gitPassword')) {
                        this.params.gitPassword = localStorageGetItem('gitPassword');
                    }
                    this.params.gitRemember = true;
                }
            },
            async check() {
                console.log('init check');

                const taskList = await this.checkoutSvn();

                console.log('taskList', taskList);
            },
            async checkoutSvn() {
                // Checkout the SVN and get the list of tasks
                if ((!this.params.username || !this.params.password) && !this.params.token) {
                    this.loginRequired = true;
                    if (jschannel) {
                        jschannel.notify({method: 'syncError'});
                    }
                    return;
                }

                // Save credentials
                if (this.params.remember && !this.params.token) {
                    localStorageSetItem('username', this.params.username);
                    localStorageSetItem('password', this.params.password);
                }

                // Filter path for double slashes
                this.params.svnUrl = this.params.svnUrl.replace(/\/+/g, '/');
                if (this.params.svnUrl[0] === '/') {
                    this.params.svnUrl = this.params.svnUrl.substr(1);
                }

                this.initCheckout();
                this.ready = {checkout: false, local_common: false};

                let tasks;

                // Checkout the task and get data
                const params1 = Object.assign({}, this.params);
                params1.action = 'checkoutSvn';

                try {
                    const res = await fetch('savesvn.php', {
                        method: 'POST',
                        body: JSON.stringify(params1),
                    });
                    const data = await res.json();

                    if (data.success && data.tasks) {
                        tasks = data.tasks;
                    } else {
                        this.checkoutFail(res);
                    }
                } catch (e) {
                    this.checkoutRequestFail();
                }

                const params2 = {
                    ...this.params,
                    action: 'updateLocalCommon',
                };

                try {
                    const res = await fetch('savesvn.php', {
                        method: 'POST',
                        body: JSON.stringify(params2),
                    });
                    const data = await res.json();

                    if (!data.success) {
                        this.checkoutFail(res);
                    }
                } catch (e) {
                    this.checkoutRequestFail();
                }

                return tasks;
            },
            initCheckout() {
                this.checkoutState = {
                    status: 'info',
                    message: 'checkout_inprogress',
                };
                this.tasksRemaining = [];
                this.loginRequired = false;
                this.disableBtn = true;
            },
            checkoutFail() {
                this.checkoutState = {
                    status: 'danger',
                    message: 'checkout_error',
                };
                this.ready = null;
                this.disableBtn = false;
            },
            checkoutRequestFail() {
                this.checkoutState = {
                    status: 'danger',
                    message: 'checkout_request_failed',
                };
                this.ready = null;
                this.disableBtn = false;
            },
        },
    }).mount('#app');
}

main();
