<?php
require_once __DIR__ . '/../src/auth.php';
require_company_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$company_id = $_SESSION['company_id'];
$error = null;
$success = null;
$coupon_to_edit = null;
$form_mode = 'add';

$expire_date_val = '';
$expire_time_val = '';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? 'add';
        $id = $_POST['id'] ?? null;

        $code = trim($_POST['code'] ?? null);
        $discount = (float)($_POST['discount'] ?? 0);
        $usage_limit = (int)($_POST['usage_limit'] ?? 0);

        $expire_date_input = $_POST['expire_date'] ?? null;
        $expire_time_input = $_POST['expire_time'] ?? null;
        $expire_date = $expire_date_input . ' ' . $expire_time_input;

        if (empty($code) || $discount <= 0 || $discount > 1 || $usage_limit <= 0 || empty($expire_date_input) || empty($expire_time_input)) {
            $error = "Tüm alanlar zorunludur. İndirim oranı 0.01 (1%) ile 1.00 (100%) arasında olmalıdır.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM Coupons WHERE code = ? AND id != ?");
            $stmt->execute([$code, $id ?? '']);

            if ($stmt->fetch()) {
                $error = "Bu kupon kodu ('" . htmlspecialchars($code) . "') zaten sistemde kayıtlı. Lütfen farklı bir kod deneyin.";
            } else {

                if ($action == 'add') {
                    $new_id = generate_uuid_v4();

                    $stmt = $pdo->prepare(
                        "INSERT INTO Coupons (id, code, discount, usage_limit, expire_date, company_id)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([$new_id, $code, $discount, $usage_limit, $expire_date, $company_id]);
                    $success = "Yeni kupon başarıyla eklendi.";

                } elseif ($action == 'edit' && $id) {
                    $stmt = $pdo->prepare(
                        "UPDATE Coupons SET code = ?, discount = ?, usage_limit = ?, expire_date = ?
                         WHERE id = ? AND company_id = ?"
                    );
                    $stmt->execute([$code, $discount, $usage_limit, $expire_date, $id, $company_id]);
                    $success = "Kupon başarıyla güncellendi.";
                }
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
        $action = $_GET['action'];
        $id = $_GET['id'] ?? null;

        if (!$id) {
            $error = "Geçersiz Kupon ID.";
        } else {
            if ($action == 'delete') {
                $stmt = $pdo->prepare(
                    "DELETE FROM Coupons WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([$id, $company_id]);
                $success = "Kupon başarıyla silindi.";

            } elseif ($action == 'edit') {
                $form_mode = 'edit';
                $stmt = $pdo->prepare(
                    "SELECT * FROM Coupons WHERE id = ? AND company_id = ?"
                );
                $stmt->execute([$id, $company_id]);
                $coupon_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$coupon_to_edit) {
                    $error = "Düzenlenecek kupon bulunamadı veya bu kupona yetkiniz yok.";
                    $form_mode = 'add';
                } else {
                    $expire_date_val = substr($coupon_to_edit['expire_date'], 0, 10);
                    $expire_time_val = substr($coupon_to_edit['expire_date'], 11, 5);
                }
            }
        }
    }

    $stmt_list = $pdo->prepare(
        "SELECT * FROM Coupons
         WHERE company_id = ?
         ORDER BY expire_date DESC"
    );
    $stmt_list->execute([$company_id]);
    $coupons = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
     if ($e->getCode() == 23000) {
        $error = "Bu kuponu silemezsiniz. Kupon zaten kullanıcılar tarafından kullanılmış olabilir veya kod çakışması yaşandı.";
    } else {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin - Kupon Yönetimi</title>
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
            <h2>Firma Kupon Yönetimi (CRUD)</h2>
            <a href="company_panel.php" class="button-secondary">Firma Paneline Dön</a>
        </div>

        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="admin-form-container">
            <h3>
                <?php echo $form_mode == 'edit' ? 'Kuponu Düzenle' : 'Yeni Kupon Ekle'; ?>
            </h3>
            <form action="company_coupons.php" method="POST" class="admin-form">

                <input type="hidden" name="action" value="<?php echo $form_mode; ?>">
                <?php if ($form_mode == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($coupon_to_edit['id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="code">Kupon Kodu:</label>
                    <input type="text" id="code" name="code" value="<?php echo htmlspecialchars($coupon_to_edit['code'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="discount">İndirim Oranı (Örn: 0.15):</label>
                    <input type="number" id="discount" name="discount" step="0.01" min="0.01" max="1.00" value="<?php echo htmlspecialchars($coupon_to_edit['discount'] ?? ''); ?>" required>
                    <small>%15 indirim için 0.15 yazın.</small>
                </div>
                <div class="form-group">
                    <label for="usage_limit">Kullanım Limiti (Adet):</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="1" value="<?php echo htmlspecialchars($coupon_to_edit['usage_limit'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="expire_date">Son Kullanma Tarihi:</label>
                    <input type="date" id="expire_date" name="expire_date" value="<?php echo htmlspecialchars($expire_date_val); ?>" required>
                </div>
                <div class="form-group">
                    <label for="expire_time">Son Kullanma Saati (24s):</label>
                    <input type="time" id="expire_time" name="expire_time" value="<?php echo htmlspecialchars($expire_time_val); ?>" required>
                </div>

                <?php if ($form_mode == 'edit'): ?>
                    <button type="submit">Kuponu Güncelle</button>
                    <a href="company_coupons.php" class="button-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit">Yeni Kupon Ekle</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-table-container">
            <h3>Firmanıza Ait Mevcut Kuponlar</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Kupon Kodu</th>
                        <th>İndirim Oranı</th>
                        <th>Kullanım Limiti</th>
                        <th>Kalan Kullanım</th>
                        <th>Son Kullanma Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($coupons) > 0): ?>
                        <?php foreach ($coupons as $coupon):
                            $kalan_kullanim = $coupon['usage_limit'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($coupon['code']); ?></strong></td>
                                <td><?php echo ($coupon['discount'] * 100); ?> %</td>
                                <td><?php echo htmlspecialchars($coupon['usage_limit']); ?></td>
                                <td><?php echo $kalan_kullanim; ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($coupon['expire_date'])); ?></td>
                                <td class="actions">
                                    <a href="company_coupons.php?action=edit&id=<?php echo $coupon['id']; ?>" class="action-button edit">Düzenle</a>
                                    <a href="company_coupons.php?action=delete&id=<?php echo $coupon['id']; ?>"
                                       class="action-button delete"
                                       onclick="return confirm('Bu kuponu silmek istediğinizden emin misiniz?');">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">Firmanıza ait kayıtlı kupon bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy: 2025 Bilet Satın Alma Platformu</p>
        </div>
    </footer>

</body>
</html>