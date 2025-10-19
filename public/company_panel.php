<?php
require_once __DIR__ . '/../src/auth.php';
require_company_admin();

require_once __DIR__ . '/../config/database.php';

$company_name = "Firma Bulunamadı";
if (isset($_SESSION['company_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM Bus_Company WHERE id = ?");
        $stmt->execute([$_SESSION['company_id']]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($company) {
            $company_name = $company['name'];
        }
    } catch (PDOException $e) {
        // Hata durumunda varsayılan değer kalır
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin Paneli</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container { max-width: 1200px; }
        .admin-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .admin-menu { display: flex; flex-wrap: wrap; gap: 20px; }
        .admin-menu-item { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-decoration: none; color: #333; width: 250px; transition: all 0.3s ease; border: 1px solid #eee;} /* Çerçeve eklendi */
        .admin-menu-item:hover { transform: translateY(-5px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .admin-menu-item h3 { margin-top: 0; color: #007bff; }
    </style>
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <span>Hoşgeldin, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="company_panel.php">Firma Paneli</a>
                <a href="logout.php">Çıkış Yap</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <div class="admin-header">
            <h2>Firma Yönetim Paneli: <?php echo htmlspecialchars($company_name); ?></h2>
        </div>

        <p>Firmanıza ait işlemleri yönetmek için aşağıdaki modülleri kullanabilirsiniz.</p>

        <div class="admin-menu">
            <a href="company_trips.php" class="admin-menu-item">
                <h3>Sefer Yönetimi (CRUD)</h3>
                <p>Yeni seferler oluşturun, mevcut seferleri düzenleyin veya silin.</p>
            </a>

            <a href="company_coupons.php" class="admin-menu-item">
                <h3>Firma Kupon Yönetimi</h3>
                <p>Firmanıza özel indirim kuponları oluşturun ve yönetin.</p>
            </a>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bilet Satın Alma Platformu</p>
        </div>
    </footer>

</body>
</html>