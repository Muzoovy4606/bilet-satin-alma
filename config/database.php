<?php

// config/database.php

// SQLite veritabanı dosyamızın yolu.
// __DIR__ bu dosyanın (database.php) bulunduğu klasörü (config) verir.
// ../ diyerek bir üst dizine (bilet-satin-alma) çıkıp database/bilet.db'ye ulaşıyoruz.
$db_path = __DIR__ . '/../database/bilet.db';

try {
    // PDO (PHP Data Objects) kullanarak SQLite veritabanına bağlanıyoruz.
    // Bu $pdo değişkenini projemizin her yerinde kullanacağız.
    $pdo = new PDO("sqlite:" . $db_path);

    // Hata modunu "exception" olarak ayarlıyoruz.
    // Bu sayede, bir veritabanı hatası olursa kodumuz bir istisna (exception) fırlatır
    // ve programın çalışması durur. Bu, hataları yakalamak için en iyi yoldur.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // SQL Injection'a karşı korunmak için PDO'nun "prepared statements" özelliğini
    // tam olarak kullanabilmek için bu ayarı false yapıyoruz.
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Veritabanı bağlantımızda yabancı anahtar (Foreign Key) kısıtlamalarını
    // aktif hale getiriyoruz. Bu, veri bütünlüğü için önemlidir.
    $pdo->exec("PRAGMA foreign_keys = ON;");

} catch (PDOException $e) {
    // Bağlantı başarısız olursa, bir hata mesajı gösterip programı sonlandırıyoruz.
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Bu dosyayı başka bir PHP dosyasından "include" ettiğimizde,
// $pdo değişkeni o dosyada kullanılabilir hale gelecektir.
?>