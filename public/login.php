<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin_panel.php");
    } elseif ($_SESSION['user_role'] == 'company_admin') {
        header("Location: company_panel.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (empty($email) || empty($password)) {
        $error = "Lütfen e-posta ve şifrenizi girin.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM User WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['company_id'] = $user['company_id'];

                if ($user['role'] == 'admin') {
                    header("Location: admin_panel.php");
                } elseif ($user['role'] == 'company_admin') {
                    header("Location: company_panel.php");
                } else {
                    header("Location: index.php");
                }
                exit;

            } else {
                $error = "E-posta veya şifre hatalı.";
            }

        } catch (PDOException $e) {
            $error = "Giriş sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap</title>
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
            <h2>Kullanıcı Girişi</h2>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="email">E-posta Adresi:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Şifre:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Giriş Yap</button>
            </form>
            <p>Bir hesabınız yok mu? <a href="register.php">Kayıt Olun</a></p>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Bilet Satın Alma Platformu</p>
        </div>
    </footer>

</body>
</html>