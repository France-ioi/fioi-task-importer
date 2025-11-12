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

You must have a database in the same format as the one of [TaskPlatform](https://github.com/France-ioi/TaskPlatform). You must also set up two AWS S3 buckets.

## Docker installation (new)

Install Docker and Docker-Compose on your computer.

Then do:

```
docker-compose build --build-arg UID=$(id -u) --build-arg GID=$(id -g)
docker-compose up -d
```

Install mkcert and generate a SSL certificate (replace localhost by
your custom local domain if you prefer):

```
mkcert -install
mkcert -cert-file docker/certs/ssl.pem -key-file docker/certs/ssl-key.pem localhost
```

You can access the TaskImporter on https://localhost:8009/import.php (replace localhost by your local domain
and add it to `/etc/hosts` if you wish to use one)

Don't forget to create a `config_local.php` file from the `config.php`!
The database must be outside Docker (for simplicity reasons, because
this database must often be used by multiple different projects).
Use `$config->db->host = 'host.docker.internal';` to connect your TaskImporter
to your local machine MySQL database.

## Vanilla installation (old)

You need [bower](http://bower.io/) and [composer](https://getcomposer.org/).

Clone this repository and run `composer install` then `npm install`.


Install PHP 5.6:

```
sudo apt-add-repository -y ppa:ondrej/php
sudo apt update
sudo apt install php5.6 php5.6-fpm php5.6-xml php5.6-mbstring php5.6-curl php5.6-mysql

```

Install PHP svn extension:

/!\ This extension does not work with PHP >= 7. You have
to ensure that you use PHP 5.

```
sudo apt-get install subversion php-dev
sudo pecl install svn
```

You may have to configure PECL to use the PHP 5 configuration and executables.
Use the following commands and change the parameters to fit your settings:

```
sudo pecl config-set php_ini /etc/php.ini
sudo pecl config-set php_bin /usr/bin/php5
sudo pear config-set php_suffix 5
```

## Solution checks

You can use these SVN paths to test the evaluation of correct solutions :
- `Algorea/algorea_training_2/11_variable_08_sokoban` for client-side
- `Tezos/testNewFormat` for server-side