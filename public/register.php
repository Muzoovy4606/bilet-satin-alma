<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = $_POST['full_name'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $password_confirm = $_POST['password_confirm'] ?? null;

    if (empty($full_name) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "Lütfen tüm alanları doldurun.";
    } elseif ($password !== $password_confirm) {
        $error = "Girdiğiniz şifreler uyuşmuyor.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Lütfen geçerli bir e-posta adresi girin.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM User WHERE email = ?");
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                $error = "Bu e-posta adresi zaten kayıtlı. Lütfen giriş yapın.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $user_id = generate_uuid_v4();
                $insertStmt = $pdo->prepare(
                    "INSERT INTO User (id, full_name, email, password, role)
                     VALUES (?, ?, ?, ?, 'user')"
                );
                $insertStmt->execute([$user_id, $full_name, $email, $hashed_password]);

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $full_name;
                $_SESSION['user_role'] = 'user';

                header("Location: index.php");
                exit;
            }

        } catch (PDOException $e) {
            $error = "Kayıt sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <a href="login.php">Giriş Yap</a>
                <a href="register.php">Kayıt Ol</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="auth-form">
            <h2>Yeni Hesap Oluştur</h2>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="full_name">Ad Soyad:</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="form-group">
                    <label for="email">E-posta Adresi:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Şifre (Tekrar):</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <button type="submit">Kayıt Ol</button>
            </form>
            <p>Zaten bir hesabınız var mı? <a href="login.php">Giriş Yapın</a></p>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bilet Satın Alma Platformu</p>
        </div>
    </footer>

</body>
</html>