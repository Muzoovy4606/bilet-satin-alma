# Resmi PHP 8.1 Apache imajını temel al
FROM php:8.1-apache

# Gerekli sistem kütüphanelerini kur
# pdo_sqlite için libsqlite3-dev
# mbstring için libonig-dev (Oniguruma regex kütüphanesi)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Gerekli PHP eklentilerini kur: PDO SQLite ve Multibyte String
RUN docker-php-ext-install pdo pdo_sqlite mbstring

# Apache yapılandırmasını projemizin public klasörünü kullanacak şekilde ayarla
RUN sed -i -e 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Apache'nin mod_rewrite modülünü etkinleştir
RUN a2enmod rewrite

# Proje dosyalarını container içindeki /var/www/html klasörüne kopyala
COPY . /var/www/html/

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Veritabanı dosyası ve klasörü için Apache kullanıcısına yazma izni ver
RUN chown -R www-data:www-data database && \
    chmod -R 775 database

# Container çalıştığında Apache'yi ön planda başlat
CMD ["apache2-foreground"]