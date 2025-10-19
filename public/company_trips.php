<?php
require_once __DIR__ . '/../src/auth.php';
require_company_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$company_id = $_SESSION['company_id'];
$error = null;
$success = null;
$trip_to_edit = null;
$form_mode = 'add';
$trips = []; // Her zaman bir dizi olarak başlat

$departure_date_val = '';
$departure_time_val = '';
$arrival_date_val = '';
$arrival_time_val = '';

try {
    // Sadece POST ve GET action'larını try içine alalım
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? 'add';
        $id = $_POST['id'] ?? null;

        $departure_city = $_POST['departure_city'] ?? null;
        $destination_city = $_POST['destination_city'] ?? null;
        $departure_date = $_POST['departure_date'] ?? null;
        $departure_time_input = $_POST['departure_time'] ?? null;
        $arrival_date = $_POST['arrival_date'] ?? null;
        $arrival_time_input = $_POST['arrival_time'] ?? null;
        $price = $_POST['price'] ?? null;
        $capacity = $_POST['capacity'] ?? null;

        $departure_time = $departure_date . ' ' . $departure_time_input;
        $arrival_time = $arrival_date . ' ' . $arrival_time_input;

        if (empty($departure_city) || empty($destination_city) || empty($departure_date) || empty($departure_time_input) || empty($arrival_date) || empty($arrival_time_input) || empty($price) || empty($capacity)) {
            $error = "Tüm alanlar zorunludur.";
        } elseif (strtotime($arrival_time) <= strtotime($departure_time)) {
            $error = "Varış saati, kalkış saatinden sonra olmalıdır.";
        } else {
            if ($action == 'add') {
                $new_id = generate_uuid_v4();
                $stmt = $pdo->prepare(
                    "INSERT INTO Trips (id, company_id, departure_city, destination_city, departure_time, arrival_time, price, capacity)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $new_id, $company_id, $departure_city, $destination_city,
                    $departure_time, $arrival_time, $price, $capacity
                ]);
                $success = "Yeni sefer başarıyla eklendi.";
            } elseif ($action == 'edit' && $id) {
                $stmt = $pdo->prepare(
                    "UPDATE Trips SET
                        departure_city = ?, destination_city = ?, departure_time = ?,
                        arrival_time = ?, price = ?, capacity = ?
                     WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([
                    $departure_city, $destination_city, $departure_time,
                    $arrival_time, $price, $capacity, $id, $company_id
                ]);
                $success = "Sefer başarıyla güncellendi.";
            }
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
        $action = $_GET['action'];
        $id = $_GET['id'] ?? null;

        if (!$id) {
            $error = "Geçersiz Sefer ID.";
        } else {
            if ($action == 'delete') {
                $stmt = $pdo->prepare(
                    "DELETE FROM Trips WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([$id, $company_id]);
                // Başarılı silme sonrası sayfayı yeniden yönlendirerek GET parametrelerini temizleyelim (PRG)
                if ($stmt->rowCount() > 0) {
                     $_SESSION['flash_success'] = "Sefer başarıyla silindi."; // Mesajı session'a koy
                     header("Location: company_trips.php");
                     exit;
                } else {
                    $error = "Sefer silinemedi (belki zaten silinmişti?).";
                }

            } elseif ($action == 'edit') {
                $form_mode = 'edit';
                $stmt = $pdo->prepare(
                    "SELECT * FROM Trips WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([$id, $company_id]);
                $trip_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$trip_to_edit) {
                    $error = "Düzenlenecek sefer bulunamadı veya bu sefere yetkiniz yok.";
                    $form_mode = 'add';
                } else {
                    $departure_date_val = substr($trip_to_edit['departure_time'], 0, 10);
                    $departure_time_val = substr($trip_to_edit['departure_time'], 11, 5);
                    $arrival_date_val = substr($trip_to_edit['arrival_time'], 0, 10);
                    $arrival_time_val = substr($trip_to_edit['arrival_time'], 11, 5);
                }
            }
        }
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        $error = "Bu seferi silemezsiniz. Bu sefere ait satılmış biletler olabilir.";
    } else {
        $error = "Veritabanı hatası: " . $e->getMessage(); // Geliştirme için hatayı gösterelim
        // Canlıda: error_log("DB Error: " . $e->getMessage()); $error = "Bir veritabanı hatası oluştu.";
    }
}

// Session'dan flash mesajı al (PRG sonrası silme mesajı gibi)
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}


