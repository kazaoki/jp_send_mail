FROM centos:7

# Set timezone
# ------------
RUN cp /etc/localtime /etc/localtime.org \
    && ln -sf  /usr/share/zoneinfo/Asia/Tokyo /etc/localtime \
    && localedef -f UTF-8 -i ja_JP ja_JP.UTF-8

# Set locale
# ----------
RUN echo 'LANG="ja_JP.utf8"' > /etc/locale.conf \
    && source /etc/locale.conf \
    && localedef -f UTF-8 -i ja_JP ja_JP.UTF-8

# Add packages
# --------------------------------------------------------------------------------------------------
RUN yum install -y epel-release \
    && yum update -y \
    && yum install -y \
       bash \
       tar \
       git \
       bzip2 \
       bzip2-devel \
       g++ gcc \
       libevent-devel \
       libxml2-devel \
       openssl-devel \
       libcurl-devel \
       libjpeg-devel \
       libpng-devel \
       re2c \
       libicu-devel \
       gcc-c++ \
       libmcrypt-devel \
       readline-devel \
       libtidy-devel \
       libxslt-devel \
       file \
       make \
       autoconf \
       patch \
       bison \
       lemon \
       libsql \
       mariadb-devel \
       perl \
       lftp \
       nodejs \
       ca-certificates \
       nginx \
       openssh-server \
       logrotate \
       multitail \
       perl-core \
       openssl \
       htop \
       wget \
       which \
       cronie \
       unzip \
       psmisc \
       perl-libwww-perl \
       perl-Crypt-SSLeay \
       perl-String-CRC32 \
       gnutls \
       perl-DBD-MySQL \
       perl-DBD-Pg \
       perl-GD \
       ImageMagick \
       ImageMagick-perl \
       postfix \
       libffi-devel \
       mariadb \
       mariadb-devel \
       postgresql \
       postgresql-devel \
       httpd \
       httpd-devel \
       mod_php \
       mod_ssl \
       net-tools \
       sudo \
       zip \
       mod_fcgid \
       lsof \
       rpm-build \
       libtool \
       php-mbstring \
       php-mysql \
       php-gd \
       php-xdebug \
       freetype-devel \
       libc-client \
    && yum clean all

# Install anyenv
# --------------------------------------------------------------------------------------------------
ENV PATH /root/.anyenv/bin:$PATH
RUN git clone https://github.com/riywo/anyenv ~/.anyenv \
    && echo 'export PATH="$HOME/.anyenv/bin:$PATH"' >> ~/.bashrc \
    && echo 'eval "$(anyenv init -)"' >> ~/.bashrc \
    && . ~/.bashrc \
    && anyenv install rbenv \
    && anyenv install plenv \
    && anyenv install phpenv \
    && anyenv install ndenv \
    && . ~/.bashrc

# Setup default PHP
# -----------------
RUN sed -i 's/\;date\.timezone\ \=/date\.timezone\ \=\ Asia\/Tokyo/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.language\ \=\ Japanese/mbstring\.language\ \=\ Japanese/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.internal_encoding\ \=\ EUC-JP/mbstring\.internal_encoding\ \=\ UTF-8/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.http_input\ \=\ auto/mbstring\.http_input\ \=\ pass/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.http_output\ \=\ SJIS/mbstring\.http_output\ \=\ pass/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.encoding_translation\ \=\ Off/mbstring\.encoding_translation\ \=\ Off/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.detect_order\ \=\ auto/mbstring\.detect_order\ \=\ UTF-8\,SJIS\,EUC-JP\,JIS\,ASCII/g' /etc/php.ini \
    && sed -i 's/\;mbstring\.func_overload\ \=\ 0/mbstring\.func_overload\ \=\ 0/g' /etc/php.ini

