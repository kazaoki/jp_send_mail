#!/bin/sh

# Process start
# -------------
echo 'Process start' >> /var/log/init.log

# TimeZone set
# ------------
cp -p /usr/share/zoneinfo/Asia/Tokyo /etc/localtime
echo 'Asia/Tokyo' > /etc/timezone

# Package update & lang setting
# -----------------------------
echo "ja_JP.UTF-8 UTF-8" >> /etc/locale.gen
echo "ja_JP.EUC-JP EUC-JP" >> /etc/locale.gen
/usr/sbin/locale-gen
export LANG=$POSTGERS_LOCALE
/usr/sbin/update-locale LANG=$POSTGERS_LOCALE

# Copy shell file
# ---------------
cp /opt/postgresql/docker-entrypoint-initdb.d/* /docker-entrypoint-initdb.d

# Copy dump file
# --------------
cp /opt/postgresql/dumps/$POSTGRES_LABEL.* /docker-entrypoint-initdb.d

# Pass to true shell
# ------------------
exec /docker-entrypoint.sh $@
