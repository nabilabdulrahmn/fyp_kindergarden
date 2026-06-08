<?php
// view_invoice.php
// Paparan Cetakan Invois Rasmi - Print-ready A4 Page
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['id'])) {
    die("ID Invois tidak dinyatakan.");
}

$invoice_id = (int)$_GET['id'];

// Ambil maklumat invois
$sql = "SELECT i.*, p.full_name AS parent_name, p.address AS parent_address, p.phone_number AS parent_phone,
               s.full_name AS student_name, s.module AS student_module, p.user_id AS parent_user_id
        FROM invoices i
        JOIN parents p ON i.parent_id = p.id
        JOIN students s ON i.student_id = s.id
        WHERE i.id = $invoice_id LIMIT 1";

$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    die("Invois tidak dijumpai.");
}

$inv = $result->fetch_assoc();

// Sekuriti: Ibu bapa hanya boleh lihat invois mereka sendiri
if ($role === 'parent' && (int)$inv['parent_user_id'] !== $user_id) {
    die("Akses dinafikan. Anda tidak dibenarkan melihat invois ini.");
}

// Parse barangan invois (Itemized items) jika ada
$items = [];
if (!empty($inv['items_json'])) {
    $items = json_decode($inv['items_json'], true);
}

// Jika tiada barangan dalam json, bina dari type sedia ada
if (empty($items)) {
    $items[] = [
        'description' => $inv['type'] ?: 'Yuran Pembelajaran',
        'amount' => $inv['amount']
    ];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Invois <?php echo htmlspecialchars($inv['invoice_number']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f0f0f0; margin: 0; padding: 20px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; background: #fff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); border-radius: 8px; position: relative; }
        
        /* Watermark Status */
        .status-watermark { position: absolute; top: 35%; left: 30%; transform: rotate(-30deg); font-size: 80px; font-weight: bold; opacity: 0.08; text-transform: uppercase; pointer-events: none; width: 400px; text-align: center; }
        .watermark-pending { color: #ff9800; border: 10px solid #ff9800; border-radius: 20px; }
        .watermark-paid { color: #4caf50; border: 10px solid #4caf50; border-radius: 20px; }
        .watermark-overdue { color: #f44336; border: 10px solid #f44336; border-radius: 20px; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .header-table td { vertical-align: top; border: none; }
        
        .logo-section h2 { margin: 0; color: #333093; font-size: 26px; font-weight: 700; letter-spacing: -0.5px; }
        .logo-section p { margin: 3px 0; font-size: 12px; color: #666; }
        
        .invoice-title { text-align: right; }
        .invoice-title h1 { margin: 0 0 5px 0; color: #333093; font-size: 28px; }
        .invoice-title p { margin: 2px 0; font-size: 13px; color: #555; }
        
        .divider { border-top: 2px solid #333093; margin: 20px 0; opacity: 0.15; }
        
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .details-table td { width: 50%; vertical-align: top; border: none; font-size: 13px; line-height: 1.6; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: #333093; color: #fff; padding: 10px; font-size: 12px; text-align: left; text-transform: uppercase; font-weight: 600; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        .items-table tr:last-child td { border-bottom: 2px solid #333093; }
        
        .summary-table { width: 40%; margin-left: 60%; border-collapse: collapse; font-size: 14px; }
        .summary-table td { padding: 8px 5px; }
        .summary-table .total-row { font-weight: bold; font-size: 16px; color: #333093; border-top: 2px solid #333093; }

        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
        
        .no-print-bar { max-width: 800px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .btn-print { background: #333093; color: white; }
        .btn-print:hover { background: #232070; }
        .btn-back { background: #e0e0e0; color: #333; }
        .btn-back:hover { background: #d0d0d0; }

        @media print {
            body { background: #fff; padding: 0; }
            .invoice-box { border: none; box-shadow: none; padding: 0; }
            .no-print-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button onclick="window.history.back();" class="btn btn-back">⬅️ Kembali</button>
        <button onclick="window.print();" class="btn btn-print">🖨️ Cetak Invois (A4)</button>
    </div>

    <div class="invoice-box">
        <!-- Watermark Status -->
        <?php if ($inv['status'] == 'Paid'): ?>
            <div class="status-watermark watermark-paid">LUNAS / PAID</div>
        <?php elseif ($inv['status'] == 'Overdue'): ?>
            <div class="status-watermark watermark-overdue">TERTUNGGAK</div>
        <?php else: ?>
            <div class="status-watermark watermark-pending">PENDING</div>
        <?php endif; ?>

        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-section">
                    <h2>TASKA CARE CENTRE</h2>
                    <p>Sistem Pengurusan Kewangan Bersepadu</p>
                    <p>123, Jalan Taman Melati, Kuala Lumpur</p>
                    <p>Hubungi: +603-4100 0000 | Email: finance@taskacare.com</p>
                </td>
                <td class="invoice-title">
                    <h1>INVOIS</h1>
                    <p><strong>No. Invois:</strong> <?php echo htmlspecialchars($inv['invoice_number']); ?></p>
                    <p><strong>Tarikh:</strong> <?php echo date('d M Y', strtotime($inv['created_at'])); ?></p>
                    <p><strong>Status:</strong> 
                        <span style="font-weight: bold; color: <?php echo $inv['status'] === 'Paid' ? '#2e7d32' : ($inv['status'] === 'Overdue' ? '#c62828' : '#e65100'); ?>">
                            <?php echo $inv['status'] === 'Paid' ? 'LUNAS (Paid)' : ($inv['status'] === 'Overdue' ? 'TERTUNGGAK (Overdue)' : 'BELUM BAYAR (Pending)'); ?>
                        </span>
                    </p>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Details -->
        <table class="details-table">
            <tr>
                <td>
                    <h3 style="margin: 0 0 8px 0; color: #333093;">Kepada:</h3>
                    <strong><?php echo htmlspecialchars($inv['parent_name']); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($inv['parent_address'] ?: 'Tiada Alamat')); ?><br>
                    Tel: <?php echo htmlspecialchars($inv['parent_phone'] ?: '-'); ?>
                </td>
                <td>
                    <h3 style="margin: 0 0 8px 0; color: #333093;">Butiran Pelajar:</h3>
                    <strong>Nama Pelajar:</strong> <?php echo htmlspecialchars($inv['student_name']); ?><br>
                    <strong>Program/Modul:</strong> <?php echo htmlspecialchars($inv['student_module']); ?><br>
                    <strong>Tarikh Akhir Bayar:</strong> <?php echo date('d M Y', strtotime($inv['created_at'] . ' + 14 days')); ?>
                </td>
            </tr>
        </table>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">No.</th>
                    <th style="width: 60%;">Butiran Perkhidmatan / Yuran</th>
                    <th style="text-align: right; width: 30%;">Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                $subtotal = 0;
                foreach ($items as $item): 
                    $subtotal += $item['amount'];
                ?>
                    <tr>
                        <td><?php echo $count++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td style="text-align: right; font-weight: 500;">RM <?php echo number_format($item['amount'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <table class="summary-table">
            <tr>
                <td>Subjumlah:</td>
                <td style="text-align: right;">RM <?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <tr class="total-row">
                <td>Jumlah Bersih:</td>
                <td style="text-align: right;">RM <?php echo number_format($inv['amount'], 2); ?></td>
            </tr>
        </table>

        <!-- Payment Instructions -->
        <div style="margin-top: 40px; padding: 15px; background: #fcfcff; border: 1px dashed #333093; border-radius: 6px; font-size: 12px; line-height: 1.5;">
            <h4 style="margin: 0 0 5px 0; color: #333093;">Arahan Pembayaran:</h4>
            1. Pindahan manual atau dalam talian (FPX) ke akaun <strong>Maybank: 5641-2345-6789 (Taska Care Centre)</strong>.<br>
            2. Selepas bayaran dibuat, sila muat naik resit/bukti bayaran melalui portal portal ibu bapa di menu <strong>Pembayaran & Invois</strong>.<br>
            3. Invois ini dijana secara komputer dan tidak memerlukan tanda tangan.
        </div>

        <div class="footer">
            Terima kasih kerana mempercayai perkhidmatan kami untuk pendidikan anak anda.
        </div>
    </div>

</body>
</html>
