<?php
require_once __DIR__ . '/../src/auth.php';
require_admin();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$error = null;
$success = null;
$company_to_edit = null;
$form_mode = 'add';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? 'add';
        $name = $_POST['name'] ?? null;
        $logo_path = $_POST['logo_path'] ?? null;
        $id = $_POST['id'] ?? null;

        if (empty($name)) {
            $error = "Firma adı zorunludur.";
        } else {
            if ($action == 'add') {
                $new_id = generate_uuid_v4();
                $stmt = $pdo->prepare("INSERT INTO Bus_Company (id, name, logo_path) VALUES (?, ?, ?)");
                $stmt->execute([$new_id, $name, $logo_path]);
                $success = "Yeni firma başarıyla eklendi.";

            } elseif ($action == 'edit' && $id) {
                $stmt = $pdo->prepare("UPDATE Bus_Company SET name = ?, logo_path = ? WHERE id = ?");
                $stmt->execute([$name, $logo_path, $id]);
                $success = "Firma başarıyla güncellendi.";
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
                $stmt = $pdo->prepare("DELETE FROM Bus_Company WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Firma başarıyla silindi. (İlişkili tüm seferler de silindi)";

            } elseif ($action == 'edit') {
                $form_mode = 'edit';
                $stmt = $pdo->prepare("SELECT * FROM Bus_Company WHERE id = ?");
                $stmt->execute([$id]);
                $company_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$company_to_edit) {
                    $error = "Düzenlenecek firma bulunamadı.";
                    $form_mode = 'add';
                }
            }
        }
    }

    $stmt_list = $pdo->query("SELECT * FROM Bus_Company ORDER BY name ASC");
    $companies = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        $error = "Bu firmayı silemezsiniz. Önce bu firmaya kayıtlı seferleri veya firma adminlerini silmelisiniz.";
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
    <title>Admin - Firma Yönetimi</title>
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
            <h2>Firma Yönetimi (CRUD)</h2>
            <a href="admin_panel.php" class="button-secondary">Admin Paneline Dön</a>
        </div>

        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="admin-form-container">
            <h3>
                <?php echo $form_mode == 'edit' ? 'Firmayı Düzenle' : 'Yeni Firma Ekle'; ?>
            </h3>
            <form action="admin_companies.php" method="POST" class="admin-form">

                <input type="hidden" name="action" value="<?php echo $form_mode; ?>">

                <?php if ($form_mode == 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($company_to_edit['id'] ?? ''); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Firma Adı:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company_to_edit['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="logo_path">Logo Yolu (örn: /img/logo.png):</label>
                    <input type="text" id="logo_path" name="logo_path" value="<?php echo htmlspecialchars($company_to_edit['logo_path'] ?? ''); ?>">
                </div>

                <?php if ($form_mode == 'edit'): ?>
                    <button type="submit">Firmayı Güncelle</button>
                    <a href="admin_companies.php" class="button-secondary">İptal</a>
                <?php else: ?>
                    <button type="submit">Yeni Firma Ekle</button>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-table-container">
            <h3>Mevcut Firmalar</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Firma Adı</th>
                        <th>Logo Yolu</th>
                        <th>Oluşturulma Tarihi</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($companies) > 0): ?>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($company['name']); ?></td>
                                <td><?php echo htmlspecialchars($company['logo_path'] ?? '-'); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($company['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="admin_companies.php?action=edit&id=<?php echo $company['id']; ?>" class="action-button edit">Düzenle</a>
                                    <a href="admin_companies.php?action=delete&id=<?php echo $company['id']; ?>"
                                       class="action-button delete"
                                       onclick="return confirm('Bu firmayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!');">Sil</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Kayıtlı otobüs firması bulunamadı.</td>
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