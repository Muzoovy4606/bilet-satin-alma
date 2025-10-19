<?php
require_once __DIR__ . '/../src/auth.php';
require_auth();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/tfpdf.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    die("Bilet ID'si belirtilmedi.");
}

try {
    $stmt = $pdo->prepare("
        SELECT
            T.id as ticket_id,
            T.total_price,
            T.status,
            Tr.departure_city,
            Tr.destination_city,
            Tr.departure_time,
            Tr.arrival_time,
            BC.name as company_name,
            BC.logo_path,
            GROUP_CONCAT(BS.seat_number, ', ') as seat_numbers,
            U.full_name as passenger_name,
            U.email as passenger_email
        FROM Tickets T
        JOIN Trips Tr ON T.trip_id = Tr.id
        JOIN Bus_Company BC ON Tr.company_id = BC.id
        LEFT JOIN Booked_Seats BS ON BS.ticket_id = T.id
        JOIN User U ON T.user_id = U.id
        WHERE T.id = ? AND T.user_id = ?
        GROUP BY T.id
    ");
    $stmt->execute([$ticket_id, $user_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        die("Bilet bulunamadı veya bu bileti görüntüleme yetkiniz yok.");
    }

    $pdf = new tFPDF('P', 'mm', 'A4');

    $pdf->AddFont('DejaVu', '', 'DejaVuSans.ttf', true);
    $defaultFontSize = 14;
    $pdf->SetFont('DejaVu', '', $defaultFontSize);

    $pdf->AddPage();

    if ($ticket['status'] == 'cancelled') {
        $pdf->SetFont('DejaVu', '', 16);
        $pdf->SetTextColor(220, 53, 69);
        $pdf->Cell(0, 10, '*** BU BILET IPTAL EDILMISTIR ***', 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('DejaVu', '', $defaultFontSize);
        $pdf->Ln(5);
    }

    $pdf->SetFont('DejaVu', '', 20);
    $pdf->Cell(0, 10, 'Otobüs Biletiniz', 0, 1, 'C');
    $pdf->Ln(10);

    // Logo ekleme
    $logo_path_relative = $ticket['logo_path'] ?? null;
    $logo_path_full = null;
    if ($logo_path_relative) {
        // public klasörünün içindeki yolu oluştur
        $logo_path_full = __DIR__ . '/../public' . $logo_path_relative;
        if (!file_exists($logo_path_full)) {
            $logo_path_full = null; // Dosya yoksa null yap
        }
    }

    // Firma adını yazdır
    $pdf->SetFont('DejaVu', '', 16);
    $pdf->Cell(0, 10, $ticket['company_name'], 0, 1, 'C');
    $pdf->Ln(5);

    // Eğer logo varsa, firma adından sonra ekle (sağ üst köşe)
    if ($logo_path_full) {
        try {
            // Logo boyutları (genişlik 30mm, yükseklik otomatik ayarlanır)
            // Konum: Sağdan 10mm, yukarıdan 10mm
             $pdf->Image($logo_path_full, $pdf->GetPageWidth() - 40, 10, 30);
        } catch (Exception $imgEx) {
             // Logoya erişilemezse hata verme, devam et
        }
    }
     // Fontu önceki bilgiye geri ayarla (bilet detayları için)
     $pdf->SetFont('DejaVu', '', 12);
     $pdf->Ln(5); // Logonun altına boşluk ekle


    $pdf->Cell(40, 10, 'Yolcu Adı:', 0, 0);
    $pdf->Cell(0, 10, $ticket['passenger_name'], 0, 1);

    $pdf->Cell(40, 10, 'Yolcu E-posta:', 0, 0);
    $pdf->Cell(0, 10, $ticket['passenger_email'], 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('DejaVu', '', 14);
    $pdf->Cell(0, 10, 'Sefer Bilgileri', 'B', 1);
    $pdf->Ln(5);

    $pdf->SetFont('DejaVu', '', 12);
    $pdf->Cell(40, 10, 'Kalkış Yeri:', 0, 0);
    $pdf->Cell(0, 10, $ticket['departure_city'], 0, 1);

    $pdf->Cell(40, 10, 'Varış Yeri:', 0, 0);
    $pdf->Cell(0, 10, $ticket['destination_city'], 0, 1);

    $pdf->Cell(40, 10, 'Kalkış Saati:', 0, 0);
    $pdf->Cell(0, 10, date('d.m.Y H:i', strtotime($ticket['departure_time'])), 0, 1);

    $pdf->Cell(40, 10, 'Tahmini Varış:', 0, 0);
    $pdf->Cell(0, 10, date('d.m.Y H:i', strtotime($ticket['arrival_time'])), 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('DejaVu', '', 14);
    $pdf->Cell(0, 10, 'Bilet Bilgileri', 'B', 1);
    $pdf->Ln(5);

    $pdf->SetFont('DejaVu', '', 12);
    $pdf->Cell(40, 10, 'Koltuk No:', 0, 0);
    $pdf->Cell(0, 10, $ticket['seat_numbers'], 0, 1);

    $pdf->SetFont('DejaVu', '', 14);
    $pdf->Cell(40, 10, 'Ödenen Tutar:', 0, 0);
    $pdf->SetFont('DejaVu', '', 16);
    $pdf->Cell(0, 10, number_format($ticket['total_price'], 2, ',', '.') . ' TL', 0, 1);

    $pdf->Ln(15);
    $pdf->SetFont('DejaVu', '', 10);
    $pdf->Cell(0, 10, 'İyi yolculuklar dileriz!', 0, 1, 'C');

    $pdf_file_name = 'Bilet-' . $ticket['departure_city'] . '-' . $ticket['ticket_id'] . '.pdf';
    if ($ticket['status'] == 'cancelled') {
         $pdf_file_name = 'IPTAL-' . $pdf_file_name;
    }
    $pdf->Output('D', $pdf_file_name);
    exit;

} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Veritabanı hatası: " . $e->getMessage());
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    die("PDF oluşturma hatası: " . $e->getMessage());
}
?>