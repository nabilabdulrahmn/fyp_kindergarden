<?php
// view_receipt.php
// Paparan Cetakan Resit Rasmi - Print-ready A4 Page
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if (!isset($_GET['id'])) {
    die("ID Pembayaran tidak dinyatakan.");
}

$payment_id = (int)$_GET['id'];

// Ambil maklumat pembayaran, invois, dan parent
$sql = "SELECT p.*, i.invoice_number, i.type AS invoice_type, i.items_json,
               pr.full_name AS parent_name, pr.address AS parent_address, pr.phone_number AS parent_phone,
               s.full_name AS student_name, s.module AS student_module, pr.user_id AS parent_user_id,
               u.username AS verifier_name
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        JOIN parents pr ON p.parent_id = pr.id
        JOIN students s ON i.student_id = s.id
        LEFT JOIN users u ON p.verified_by = u.id
        WHERE p.id = $payment_id LIMIT 1";

$result = $conn->query($sql);
if (!$result || $result->num_rows == 0) {
    die("Rekod pembayaran tidak dijumpai.");
}

$pay = $result->fetch_assoc();

// Sekuriti: Ibu bapa hanya boleh lihat resit mereka sendiri
if ($role === 'parent' && (int)$pay['parent_user_id'] !== $user_id) {
    die("Akses dinafikan. Anda tidak dibenarkan melihat resit ini.");
}

// Hanya tunjuk resit jika status adalah Verified
if ($pay['status'] !== 'Verified' && $role !== 'admin') {
    die("Resit belum dijana atau pembayaran belum disahkan.");
}

// Parse itemized items
$items = [];
if (!empty($pay['items_json'])) {
    $items = json_decode($pay['items_json'], true);
}

