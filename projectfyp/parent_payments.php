<?php
// parent_payments.php
// Pembayaran & Invois - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$msg = '';

// Dapatkan parent_id
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Proses bayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_payment'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $transaction_ref = $conn->real_escape_string($_POST['transaction_ref']);
    
    // Sahkan invois milik parent ini
    $verify = $conn->query("SELECT id, amount, status FROM invoices WHERE id = $invoice_id AND parent_id = $parent_id LIMIT 1");
    if ($verify && $verify->num_rows > 0) {
        $inv = $verify->fetch_assoc();
        if ($inv['status'] !== 'Paid') {
            // Upload resit
            $receipt = '';
            if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
                $target_dir = "uploads/receipts/";
                if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                $receipt = $target_dir . time() . "_" . basename($_FILES['receipt']['name']);
                move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt);
            }
            
            $amount = $inv['amount'];
            $sql_pay = "INSERT INTO payments (invoice_id, parent_id, amount_paid, payment_method, transaction_ref, receipt_file, status) 
                        VALUES ($invoice_id, $parent_id, $amount, '$payment_method', '$transaction_ref', '$receipt', 'Pending')";
            if ($conn->query($sql_pay)) {
                $msg = "<div class='alert success'>Pembayaran berjaya dihantar! Menunggu pengesahan admin.</div>";
            } else {
                $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
            }
        } else {
            $msg = "<div class='alert error'>Invois ini telah dibayar.</div>";
        }
    } else {
        $msg = "<div class='alert error'>Invois tidak sah atau bukan milik anda.</div>";
    }
}

// Ambil invois parent ini sahaja
$sql_invoices = "SELECT i.*, s.full_name AS student_name 
                 FROM invoices i 
                 INNER JOIN students s ON i.student_id = s.id 
                 WHERE i.parent_id = $parent_id 
                 ORDER BY i.created_at DESC";
$invoices = $conn->query($sql_invoices);

// Kira statistik
$total_pending = 0;
$total_paid = 0;
$total_overdue = 0;
$invoice_list = array();
if ($invoices) {
    while ($row = $invoices->fetch_assoc()) {
        $invoice_list[] = $row;
        if ($row['status'] == 'Pending') $total_pending += $row['amount'];
        if ($row['status'] == 'Paid') $total_paid += $row['amount'];
        if ($row['status'] == 'Overdue') $total_overdue += $row['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran & Invois - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1100px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #84b6f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }
        .alert { padding: 12px 15px; margin-bottom: 15px; border-radius: 8px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }

        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 22px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
        .stat-number { font-size: 28px; font-weight: bold; }
        .stat-label { font-size: 13px; color: #888; margin-top: 5px; }
        .stat-pending .stat-number { color: #ffb347; }
        .stat-paid .stat-number { color: #28a745; }
        .stat-overdue .stat-number { color: #ff6961; }

        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-size: 13px; color: #555; border-bottom: 2px solid #eee; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; }
        .badge-pending { background: #ffb347; }
        .badge-paid { background: #28a745; }
        .badge-overdue { background: #ff6961; }

        .btn-pay { background: #84b6f4; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .btn-pay:hover { background: #6a9bd8; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 500px; }
        .modal h3 { margin-bottom: 20px; color: #333; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .form-group select, .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        .btn-submit { background: #28a745; color: white; border: none; padding: 10px 30px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-close { background: #ccc; color: #333; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-left: 10px; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; }
    </style>
    <script>
        function openPayModal(invoiceId, amount, studentName) {
            document.getElementById('modal_invoice_id').value = invoiceId;
            document.getElementById('modal_info').innerHTML = '<strong>' + studentName + '</strong> - RM ' + parseFloat(amount).toFixed(2);
            document.getElementById('payModal').classList.add('active');
        }
        function closePayModal() {
            document.getElementById('payModal').classList.remove('active');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>💳 Pembayaran & Invois</h2>
                <div class="subtitle">Urus pembayaran yuran anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="stats-row">
            <div class="stat-card stat-pending">
                <div class="stat-number">RM <?php echo number_format($total_pending, 2); ?></div>
                <div class="stat-label">⏳ Belum Bayar</div>
            </div>
            <div class="stat-card stat-paid">
                <div class="stat-number">RM <?php echo number_format($total_paid, 2); ?></div>
                <div class="stat-label">✅ Telah Bayar</div>
            </div>
            <div class="stat-card stat-overdue">
                <div class="stat-number">RM <?php echo number_format($total_overdue, 2); ?></div>
                <div class="stat-label">⚠️ Tertunggak</div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-bottom: 15px; color: #333;">📄 Senarai Invois</h3>
            <table>
                <thead>
                    <tr>
                        <th>No. Invois</th>
                        <th>Anak</th>
                        <th>Jenis</th>
                        <th>Jumlah (RM)</th>
                        <th>Status</th>
                        <th>Tarikh</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($invoice_list) > 0): ?>
                        <?php for ($i = 0; $i < count($invoice_list); $i++): ?>
                            <?php 
                                $inv = $invoice_list[$i];
                                $badge = 'badge-pending';
                                if ($inv['status'] == 'Paid') $badge = 'badge-paid';
                                if ($inv['status'] == 'Overdue') $badge = 'badge-overdue';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inv['invoice_number'] ?? 'INV-' . $inv['id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($inv['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($inv['type'] ?? '-'); ?></td>
                                <td><strong>RM <?php echo number_format($inv['amount'], 2); ?></strong></td>
                                <td><span class="badge <?php echo $badge; ?>"><?php echo $inv['status']; ?></span></td>
                                <td><?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></td>
                                <td>
                                    <?php if ($inv['status'] !== 'Paid'): ?>
                                        <button class="btn-pay" onclick="openPayModal(<?php echo $inv['id']; ?>, <?php echo $inv['amount']; ?>, '<?php echo htmlspecialchars($inv['student_name'], ENT_QUOTES); ?>')">💳 Bayar</button>
                                    <?php else: ?>
                                        <span style="color: #28a745; font-size: 12px;">✅ Selesai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endfor; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-state">Tiada invois direkodkan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Pembayaran -->
    <div class="modal-overlay" id="payModal">
        <div class="modal">
            <h3>💳 Buat Pembayaran</h3>
            <div id="modal_info" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px;"></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="invoice_id" id="modal_invoice_id">
                <div class="form-group">
                    <label>Kaedah Pembayaran</label>
                    <select name="payment_method" required>
                        <option value="FPX">FPX (Online Banking)</option>
                        <option value="Manual Transfer">Pindahan Manual (ATM)</option>
                        <option value="Cash">Tunai</option>
                        <option value="Online">Online (E-wallet)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>No. Rujukan Transaksi</label>
                    <input type="text" name="transaction_ref" placeholder="Cth: FPX-20260520-001">
                </div>
                <div class="form-group">
                    <label>Muat Naik Resit (Pilihan)</label>
                    <input type="file" name="receipt" accept=".jpg,.png,.pdf">
                </div>
                <button type="submit" name="make_payment" class="btn-submit">✅ Hantar Pembayaran</button>
                <button type="button" class="btn-close" onclick="closePayModal()">Batal</button>
            </form>
        </div>
    </div>
</body>
</html>
