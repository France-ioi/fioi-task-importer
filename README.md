# fioi-task-importer

**Read the documentation online on [GitHub pages](http://france-ioi.github.io/fioi-task-importer)**, or alternatively in the `docs/` folder.

This repository provides a small tool to import a svn task into a database, through the following steps:

- user provides a svn url (with revision) and his credentials in an html form
- a php script checks out the revision
- then pushes it to a public S3 url with temporary name, and removes them from the local drive
- serves the S3 files in an iframe
- fetches all resources through Bebras installation API
- calls another php script with the resources, installing the resources in the database and private S3
- removes the files from S3

## Requirement

You must have a database in the same format as the one of (TaskPlatform)[https://github.com/France-ioi/TaskPlatform]. You must also set up two AWS S3 buckets. You also need [bower](http://bower.io/) and [composer](https://getcomposer.org/).

## Installation

Clone this repository and run `composer install` then `bower install`.
