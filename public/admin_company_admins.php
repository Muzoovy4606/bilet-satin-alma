<?php
require_once __DIR__ . '/../src/auth.php';
require_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$error = null;
$success = null;
$user_to_edit = null;
$form_mode = 'add';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? 'add';
        $id = $_POST['id'] ?? null;
        $full_name = $_POST['full_name'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $company_id = $_POST['company_id'] ?? null;

        if (empty($full_name) || empty($email) || empty($company_id)) {
            $error = "Ad Soyad, E-posta ve Firma seçimi zorunludur.";
        } elseif ($action == 'add' && empty($password)) {
            $error = "Yeni kullanıcı için şifre zorunludur.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçersiz e-posta adresi.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id ?? '']);
            if ($stmt->fetch()) {
                $error = "Bu e-posta adresi zaten başka bir kullanıcı tarafından kullanılıyor.";
            } else {
                if ($action == 'add') {
                    $new_id = generate_uuid_v4();
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO User (id, full_name, email, password, role, company_id)
                         VALUES (?, ?, ?, ?, 'company_admin', ?)"
                    );
                    $stmt->execute([$new_id, $full_name, $email, $hashed_password, $company_id]);
                    $success = "Yeni Firma Admin kullanıcısı başarıyla eklendi.";

                } elseif ($action == 'edit' && $id) {
                    if (empty($password)) {
                        $stmt = $pdo->prepare(
                            "UPDATE User SET full_name = ?, email = ?, company_id = ?
                             WHERE id = ? AND role = 'company_admin'"
                        );
                        $stmt->execute([$full_name, $email, $company_id, $id]);
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $pdo->prepare(
                            "UPDATE User SET full_name = ?, email = ?, company_id = ?, password = ?
                             WHERE id = ? AND role = 'company_admin'"
                        );
                        $stmt->execute([$full_name, $email, $company_id, $hashed_password, $id]);
                    }
                    $success = "Firma Admin kullanıcısı başarıyla güncellendi.";
                }
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
        $action = $_GET['action'];
        $id = $_GET['id'] ?? null;

        if (!$id) {
            $error = "Geçersiz ID.";
        } else {
            if ($action == 'delete') {
                $stmt = $pdo->prepare("DELETE FROM User WHERE id = ? AND role = 'company_admin'");
                $stmt->execute([$id]);
                $success = "Firma Admin kullanıcısı başarıyla silindi.";

            } elseif ($action == 'edit') {
                $form_mode = 'edit';
                $stmt = $pdo->prepare("SELECT * FROM User WHERE id = ? AND role = 'company_admin'");
                $stmt->execute([$id]);
                $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user_to_edit) {
                    $error = "Düzenlenecek kullanıcı bulunamadı.";
                    $form_mode = 'add';
                }
            }
        }
    }

    $stmt_list = $pdo->query("
        SELECT
            User.*,
            Bus_Company.name AS company_name
        FROM User
        LEFT JOIN Bus_Company ON User.company_id = Bus_Company.id
        WHERE User.role = 'company_admin'
        ORDER BY User.created_at DESC
    ");
    $company_admins = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

    $stmt_companies = $pdo->query("SELECT id, name FROM Bus_Company ORDER BY name ASC");
    $available_companies = $stmt_companies->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Firma Admin Yönetimi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <span>Hoşgeldin, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="admin_panel.php">Admin Paneli</a>
                <a href="logout.php">Çıkış Yap</a>
            </nav>
        </div>
    </header>

    <main class="container admin-container">
        <div class="admin-header">
            <h2>Firma Admin Yönetimi</h2>
            <a href="admin_panel.php" class="button-secondary">Admin Paneline Dön</a>
        </div>

        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="admin-form-container">
            <h3>
                <?php echo $form_mode == 'edit' ? 'Firma Admini Düzenle' : 'Yeni Firma Admin Ekle'; ?>
            </h3>
            <form action="admin_company_admins.php" method="POST" class="admin-form">

                <input type="hidden" name="action" value="<?php echo $form_mode; ?>">

                <?php if ($form_mode == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_to_edit['id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="full_name">Ad Soyad:</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_to_edit['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-posta:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre:</label>
                    <input type="password" id="password" name="password" <?php echo ($form_mode == 'add') ? 'required' : ''; ?>>
                    <?php if ($form_mode == 'edit'): ?>
                        <small>Değiştirmek istemiyorsanız boş bırakın.</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="company_id">Atanacak Firma:</label>
                    <select id="company_id" name="company_id" required>
                        <option value="">-- Firma Seçin --</option>
                        <?php foreach ($available_companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                <?php
                                if (isset($user_to_edit['company_id']) && $user_to_edit['company_id'] == $company['id']) {
                                    echo 'selected';
                                }
                                ?>
                            >
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($form_mode == 'edit'): ?>
                    <button type="submit">Kullanıcıyı Güncelle</button>
                    <a href="admin_company_admins.php" class="button-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit">Yeni Admin Ekle</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-table-container">
            <h3>Mevcut Firma Adminleri</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ad Soyad</th>
                        <th>E-posta</th>
                        <th>Atandığı Firma</th>
                        <th>Kayıt Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($company_admins) > 0): ?>
                        <?php foreach ($company_admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <?php
                                    echo htmlspecialchars($admin['company_name'] ?? '<i>Atanmamış</i>');
                                    ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($admin['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="admin_company_admins.php?action=edit&id=<?php echo $admin['id']; ?>" class="action-button edit">Düzenle</a>
                                    <a href="admin_company_admins.php?action=delete&id=<?php echo $admin['id']; ?>"
                                       class="action-button delete"
                                       onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?');">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Kayıtlı firma admin kullanıcısı bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bilet Satın Alma Platformu</p>
        </div>
    </footer>

</body>
</html>