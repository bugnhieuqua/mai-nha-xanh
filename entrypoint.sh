#!/bin/bash
set -e

# Tắt các MPM xung đột ngay khi container khởi động (runtime)
a2dismod mpm_event || true
a2dismod mpm_worker || true
a2enmod mpm_prefork || true

# Đảm bảo quyền ghi cho user www-data trên thư mục volume được mount
if [ -d /var/www/html/uploads ]; then
    chown -R www-data:www-data /var/www/html/uploads
    chmod -R 777 /var/www/html/uploads
fi

# Thực thi lệnh chính của container (apache2-foreground)
exec "$@"

