#!/bin/sh

# The following script will be executed after the startup of the container.

# -- Permission setting
# find /var/www/html/ -type d -exec chmod 0777 {} \;
# find /var/www/html/ -type f -exec chmod 0666 {} \;
# find /var/www/html/ -type f -name *.cgi -exec chmod 0755 {} \;

# -- etc...


mkdir /var/log/php
touch /var/log/php/error.log


# -- Print log
echo 'init.sh setup done.' >> /var/log/entrypoint.log
