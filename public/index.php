<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

$departure_city = $_GET['departure_city'] ?? null;
$destination_city = $_GET['destination_city'] ?? null;
$departure_date = $_GET['departure_date'] ?? null;
$trips = [];

$search_performed = $departure_city && $destination_city;

if ($search_performed) {
    try {
        $sql = "
            SELECT
                Trips.*,
                b.name AS company_name,
                b.logo_path
            FROM Trips
            JOIN Bus_Company b ON Trips.company_id = b.id
            WHERE departure_city = :dep_city
            AND destination_city = :dest_city
            AND departure_time > CURRENT_TIMESTAMP
        ";

        $params = [
            ':dep_city' => $departure_city,
            ':dest_city' => $destination_city
        ];

        if ($departure_date) {
            $date_start = $departure_date . ' 00:00:00';
            $date_end = $departure_date . ' 23:59:59';
            $sql .= " AND departure_time >= :date_start AND departure_time <= :date_end";
            $params[':date_start'] = $date_start;
            $params[':date_end'] = $date_end;
        }

        $sql .= " ORDER BY departure_time ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        echo "Sorgu hatası: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Alma Platformu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span>Hoşgeldin, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>

                    <?php if ($_SESSION['user_role'] == 'admin'): ?>
                        <a href="admin_panel.php">Admin Paneli</a>
                    <?php elseif ($_SESSION['user_role'] == 'company_admin'): ?>
                        <a href="company_panel.php">Firma Paneli</a>
                    <?php else: ?>
                        <a href="account.php">Hesabım / Biletler</a>
                    <?php endif; ?>

                    <a href="logout.php">Çıkış Yap</a>
                <?php else: ?>
                    <a href="login.php">Giriş Yap</a>
                    <a href="register.php">Kayıt Ol</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <div class="container search-form-container">
            <section class="search-form">
                <h2>Nereden Nereye?</h2>
                <form action="index.php" method="GET">
                    <div class="form-group">
                        <label for="departure_city">Kalkış Yeri:</label>
                        <input type="text" id="departure_city" name="departure_city" value="<?php echo htmlspecialchars($departure_city ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="destination_city">Varış Yeri:</label>
                        <input type="text" id="destination_city" name="destination_city" value="<?php echo htmlspecialchars($destination_city ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="departure_date">Gidiş Tarihi:</label>
                        <input type="date" id="departure_date" name="departure_date" value="<?php echo htmlspecialchars($departure_date ?? ''); ?>">
                        <small>(Boş bırakırsanız tüm tarihler)</small>
                    </div>
                    <button type="submit">Sefer Ara</button>
                </form>
            </section>
        </div>

        <div class="container trip-list-container">
            <section class="trip-list">
                <h2>Sefer Sonuçları</h2>

                <?php if ($search_performed): ?>
                    <?php if (count($trips) > 0): ?>
                        <?php foreach ($trips as $trip): ?>
                            <div class="trip-card">
                                <div class="company-info">
                                    <?php if (!empty($trip['logo_path']) && file_exists(__DIR__ . $trip['logo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($trip['logo_path']); ?>" alt="<?php echo htmlspecialchars($trip['company_name']); ?> Logo" class="company-logo">
                                    <?php endif; ?>
                                    <span class="company-name"><?php echo htmlspecialchars($trip['company_name']); ?></span>
                                </div>
                                <div class="trip-info">
                                    <strong>Kalkış:</strong> <?php echo htmlspecialchars($trip['departure_city']); ?>
                                    <span>(<?php echo date('d.m.Y H:i', strtotime($trip['departure_time'])); ?>)</span>
                                </div>
                                <div class="trip-info">
                                    <strong>Varış:</strong> <?php echo htmlspecialchars($trip['destination_city']); ?>
                                    <span>(<?php echo date('d.m.Y H:i', strtotime($trip['arrival_time'])); ?>)</span>
                                </div>
                                <div class="trip-details">
                                    <span class="price"><?php echo htmlspecialchars($trip['price']); ?> TL</span>

                                    <?php
                                    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 'user'):
                                    ?>
                                        <a href="buy_ticket.php?trip_id=<?php echo $trip['id']; ?>" class="buy-button">Bilet Al</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Aradığınız kriterlere uygun sefer bulunamadı.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Lütfen kalkış ve varış noktalarını seçerek arama yapın.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Bilet Satın Alma Platformu</p>
    </footer>
</body>
</html>