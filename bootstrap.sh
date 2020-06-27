# 1. Установка и конфигурация Git ##############################################

echo ">>> Installing git"

apt-get update -y
apt-get install -qq git

git config --global core.autocrlf true
git config --global core.filemode false

# 2. Установка MongoDB #########################################################

echo ">>> Installing MongoDB"
# Добавляем репозиторий со свежими пакетами MongoDB
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 0C49F3730359A14518585931BC711F9BA15703C6
echo "deb http://repo.mongodb.org/apt/ubuntu xenial/mongodb-org/3.4 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-3.4.list
sudo apt-get update -y
# Устанавливаем набор пакетов
sudo apt-get install -qq mongodb-org=3.4* mongodb-org-server=3.4* mongodb-org-shell=3.4* mongodb-org-mongos=3.4* mongodb-org-tools=3.4*
# Меняем дефолтную конфигурацию
sudo sed -i "s/bindIp:.*/bindIp: 0.0.0.0/" /etc/mongod.conf
# Перезапускаем процесс
systemctl enable mongod.service
systemctl restart mongod.service

# 3. Установка основных пакетов для PHP 7.0 ####################################

echo ">>> Installing PHP"

apt-get install -qq php-fpm php-cli php-common php-curl php-gd php-intl php-pear php-imagick php-imap php-mcrypt php7.0-sqlite3 php-xmlrpc php7.0-xml php7.0-mbstring

# 4. Установка свежего драйвера для работы с MongoDB из PHP ####################

echo ">>> Installing mongodb PHP-extension"
# Сперва устанавливаются пакеты необходимые для кампиляции драйвера
sudo apt-get install -qq php7.0-dev autoconf g++ make openssl libssl-dev libcurl4-openssl-dev libcurl4-openssl-dev pkg-config libsasl2-dev
# Перегружаем переменные окружения, чтобы подхватить тулзу phpize
. /etc/environment
# Установка происходит с помощью менеджера для PHP-расширений pecl
echo -ne '\n' | sudo pecl install mongodb-1.2.8

if [ -d "/etc/php/7.0/mods-available" ]; then
  echo 'extension=mongodb.so' > /etc/php/7.0/mods-available/mongodb.ini
  [ -d '/etc/php/7.0/fpm' ] && ln -s /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/fpm/conf.d/30-mongodb.ini
  [ -d '/etc/php/7.0/cli' ] && ln -s /etc/php/7.0/mods-available/mongodb.ini /etc/php/7.0/cli/conf.d/30-mongodb.ini
fi

# 5. Конфигурация PHP ##########################################################

echo ">>> Configuring PHP-FPM"

mkdir /var/log/php/

sed -i "s/listen =.*/listen = 127.0.0.1:9000/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/;listen.allowed_clients/listen.allowed_clients/" /etc/php/7.0/fpm/pool.d/www.conf

sed -i "s/user \= www-data/user = ubuntu/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/group \= www-data/group = ubuntu/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/listen\.owner.*/listen.owner = ubuntu/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/listen\.group.*/listen.group = ubuntu/" /etc/php/7.0/fpm/pool.d/www.conf
sed -i "s/.*listen\.mode.*/listen.mode = 0666/" /etc/php/7.0/fpm/pool.d/www.conf

sed -i "s/.*cgi\.fix_pathinfo\=.*/cgi.fix_pathinfo = 0/" /etc/php/7.0/fpm/php.ini

sed -i "s/.*error_log \= syslog/error_log = \/var\/log\/php\/fpm.error.log/" /etc/php/7.0/fpm/php.ini
sed -i "s/.*error_log \= syslog/error_log = \/var\/log\/php\/cli.error.log/" /etc/php/7.0/cli/php.ini

sed -i "s/error_log \=.*/error_log = \/var\/log\/php\/fpm.error.log/" /etc/php/7.0/fpm/php-fpm.conf

sudo systemctl restart php7.0-fpm.service

# 6. Устанавливаем менеджер PHP-зависимостей ###################################

echo ">>> Installig/updating Composer"

composer --version 2> /dev/null
COMPOSER_STATUS=$? # 0:installed

if [[ ${COMPOSER_STATUS} != "0" ]]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
else
    composer self-update
fi

# 7. Устанавливаем Phalcon #####################################################

echo ">>> Install Phalcon ..."

curl -s https://packagecloud.io/install/repositories/phalcon/stable/script.deb.sh | sudo bash
apt-get -qq install php7.0-phalcon=3.0.2*

systemctl restart php7.0-fpm.service

# 8. Устанавливаем Redis #######################################################

echo ">>> Installing Redis"

apt-get -qq install redis-server=2:3.0.* php-redis

# 9. Удаляем сервер Apache т.к. мы будем использовать nginx ####################

echo ">>> Remove Apache"

apt-get -qq remove apache2

# 10. Устанавливаем nginx ######################################################

echo ">>> Installing NGINX"

apt-get -qq install nginx

# 11. Изменяем общий конфиг nginx ##############################################

echo ">>> Configuring NGINX"

# There is a VirtualBox bug related to sendfile which can result in corrupted or non-updating files
# https://docs.vagrantup.com/v2/synced-folders/virtualbox.html
sed -i 's/sendfile on;/sendfile off;/' /etc/nginx/nginx.conf
sed -i "s/user www-data;/user ubuntu;/" /etc/nginx/nginx.conf
sed -i "s/# server_names_hash_bucket_size.*/server_names_hash_bucket_size 64;/" /etc/nginx/nginx.conf

usermod -a -G www-data ubuntu

# 12. Добавляем хост для обработки HTTP-запрсов к нашему проекту ###############

echo ">>> Configuring host"
# Формируем содержимое конфига для домена dev-isit.lab.ru
read -d '' NGINX_SITE <<EOF
server {
        listen 80;
        server_name dev-isit.lab.ru;

        index index.php;
        root /home/ubuntu/project/public/;

        access_log off;
        error_log /var/log/nginx/dev-isit.lab.ru.error.log error;

        try_files \$uri \$uri/ @rewrite;

        location @rewrite {
                rewrite ^/(.*)$ /index.php?_url=/\$1;
        }

        # для кроссдоменного досупа к шрифтам
        location ~* \\\.(eot|ttf|woff)$ {
                add_header Access-Control-Allow-Origin *;
        }

        location ~ \\\.php {
                try_files \$uri =404;
                fastcgi_split_path_info ^(.+?\\\.php)(/.*)\$;
                fastcgi_param HTTPS off;

                fastcgi_pass 127.0.0.1:9000; # using TCP
                fastcgi_index index.php;
                include fastcgi_params;

                fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
                fastcgi_param DOCUMENT_ROOT \$realpath_root;
        }
}
EOF

echo "${NGINX_SITE}" > /etc/nginx/sites-available/dev-isit.lab.ru
ln -sf /etc/nginx/sites-available/dev-isit.lab.ru /etc/nginx/sites-enabled/dev-isit.lab.ru

systemctl restart nginx.service

echo ">>> Устанавливаем Zip"
apt-get -qq install zip

# 13. Добавляем в bash_profile дефолтную директорию при входе ##################

echo ">>> Deafult dir"

echo "cd ~ubuntu/project" >> ~ubuntu/.bash_profile
