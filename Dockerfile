FROM php:8.0-apache

# Cài đặt các thư viện hệ thống và PHP extensions cần thiết
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Kích hoạt rewrite module của Apache để hỗ trợ tệp .htaccess điều hướng URL
RUN a2enmod rewrite

# Sao chép toàn bộ mã nguồn của dự án vào thư mục gốc Apache
COPY . /var/www/html/

# Tạo các thư mục lưu trữ động và cấp quyền ghi cho Apache
RUN mkdir -p /var/www/html/logs /var/www/html/assets/cache /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/ \
    && chmod -R 755 /var/www/html/ \
    && chmod -R 777 /var/www/html/logs /var/www/html/assets/cache /var/www/html/uploads

# Chuyển đổi cổng Apache động theo biến PORT (Yêu cầu bắt buộc của Railway/Cloud Run)
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Thiết lập biến môi trường PORT mặc định nếu không có
ENV PORT=80

EXPOSE 80

CMD ["apache2-foreground"]
