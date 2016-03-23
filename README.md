# fioi-task-importer

This repository provides a small tool to import a svn task into a database, through the following steps:

- user provides a svn url (with revision) and his credentials in an html form
- a php script checks out the revision
- then pushes it to a public S3 url with temporary name, and removes them from the local drive
- serves the S3 files in an iframe
- fetches all resources through Bebras installation API
- calls another php script with the resources, installing the resources in the database and private S3
- removes the files from S3
