<?php
// parent_payment_history.php
// Sejarah Pembayaran - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Dapatkan parent_id
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Ambil sejarah pembayaran parent ini sahaja
$sql_payments = "SELECT p.*, i.invoice_number, i.amount AS invoice_amount, i.type AS invoice_type,
                 s.full_name AS student_name
                 FROM payments p 
                 INNER JOIN invoices i ON p.invoice_id = i.id 
                 INNER JOIN students s ON i.student_id = s.id
                 WHERE p.parent_id = $parent_id 
                 ORDER BY p.payment_date DESC";
$payments = $conn->query($sql_payments);

// Statistik
$total_payments = 0;
$verified_count = 0;
$pending_count = 0;
$payment_list = array();
if ($payments) {
    while ($row = $payments->fetch_assoc()) {
        $payment_list[] = $row;
        $total_payments += $row['amount_paid'];
        if ($row['status'] == 'Verified') $verified_count++;
        if ($row['status'] == 'Pending') $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sejarah Pembayaran - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1100px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #77dd77; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 22px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .stat-number { font-size: 28px; font-weight: bold; }
        .stat-label { font-size: 13px; color: #888; margin-top: 5px; }

        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; }
        .badge-verified { background: #28a745; }
        .badge-pending { background: #ffb347; }
        .badge-rejected { background: #ff6961; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🧾 Sejarah Pembayaran</h2>
                <div class="subtitle">Rekod semua pembayaran yang telah dibuat</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number" style="color: #333;">RM <?php echo number_format($total_payments, 2); ?></div>
                <div class="stat-label">💰 Jumlah Dibayar</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #28a745;"><?php echo $verified_count; ?></div>
                <div class="stat-label">✅ Disahkan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" style="color: #ffb347;"><?php echo $pending_count; ?></div>
                <div class="stat-label">⏳ Menunggu Pengesahan</div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 15px; color: #333;">📋 Senarai Transaksi</h3>
            <table>
                <thead>
                    <tr>
                        <th>Tarikh</th>
                        <th>Invois</th>
                        <th>Anak</th>
                        <th>Jumlah (RM)</th>
                        <th>Kaedah</th>
                        <th>No. Rujukan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($payment_list) > 0): ?>
                        <?php for ($i = 0; $i < count($payment_list); $i++): ?>
                            <?php 
                                $p = $payment_list[$i];
                                $badge = 'badge-pending';
                                if ($p['status'] == 'Verified') $badge = 'badge-verified';
                                if ($p['status'] == 'Rejected') $badge = 'badge-rejected';
                            ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['payment_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['invoice_number'] ?? 'INV-' . $p['invoice_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['student_name']); ?></td>
                                <td><strong>RM <?php echo number_format($p['amount_paid'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($p['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($p['transaction_ref'] ?? '-'); ?></td>
                                <td><span class="badge <?php echo $badge; ?>"><?php echo $p['status']; ?></span></td>
                            </tr>
                        <?php endfor; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-state">Tiada rekod pembayaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