if (empty($items)) {
    $items[] = [
        'description' => $pay['invoice_type'] ?: 'Yuran Pembelajaran',
        'amount' => $pay['amount_paid']
    ];
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Resit Rasmi RCP-<?php echo htmlspecialchars($pay['id']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; background: #f0f0f0; margin: 0; padding: 20px; }
        .receipt-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; background: #fff; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); border-radius: 8px; position: relative; }
        
        /* Digital Paid Stamp */
        .paid-stamp { position: absolute; top: 120px; right: 50px; border: 4px double #4caf50; color: #4caf50; font-family: 'Courier New', Courier, monospace; font-size: 20px; font-weight: bold; padding: 8px 15px; text-align: center; border-radius: 4px; transform: rotate(-8deg); background: rgba(76, 175, 80, 0.05); }
        .paid-stamp span { display: block; font-size: 11px; margin-top: 4px; color: #555; font-family: 'Segoe UI', sans-serif; font-weight: normal; }

        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .header-table td { vertical-align: top; border: none; }
        
        .logo-section h2 { margin: 0; color: #2e7d32; font-size: 26px; font-weight: 700; }
        .logo-section p { margin: 3px 0; font-size: 12px; color: #666; }
        
        .receipt-title { text-align: right; }
        .receipt-title h1 { margin: 0 0 5px 0; color: #2e7d32; font-size: 28px; }
        .receipt-title p { margin: 2px 0; font-size: 13px; color: #555; }
        
        .divider { border-top: 2px solid #2e7d32; margin: 20px 0; opacity: 0.15; }
        
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .details-table td { width: 50%; vertical-align: top; border: none; font-size: 13px; line-height: 1.6; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: #2e7d32; color: #fff; padding: 10px; font-size: 12px; text-align: left; text-transform: uppercase; font-weight: 600; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #eee; font-size: 13px; }
        .items-table tr:last-child td { border-bottom: 2px solid #2e7d32; }
        
        .summary-table { width: 40%; margin-left: 60%; border-collapse: collapse; font-size: 14px; }
        .summary-table td { padding: 8px 5px; }
        .summary-table .total-row { font-weight: bold; font-size: 16px; color: #2e7d32; border-top: 2px solid #2e7d32; }

        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #888; border-top: 1px solid #eee; padding-top: 15px; }
        
        .no-print-bar { max-width: 800px; margin: 0 auto 15px auto; display: flex; justify-content: space-between; align-items: center; }
        .btn { padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: bold; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .btn-print { background: #2e7d32; color: white; }
        .btn-print:hover { background: #1e5221; }
        .btn-back { background: #e0e0e0; color: #333; }
        .btn-back:hover { background: #d0d0d0; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt-box { border: none; box-shadow: none; padding: 0; }
            .no-print-bar { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <button onclick="window.history.back();" class="btn btn-back">⬅️ Kembali</button>
        <button onclick="window.print();" class="btn btn-print">🖨️ Cetak Resit Rasmi (A4)</button>
    </div>

    <div class="receipt-box">
        <!-- Digital Paid Stamp -->
        <div class="paid-stamp">
            LUNAS & DIKAWAL
            <span>PADA <?php echo date('d/m/Y', strtotime($pay['verified_at'] ?: $pay['payment_date'])); ?></span>
        </div>

        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo-section">
                    <h2>TASKA CARE CENTRE</h2>
                    <p>Sistem Pengurusan Kewangan Bersepadu</p>
                    <p>123, Jalan Taman Melati, Kuala Lumpur</p>
                    <p>Hubungi: +603-4100 0000 | Email: finance@taskacare.com</p>
                </td>
                <td class="receipt-title">
                    <h1>RESIT RASMI</h1>
                    <p><strong>No. Resit:</strong> RCP-<?php echo sprintf('%05d', $pay['id']); ?></p>
                    <p><strong>Rujukan Invois:</strong> <?php echo htmlspecialchars($pay['invoice_number']); ?></p>
                    <p><strong>Tarikh Bayaran:</strong> <?php echo date('d M Y', strtotime($pay['payment_date'])); ?></p>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <!-- Details -->
        <table class="details-table">
            <tr>
                <td>
                    <h3 style="margin: 0 0 8px 0; color: #2e7d32;">Diterima Daripada:</h3>
                    <strong><?php echo htmlspecialchars($pay['parent_name']); ?></strong><br>
                    <?php echo nl2br(htmlspecialchars($inv['parent_address'] ?? $pay['parent_address'] ?: 'Tiada Alamat')); ?><br>
                    Tel: <?php echo htmlspecialchars($pay['parent_phone'] ?: '-'); ?>
                </td>
                <td>
                    <h3 style="margin: 0 0 8px 0; color: #2e7d32;">Maklumat Transaksi:</h3>
                    <strong>Kaedah Bayaran:</strong> <?php echo htmlspecialchars($pay['payment_method']); ?><br>
                    <strong>Rujukan Transaksi:</strong> <?php echo htmlspecialchars($pay['transaction_ref'] ?: '-'); ?><br>
                    <strong>Anak / Pelajar:</strong> <?php echo htmlspecialchars($pay['student_name']); ?> (<?php echo htmlspecialchars($pay['student_module']); ?>)<br>
                    <strong>Disahkan Oleh:</strong> <?php echo htmlspecialchars($pay['verifier_name'] ?: 'Sistem'); ?>
                </td>
            </tr>
        </table>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">No.</th>
                    <th style="width: 60%;">Butiran Penerimaan Bayaran</th>
                    <th style="text-align: right; width: 30%;">Jumlah Diterima (RM)</th>
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
                <td>Jumlah Dibayar:</td>
                <td style="text-align: right;">RM <?php echo number_format($subtotal, 2); ?></td>
            </tr>
            <tr class="total-row">
                <td>Jumlah Diterima:</td>
                <td style="text-align: right;">RM <?php echo number_format($pay['amount_paid'], 2); ?></td>
            </tr>
        </table>

        <!-- Payment Instructions -->
        <div style="margin-top: 40px; text-align: left; font-size: 11px; line-height: 1.5; color: #666;">
            * Resit ini dijanakan secara digital selepas pengesahan transaksi oleh pihak pentadbir Taska Care Centre.<br>
            * Sila simpan resit ini sebagai bukti pembayaran rasmi yuran anda.
        </div>

        <div class="footer">
            Resit ini adalah dokumen sah dan dikeluarkan oleh Taska Care Centre. Terima Kasih!
        </div>
    </div>

</body>
</html>
