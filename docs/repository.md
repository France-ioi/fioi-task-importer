# Repository structure

Svnimport expects a certain structure in the repository in order to function properly.

* [root]
    * \_common: files common for the whole repository; updated on each task import
    * OrganizationX: files for the organization X
        * \_local\_common: files common for the organization X; updated on each import of a task from this folder
        * task1
            * index.html
        * subfolder
            * task2
                * index.html
    * OrganizationY

Note that no task may be put in the root folder of the repository (the one containing `_common`), as svnimport won't work.

## Shared resources

You can share resources between various tasks of your organization by creating a folder `_local_common` in your organization folder.

### Use in static tasks

Import the file by specifying the URL, going back to the [root] of the repository. For instance, if your `index.html` is in `[root]/OrganizationX/task1/index.html`, you can add a javascript file with:
```
<script src="../../OrganizationX/_local_common/script.js"></script>
```

### Use in taskgrader tasks

If you need to specify a path to the `_local_common` folder, specify it going from the `ROOT_PATH` of the repository, instead of the `TASK_PATH`, as such: `$ROOT_PATH/OrganizationX/_local_common/lib.h`.
