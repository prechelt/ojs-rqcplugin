#!/usr/bin/env bash
# created 2018-12-04, Lutz Prechelt
# Driver for calling OJS's phpunit with some env set up

export DUMMY_PDF=z/testing/test-upload1.pdf  # Path to dummy PDF file to use for document uploads
export DUMMY_ZIP=z/testingtest-upload1.zip  # Path to dummy ZIP file to use for document uploads
export BASEURL="http://localhost:8004/"  # Base URL, excluding index.php
export DBHOST=localhost  # Hostname of database server
export DBNAME=ojstest  # Database name
export DBUSERNAME=ojs2  # Username for database connections
export DBPASSWORD=  # Database password
export FILESDIR=/temp/ojs-uploads  # Pathname for storing server-side submission files
export DBTYPE=PostgreSQL  # Database driver (MySQL or PostgreSQL)
export TIMEOUT=30  # Selenium timeout; optional, 30 seconds by default
php lib/pkp/lib/vendor/phpunit/phpunit/phpunit $@