// LİSTELEME İŞLEMİ (Her zaman çalışır)
try {
    $stmt_list = $pdo->prepare(
        "SELECT * FROM Trips
         WHERE company_id = ?
         ORDER BY departure_time DESC"
    );
    $stmt_list->execute([$company_id]);
    $trips = $stmt_list->fetchAll(PDO::FETCH_ASSOC); // $trips burada ya dolu ya boş array olur
} catch (PDOException $e) {
     $error = "Sefer listesi alınırken hata oluştu: " . $e->getMessage();
     // $trips yukarıda [] olarak başlatıldığı için HTML'de hata vermez.
}


?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin - Sefer Yönetimi</title>
    <link rel="stylesheet" href="style.css">
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
            <h2>Sefer Yönetimi (CRUD)</h2>
            <a href="company_panel.php" class="button-secondary">Firma Paneline Dön</a>
        </div>

        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="admin-form-container">
            <h3>
                <?php echo $form_mode == 'edit' ? 'Seferi Düzenle' : 'Yeni Sefer Ekle'; ?>
            </h3>
            <form action="company_trips.php" method="POST" class="admin-form">

                <input type="hidden" name="action" value="<?php echo $form_mode; ?>">
                <?php if ($form_mode == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($trip_to_edit['id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="departure_city">Kalkış Yeri:</label>
                    <input type="text" id="departure_city" name="departure_city" value="<?php echo htmlspecialchars($trip_to_edit['departure_city'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="destination_city">Varış Yeri:</label>
                    <input type="text" id="destination_city" name="destination_city" value="<?php echo htmlspecialchars($trip_to_edit['destination_city'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="departure_date">Kalkış Tarihi:</label>
                    <input type="date" id="departure_date" name="departure_date" value="<?php echo htmlspecialchars($departure_date_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="departure_time">Kalkış Saati (24s):</label>
                    <input type="time" id="departure_time" name="departure_time" value="<?php echo htmlspecialchars($departure_time_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="arrival_date">Varış Tarihi:</label>
                    <input type="date" id="arrival_date" name="arrival_date" value="<?php echo htmlspecialchars($arrival_date_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="arrival_time">Varış Saati (24s):</label>
                    <input type="time" id="arrival_time" name="arrival_time" value="<?php echo htmlspecialchars($arrival_time_val); ?>" required>
                </div>

                <div class="form-group">
                    <label for="price">Fiyat (TL):</label>
                    <input type="number" id="price" name="price" min="0" value="<?php echo htmlspecialchars($trip_to_edit['price'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="capacity">Kapasite (Koltuk Sayısı):</label>
                    <input type="number" id="capacity" name="capacity" min="1" value="<?php echo htmlspecialchars($trip_to_edit['capacity'] ?? ''); ?>" required>
                </div>

                <?php if ($form_mode == 'edit'): ?>
                    <button type="submit">Seferi Güncelle</button>
                    <a href="company_trips.php" class="button-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit">Yeni Sefer Ekle</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-table-container">
            <h3>Mevcut Seferler</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kalkış</th>
                        <th>Varış</th>
                        <th>Kalkış Zamanı</th>
                        <th>Varış Zamanı</th>
                        <th>Fiyat</th>
                        <th>Kapasite</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($trips) > 0): ?>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trip['departure_city']); ?></td>
                                <td><?php echo htmlspecialchars($trip['destination_city']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($trip['departure_time'])); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($trip['arrival_time'])); ?></td>
                                <td><?php echo htmlspecialchars($trip['price']); ?> TL</td>
                                <td><?php echo htmlspecialchars($trip['capacity']); ?></td>
                                <td class="actions">
                                    <a href="company_trips.php?action=edit&id=<?php echo $trip['id']; ?>" class="action-button edit">Düzenle</a>
                                    <a href="company_trips.php?action=delete&id=<?php echo $trip['id']; ?>"
                                       class="action-button delete"
                                       onclick="return confirm('Bu seferi silmek istediğinizden emin misiniz?');">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">Firmanıza ait kayıtlı sefer bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Bilet Satın Alma Platformu</p>
    </footer>

</body>
</html>