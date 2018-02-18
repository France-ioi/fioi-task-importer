# Embed options

Svnimport can be embedded in an iframe for semi-automated import of tasks.

## Options

Options can be specified as GET options in the svnimport URL. It allows for an automatic start of a task import when specifying `autostart=1`. All these options match with the fields in the svnimport interface.

To start automatically an import, these two options are required :

* `path=[string]` : SVN path relative to the base path defined in the configuration
* `autostart=1` : start automatically the import

These other options are optional, if they are not specified they will default to user-saved values or svnimport defaults :

* `revision=[string]` : SVN revision to import, empty to import HEAD
* `recursive=1` : import tasks recursively
* `noimport=1` : do not reimport tasks, only generate links to current version in production
* `localeEn=[string]` : english locale (either `default`, `gb` or `us`)
* `theme=[string]` : LTI display theme (either `none` or `telecom`)
* `display=[string]` : svnimport display mode (either `full` or `frame`, see below)

These two options are also supported but it is strongly discouraged to use them for security reasons :

* `username` : SVN username
* `password` : SVN password

Any options specified in the URL will supersede user-saved options.

## Frame display mode

If you specify `display=frame` in the URL, svnimport will use a display template intended for use in embeds. This template only displays the options and results of the import. It allows the user to switch to the full interface if needed.

If the import is not automatically started, or if it fails due to incorrect SVN path or credentials, it will allow the user to change these parameters.
