#!/bin/sh
set -e

composer install
npm install

exec docker-php-entrypoint "$@"
