#!bin/sh
set -e

php /var/www/html/artisan storage:link

ls -l /var/www/html
ls -l /var/www/html/public


echo "launching web server"