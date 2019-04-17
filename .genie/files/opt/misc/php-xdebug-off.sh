#!/bin/sh

phpini=$(cat /phpinipath)

# clear
sed -i "/xdebug\.remote_enable/d" $phpini
sed -i "/xdebug\.remote_autostart/d" $phpini
sed -i "/xdebug\.remote_host/d" $phpini
sed -i "/xdebug\.remote_port/d" $phpini

# adding
echo 'xdebug.remote_enable = Off' >> $phpini
echo 'xdebug.remote_autostart = Off' >> $phpini

# restart httpd
/usr/sbin/httpd -k restart
