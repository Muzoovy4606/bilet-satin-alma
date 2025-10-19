<?php
require_once __DIR__ . '/../src/auth.php';
require_user();
require_once __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'];
$error = null;
$success = null;
$user = null;
$tickets = [];

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_balance') {
        $card_number = preg_replace('/\s+/', '', $_POST['card_number'] ?? '');
        $expiry_month = $_POST['expiry_month'] ?? null;
        $expiry_year = $_POST['expiry_year'] ?? null;
        $cvv = $_POST['cvv'] ?? null;
        $amount_to_add = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

        $current_year = date('Y');
        $current_month = date('m');
        $expiry_valid = false;
        if ($expiry_year && $expiry_month) {
            if ($expiry_year > $current_year) {
                $expiry_valid = true;
            } elseif ($expiry_year == $current_year && $expiry_month >= $current_month) {
                $expiry_valid = true;
            }
        }

        if (empty($card_number) || empty($expiry_month) || empty($expiry_year) || empty($cvv) || $amount_to_add === false || $amount_to_add <= 0) {
            $error = "Lütfen tüm kart bilgilerini ve geçerli bir tutar girin.";
        } elseif (!ctype_digit($card_number) || strlen($card_number) !== 16) {
            $error = "Kart numarası 16 rakamdan oluşmalıdır.";
        } elseif (!$expiry_valid) {
            $error = "Kartın son kullanma tarihi geçmiş veya geçersiz.";
        } elseif (!ctype_digit($cvv) || strlen($cvv) !== 3) {
            $error = "CVV 3 rakamdan oluşmalıdır.";
        } else {
            $stmt_add = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
            $stmt_add->execute([$amount_to_add, $user_id]);

            if ($stmt_add->rowCount() > 0) {
                $_SESSION['balance_added_message'] = htmlspecialchars(number_format($amount_to_add, 2, ',', '.')) . " TL başarıyla hesabınıza eklendi.";
                header("Location: account.php");
                exit;
            } else {
                $error = "Bakiye eklenirken bir veritabanı hatası oluştu.";
            }
        }
    }
    if (isset($_SESSION['balance_added_message'])) {
         $success = $_SESSION['balance_added_message'];
         unset($_SESSION['balance_added_message']);
    }

    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'cancel') {
        $ticket_id_to_cancel = $_GET['ticket_id'] ?? null;

        if ($ticket_id_to_cancel) {
            $pdo->beginTransaction();
            try {
                $stmt_check = $pdo->prepare("
                    SELECT T.total_price, Tr.departure_time, T.status
                    FROM Tickets T
                    JOIN Trips Tr ON T.trip_id = Tr.id
                    WHERE T.id = ? AND T.user_id = ?
                ");
                $stmt_check->execute([$ticket_id_to_cancel, $user_id]);
                $ticket_to_cancel = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if (!$ticket_to_cancel) { throw new Exception("Bilet bulunamadı veya bu bilet size ait değil."); }
                if ($ticket_to_cancel['status'] !== 'active') { throw new Exception("Bu bilet zaten iptal edilmiş veya aktif değil."); }

                $departure_timestamp = strtotime($ticket_to_cancel['departure_time']);
                $current_timestamp = time();
                if (($departure_timestamp - $current_timestamp) < 3600) { throw new Exception("Kalkış saatine 1 saatten az bir süre kaldığı için bilet iptal edilemez."); }

                $stmt_cancel = $pdo->prepare("UPDATE Tickets SET status = 'cancelled' WHERE id = ?");
                $stmt_cancel->execute([$ticket_id_to_cancel]);

                $refund_amount = $ticket_to_cancel['total_price'];
                $stmt_refund = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
                $stmt_refund->execute([$refund_amount, $user_id]);

                $pdo->commit();
                $_SESSION['cancellation_message'] = "Bilet başarıyla iptal edildi. Tutar (" . htmlspecialchars(number_format($refund_amount, 2, ',', '.')) . " TL) hesabınıza iade edildi.";
                header("Location: account.php");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "İptal Hatası: " . $e->getMessage();
            }
        }
    }
    if (isset($_SESSION['cancellation_message'])) {
        $success = $_SESSION['cancellation_message'];
        unset($_SESSION['cancellation_message']);
    }

    $stmt_user = $pdo->prepare("SELECT full_name, email, balance FROM User WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

    $stmt_tickets = $pdo->prepare("
        SELECT
            T.id as ticket_id,
            T.status,
            T.total_price,
            Tr.departure_city,
            Tr.destination_city,
            Tr.departure_time,
            Tr.arrival_time,
            BC.name as company_name,
            BC.logo_path,
            GROUP_CONCAT(BS.seat_number, ', ') as seat_numbers
        FROM Tickets T
        JOIN Trips Tr ON T.trip_id = Tr.id
        JOIN Bus_Company BC ON Tr.company_id = BC.id
        LEFT JOIN Booked_Seats BS ON BS.ticket_id = T.id
        WHERE T.user_id = ?
        GROUP BY T.id
        ORDER BY Tr.departure_time DESC
    ");
    $stmt_tickets->execute([$user_id]);
    $tickets = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hesabım</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <div class="container">
            <h1><a href="index.php" class="logo">Otobüs Bileti Platformu</a></h1>
            <nav>
                <span>Hoşgeldin, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
                <a href="account.php">Hesabım / Biletler</a>
                <a href="logout.php">Çıkış Yap</a>
            </nav>
        </div>
    </header>

    <main class="container">

        <?php if ($error): ?><div class="message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="message success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="account-grid">

            <section class="user-profile">
                <h3>Profil Bilgilerim</h3>
                <div class="profile-card">
                    <?php if ($user): ?>
                        <p><strong>Ad Soyad:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p><strong>E-posta:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="balance">
                            <strong>Mevcut Bakiye:</strong>
                            <span><?php echo htmlspecialchars(number_format($user['balance'], 2, ',', '.')); ?> TL</span>
                        </p>
                    <?php else: ?>
                        <p>Kullanıcı bilgileri yüklenemedi.</p>
                    <?php endif; ?>
                </div>

                <div class="add-balance-form">
                    <h4>Hesaba Sanal Kredi Ekle (Simülasyon)</h4>
                    <form action="account.php" method="POST" class="card-form">
                        <input type="hidden" name="action" value="add_balance">

                        <div class="form-group">
                            <label for="card_number">Kart Numarası:</label>
                            <input type="text" id="card_number" name="card_number" required
                                   placeholder="---- ---- ---- ----"
                                   pattern="\d{16}" maxlength="16"
                                   oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                        </div>

                        <div class="form-row">
                            <div class="form-group expiry-group">
                                <label>Son Kullanma Tarihi:</label>
                                <div class="expiry-inputs">
                                    <select id="expiry_month" name="expiry_month" required>
                                        <option value="">Ay</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>">
                                                <?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="separator">/</span>
                                    <select id="expiry_year" name="expiry_year" required>
                                        <option value="">Yıl</option>
                                        <?php
                                        $current_year = date('Y');
                                        for ($y = $current_year; $y <= $current_year + 10; $y++): ?>
                                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group cvv-group">
                                <label for="cvv">CVV:</label>
                                <input type="text" id="cvv" name="cvv" required
                                       placeholder="---"
                                       pattern="\d{3}" maxlength="3"
                                       oninput="this.value = this.value.replace(/[^0-9.]/g, '');">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="amount">Eklenecek Tutar (TL):</label>
                            <input type="number" id="amount" name="amount" step="0.01" min="1" required placeholder="Örn: 50.00">
                        </div>

                        <button type="submit" class="button-small add-balance-button">Bakiyeyi Ekle</button>
                    </form>
                </div>
                </section>

            <section class="ticket-list-container">
                <h3>Satın Aldığım Biletler</h3>
                 <?php if (count($tickets) > 0): ?>
                    <?php
                    $current_time = time();
                    foreach ($tickets as $ticket):
                        $departure_time = strtotime($ticket['departure_time']);
                        $status_class = $ticket['status'];
                        $status_text = '';
                        if ($ticket['status'] == 'active' && $departure_time < $current_time) {
                            $status_text = 'Sefer Tamamlandı'; $status_class = 'completed';
                        } elseif ($ticket['status'] == 'active') { $status_text = 'Aktif Bilet';
                        } elseif ($ticket['status'] == 'cancelled') { $status_text = 'İptal Edildi'; }
                        $can_cancel = ($ticket['status'] == 'active' && ($departure_time - $current_time) > 3600);
                    ?>
                        <div class="user-ticket-card status-<?php echo htmlspecialchars($status_class); ?>">
                            <div class="ticket-header">
                                <div class="company-info ticket-header-company">
                                     <?php if (!empty($ticket['logo_path']) && file_exists(__DIR__ . $ticket['logo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($ticket['logo_path']); ?>" alt="<?php echo htmlspecialchars($ticket['company_name']); ?> Logo" class="company-logo tiny">
                                    <?php endif; ?>
                                    <strong><?php echo htmlspecialchars($ticket['company_name']); ?></strong>
                                </div>
                                <span class="ticket-status"><?php echo $status_text; ?></span>
                            </div>
                            <div class="ticket-body">
                                <div class="ticket-route">
                                    <p><strong>Kalkış:</strong> <?php echo htmlspecialchars($ticket['departure_city']); ?><br><span><?php echo date('d.m.Y H:i', $departure_time); ?></span></p>
                                    <p><strong>Varış:</strong> <?php echo htmlspecialchars($ticket['destination_city']); ?><br><span><?php echo date('d.m.Y H:i', strtotime($ticket['arrival_time'])); ?></span></p>
                                </div>
                                <div class="ticket-info">
                                    <p><strong>Koltuk No:</strong> <?php echo htmlspecialchars($ticket['seat_numbers']); ?></p>
                                    <p><strong>Ödenen Tutar:</strong> <?php echo htmlspecialchars(number_format($ticket['total_price'], 2, ',', '.')); ?> TL</p>
                                </div>
                            </div>
                            <div class="ticket-actions">
                                <a href="download_pdf.php?ticket_id=<?php echo $ticket['ticket_id']; ?>" class="action-button pdf" target="_blank">PDF İndir</a>
                                <?php if ($can_cancel): ?>
                                    <a href="account.php?action=cancel&ticket_id=<?php echo $ticket['ticket_id']; ?>" class="action-button delete" onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz? Tutar hesabınıza iade edilecektir.');">Bileti İptal Et</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Henüz satın aldığınız bir bilet bulunmuyor.</p>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 Bilet Satın Alma Platformu</p>
    </footer>

</body>
</html>