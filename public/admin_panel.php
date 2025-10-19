<?php
require_once __DIR__ . '/../src/auth.php';
require_admin();

require_once __DIR__ . '/../config/database.php';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1200px;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .admin-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .admin-menu-item {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
            width: 250px;
            transition: all 0.3s ease;
        }
        .admin-menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .admin-menu-item h3 {
            margin-top: 0;
            color: #007bff;
        }
    </style>
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Hoşgeldin, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                    <a href="admin_panel.php">Admin Paneli</a>
                    <a href="logout.php">Çıkış Yap</a>
                <?php endif; ?>
                </nav>
        </div>
    </header>

    <main class="container admin-container">
        <div class="admin-header">
            <h2>Admin Yönetim Paneli</h2>
        </div>

        <p>Sistem yönetimi için aşağıdaki modülleri kullanabilirsiniz.</p>

        <div class="admin-menu">
            <a href="admin_companies.php" class="admin-menu-item">
                <h3>Firma Yönetimi</h3>
                <p>Yeni otobüs firmaları oluşturun, mevcutları düzenleyin veya silin.</p>
            </a>

            <a href="admin_company_admins.php" class="admin-menu-item">
                <h3>Firma Admin Yönetimi</h3>
                <p>Yeni 'Firma Admin' kullanıcıları oluşturun ve firmalara atayın.</p>
            </a>

            <a href="admin_coupons.php" class="admin-menu-item">
                <h3>Genel Kupon Yönetimi</h3>
                <p>Tüm firmalarda geçerli indirim kuponları oluşturun ve yönetin.</p>
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