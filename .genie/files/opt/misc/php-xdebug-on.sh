#!/bin/sh

phpini=$(cat /phpinipath)

# clear
sed -i "/xdebug\.remote_enable/d" $phpini
sed -i "/xdebug\.remote_autostart/d" $phpini
sed -i "/xdebug\.remote_host/d" $phpini
sed -i "/xdebug\.remote_port/d" $phpini

# adding
echo 'xdebug.remote_enable = On' >> $phpini
echo 'xdebug.remote_autostart = On' >> $phpini
echo "xdebug.remote_host=$GENIE_LANG_PHP_XDEBUG_HOST" >> $phpini
echo "xdebug.remote_port=$GENIE_LANG_PHP_XDEBUG_PORT" >> $phpini

# restart httpd
/usr/sbin/httpd -k restart
