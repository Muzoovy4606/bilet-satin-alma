müşteri:test@mail.com,test123
süper admin:admin@bilet.com,admin123(Admin123)
firma admini kamil_koc@bilet.com,kamil123(şifre yanlışsa süper admin paneli üzerinden değiştirilebilir)


Database e yazma fonksiyonlarında yetki sorunu ile karşılaşılırsa(şifre değiştirme vs)
sudo docker exec bilet_satin_alma_web chown -R www-data:www-data /var/www/html/database
sudo docker exec bilet_satin_alma_web chmod -R 775 /var/www/html/database
komutları ile sorun düzeltilebilir.
