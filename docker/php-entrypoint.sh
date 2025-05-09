#!/bin/sh
set -e

composer install
npm install
mkdir -p files/zips

exec docker-php-entrypoint "$@"