# Setup Apache
# ------------
RUN sed -i 's/\#AddHandler\ cgi\-script\ \.cgi/AddHandler\ cgi\-script\ \.cgi/g' /etc/httpd/conf/httpd.conf \
    && sed -i 's/AddDefaultCharset\ UTF\-8/AddDefaultCharset\ Off/g' /etc/httpd/conf/httpd.conf \
    && sed -i 's/^User apache$/User docker/' /etc/httpd/conf/httpd.conf \
    && sed -i 's/^Group apache$/Group docker/' /etc/httpd/conf/httpd.conf \
    && echo '<Directory /var/www/html>' >> /etc/httpd/conf/httpd.conf \
    && echo '  Options FollowSymLinks ExecCGI' >> /etc/httpd/conf/httpd.conf \
    && echo '  AllowOverride All' >> /etc/httpd/conf/httpd.conf \
    && echo '  DirectoryIndex index.cgi index.php index.html index.htm' >> /etc/httpd/conf/httpd.conf \
    && echo '  SetEnv PERL5LIB /perl/cpanfile-modules/lib/perl5' >> /etc/httpd/conf/httpd.conf \
    && echo '  EnableMMAP Off' >> /etc/httpd/conf/httpd.conf \
    && echo '  EnableSendfile Off' >> /etc/httpd/conf/httpd.conf \
    && echo '  # <__PASSENV__>' >> /etc/httpd/conf/httpd.conf \
    && echo '  # </__PASSENV__>' >> /etc/httpd/conf/httpd.conf \
    && echo '  # <__BANDWIDTH__>' >> /etc/httpd/conf/httpd.conf \
    && echo '  # </__BANDWIDTH__>' >> /etc/httpd/conf/httpd.conf \
    && echo '  # <__NO_CACHE__>' >> /etc/httpd/conf/httpd.conf \
    && echo '  # </__NO_CACHE__>' >> /etc/httpd/conf/httpd.conf \
    && echo '</Directory>' >> /etc/httpd/conf/httpd.conf \
    && sed -i 's/\#ServerName www\.example\.com\:80/ServerName genie/g' /etc/httpd/conf/httpd.conf \
    && sed -i 's/ScriptAlias \/cgi-bin\/ \"\/var\/www\/cgi-bin\/\"/\#ScriptAlias \/cgi-bin\/ \"\/var\/www\/cgi-bin\/\"/' /etc/httpd/conf/httpd.conf

# Install ngrok
# -------------
RUN mkdir /tmp/ngrok/ \
    && cd /tmp/ngrok/ \
    && curl -L -O https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-linux-amd64.zip \
    && unzip ngrok-stable-linux-amd64.zip \
    && mv ngrok /usr/local/bin \
    && rm -fr /tmp/ngrok/ \
    && cd

# Install suPHP
# -------------
ADD suphp-0.7.2-1.src.rpm /tmp/suphp/suphp-0.7.2-1.src.rpm
RUN cd /tmp/suphp/ \
    && rpm -ivh suphp-0.7.2-1.src.rpm \
    && rpmbuild -ba /root/rpmbuild/SPECS/suphp.spec \
    && rpm -ivh /root/rpmbuild/RPMS/x86_64/suphp-0.7.2-1.x86_64.rpm \
    && echo 'IncludeOptional /etc/httpd/conf/extra/httpd-suphp.conf' >> /etc/httpd/conf/httpd.conf

# Install Fluentd
# ---------------
RUN curl -L https://toolbelt.treasuredata.com/sh/install-redhat-td-agent2.sh | sh \
    && /opt/td-agent/embedded/bin/fluent-gem install fluent-plugin-elasticsearch

# Node.js config
# --------------------------------------------------------------------------------------------------
RUN echo '{ "allow_root": true }' > /root/.bowerrc \
    && npm install bower maildev -g

# # Install box# logrotate adjust
# ----------------
RUN sed -i 's/\/bin\/systemctl reload httpd\.service/\/usr\/sbin\/httpd \-k graceful/g' /etc/logrotate.d/httpd

# Install box
# -----------
RUN cd /tmp \
    && curl -LSs https://box-project.github.io/box2/installer.php | php \
    && mv box.phar /usr/bin/box \
    && chmod +x /usr/bin/box \
    && sed -i "s/;phar.readonly = On/phar.readonly = Off/" /etc/php.ini

# Add files
# ---------
ADD loop.sh /loop.sh
ADD entrypoint.sh /entrypoint.sh
RUN chmod +x /*.sh

# Add docker user
# ---------------
RUN groupadd -g 1000 docker \
    && useradd -g docker -m -s /bin/bash docker \
    && usermod -aG apache,nginx docker \
    && localedef -f UTF-8 -i ja_JP ja_JP.UTF-8

# Entry point
# -----------
ENTRYPOINT /entrypoint.sh
