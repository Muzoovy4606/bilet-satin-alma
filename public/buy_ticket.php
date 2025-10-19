<?php
require_once __DIR__ . '/../src/auth.php';
require_user();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers.php';

$error = null;
$success = null;
$trip = null;
$booked_seats = [];
$user_balance = 0;
$trip_id = $_GET['trip_id'] ?? $_POST['trip_id'] ?? null;

$user_id = $_SESSION['user_id'];

if (!$trip_id) {
    header("Location: index.php");
    exit;
}

try {
    $stmt_trip = $pdo->prepare("
        SELECT
            t.*,
            b.name AS company_name,
            b.logo_path
        FROM Trips t
        JOIN Bus_Company b ON t.company_id = b.id
        WHERE t.id = ? AND t.departure_time > CURRENT_TIMESTAMP
    ");
    $stmt_trip->execute([$trip_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    if (!$trip) {
        $error = "Sefer bulunamadı veya seferin tarihi geçmiş.";
    } else {
        $stmt_user = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user_balance = $stmt_user->fetchColumn();

        $stmt_seats = $pdo->prepare("
            SELECT bs.seat_number
            FROM Booked_Seats bs
            JOIN Tickets t ON bs.ticket_id = t.id
            WHERE t.trip_id = ? AND t.status = 'active'
        ");
        $stmt_seats->execute([$trip_id]);
        $booked_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && $trip) {

        $selected_seats = $_POST['seats'] ?? [];
        $coupon_code = trim($_POST['coupon_code'] ?? '');

        if (empty($selected_seats)) {
            throw new Exception("Lütfen en az bir koltuk seçin.");
        }

        $pdo->beginTransaction();

        try {
            $placeholders = rtrim(str_repeat('?,', count($selected_seats)), ',');
            $stmt_check_seats = $pdo->prepare("
                SELECT bs.seat_number FROM Booked_Seats bs
                JOIN Tickets t ON bs.ticket_id = t.id
                WHERE t.trip_id = ? AND t.status = 'active' AND bs.seat_number IN ($placeholders)
            ");
            $params = array_merge([$trip_id], $selected_seats);
            $stmt_check_seats->execute($params);

            $already_taken = $stmt_check_seats->fetch(PDO::FETCH_COLUMN);
            if ($already_taken) {
                throw new Exception("Seçtiğiniz koltuklardan biri (" . $already_taken . " numara) siz işlemi tamamlarken başkası tarafından satın alındı. Lütfen tekrar deneyin.");
            }

            $base_price = $trip['price'];
            $seat_count = count($selected_seats);
            $total_price = $base_price * $seat_count;
            $discount_amount = 0;
            $coupon_id_to_use = null;

            if (!empty($coupon_code)) {
                $stmt_coupon = $pdo->prepare("
                    SELECT * FROM Coupons
                    WHERE code = ?
                    AND expire_date > CURRENT_TIMESTAMP
                    AND (company_id = ? OR company_id IS NULL)
                ");
                $stmt_coupon->execute([$coupon_code, $trip['company_id']]);
                $coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

                if (!$coupon) { throw new Exception("Geçersiz veya bu sefer için uygun olmayan kupon kodu."); }

                $stmt_usage = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ?");
                $stmt_usage->execute([$coupon['id']]);
                $coupon_usage_count = $stmt_usage->fetchColumn();
                if ($coupon_usage_count >= $coupon['usage_limit']) { throw new Exception("Kuponun kullanım limiti dolmuş."); }

                $stmt_user_usage = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE coupon_id = ? AND user_id = ?");
                $stmt_user_usage->execute([$coupon['id'], $user_id]);
                if($stmt_user_usage->fetchColumn() > 0) { throw new Exception("Bu kupon kodunu zaten daha önce kullanmışsınız."); }

                $discount_amount = $total_price * $coupon['discount'];
                $total_price = $total_price - $discount_amount;
                $coupon_id_to_use = $coupon['id'];
            }

            $stmt_balance = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
            $stmt_balance->execute([$user_id]);
            $current_balance = $stmt_balance->fetchColumn();

            if ($current_balance < $total_price) {
                throw new Exception("Yetersiz bakiye. Mevcut bakiyeniz: ". $current_balance . " TL, Gereken: " . $total_price . " TL");
            }

            $stmt_update_balance = $pdo->prepare("UPDATE User SET balance = balance - ? WHERE id = ?");
            $stmt_update_balance->execute([$total_price, $user_id]);

            $new_ticket_id = generate_uuid_v4();
            $stmt_create_ticket = $pdo->prepare("INSERT INTO Tickets (id, trip_id, user_id, total_price, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt_create_ticket->execute([$new_ticket_id, $trip_id, $user_id, $total_price]);

            $stmt_book_seat = $pdo->prepare("INSERT INTO Booked_Seats (id, ticket_id, seat_number) VALUES (?, ?, ?)");
            foreach ($selected_seats as $seat) {
                $stmt_book_seat->execute([generate_uuid_v4(), $new_ticket_id, $seat]);
            }

            if ($coupon_id_to_use) {
                $stmt_use_coupon = $pdo->prepare("INSERT INTO User_Coupons (id, coupon_id, user_id) VALUES (?, ?, ?)");
                $stmt_use_coupon->execute([generate_uuid_v4(), $coupon_id_to_use, $user_id]);
            }

            $pdo->commit();

            $_SESSION['success_message'] = "Biletiniz başarıyla satın alındı! (Ticket ID: " . $new_ticket_id . ")";
            header("Location: account.php");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "İşlem Başarısız: " . $e->getMessage();

            $stmt_user->execute([$user_id]);
            $user_balance = $stmt_user->fetchColumn();
            $stmt_seats->execute([$trip_id]);
            $booked_seats = $stmt_seats->fetchAll(PDO::FETCH_COLUMN);
        }
    }

} catch (PDOException $e) {
    $error = "Veritabanı bağlantı hatası: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilet Satın Al</title>
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
        <div class="buy-ticket-container">

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($trip): ?>
                <section class="trip-summary">
                    <h2>Sefer Detayları</h2>
                    <div class="trip-card">
                         <div class="company-info summary">
                            <?php if (!empty($trip['logo_path']) && file_exists(__DIR__ . $trip['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($trip['logo_path']); ?>" alt="<?php echo htmlspecialchars($trip['company_name']); ?> Logo" class="company-logo small">
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
                            (Koltuk Başına)
                        </div>
                    </div>
                </section>

                <form action="buy_ticket.php" method="POST" class="seat-form">
                    <input type="hidden" name="trip_id" value="<?php echo htmlspecialchars($trip['id']); ?>">

                    <section class="seat-selection">
                        <h3>Koltuk Seçimi</h3>
                        <p>Mevcut Bakiyeniz: <strong><?php echo htmlspecialchars($user_balance); ?> TL</strong></p>
                        <div class="seat-map-container">

                            <div class="bus-layout">
                                <div class="bus-front">ŞOFÖR</div>
                                <div class="seat-map bus-grid">
                                    <?php
                                        $seats_per_row = 4;
                                        for ($i = 1; $i <= $trip['capacity']; $i++):
                                            $is_booked = in_array($i, $booked_seats);
                                            $position_in_row = ($i - 1) % $seats_per_row + 1;
                                            $seat_position_class = '';
                                            if ($position_in_row == 1) $seat_position_class = 'seat-lw';
                                            elseif ($position_in_row == 2) $seat_position_class = 'seat-la';
                                            elseif ($position_in_row == 3) $seat_position_class = 'seat-ra';
                                            elseif ($position_in_row == 4) $seat_position_class = 'seat-rw';
                                    ?>
                                        <div class="seat <?php echo $seat_position_class; ?> <?php echo $is_booked ? 'disabled' : ''; ?>">
                                            <input
                                                type="checkbox"
                                                id="seat-<?php echo $i; ?>"
                                                name="seats[]"
                                                value="<?php echo $i; ?>"
                                                <?php echo $is_booked ? 'disabled' : ''; ?>
                                            >
                                            <label for="seat-<?php echo $i; ?>"><?php echo $i; ?></label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <ul class="seat-legend">
                                <li><span class="seat"></span> Boş</li>
                                <li><span class="seat selected"></span> Seçili</li>
                                <li><span class="seat disabled"><label>X</label></span> Dolu</li>
                            </ul>
                        </div>
                    </section>

                    <section class="payment-section">
                        <h3>Ödeme</h3>
                        <div class="form-group">
                            <label for="coupon_code">İndirim Kuponu:</label>
                            <input type="text" id="coupon_code" name="coupon_code" placeholder="Varsa kupon kodunuzu girin">
                        </div>
                        <div class="total-price">
                            Toplam Tutar: <strong id="total-price-display">0 TL</strong>
                        </div>
                        <button type="submit" class="buy-button-large">Satın Al</button>
                    </section>
                </form>

            <?php else: ?>
                <p>Sefer bilgileri yüklenemedi. Lütfen <a href="index.php">ana sayfaya</a> dönüp tekrar deneyin.</p>
            <?php endif; ?>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($trip): ?>
                const basePrice = <?php echo $trip['price']; ?>;
                const seatCheckboxes = document.querySelectorAll('.seat-map input[type="checkbox"]');
                const totalPriceDisplay = document.getElementById('total-price-display');

                function updateTotalPrice() {
                    const selectedSeats = document.querySelectorAll('.seat-map input[type="checkbox"]:checked:not(:disabled)');
                    const total = selectedSeats.length * basePrice;
                    totalPriceDisplay.textContent = total + ' TL';
                }

                seatCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateTotalPrice);
                });

                updateTotalPrice();
            <?php endif; ?>
        });
    </script>

    <footer>
        <p>&copy; 2025 Bilet Satın Alma Platformu</p>
    </footer>

</body>
</html>