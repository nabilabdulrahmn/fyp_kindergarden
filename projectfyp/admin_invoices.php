<?php
// admin_invoices.php
// Penjanaan Invois & Bil - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Janakan Invois Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_invoice'])) {
    $parent_id = (int)$_POST['parent_id'];
    $student_id = (int)$_POST['student_id'];
    $fee_id = (int)$_POST['fee_id'];
    $invoice_number = "INV-" . time();
    
    // Ambil amount & nama yuran dari fee_structures
    $fee_result = $conn->query("SELECT amount, fee_name FROM fee_structures WHERE id = $fee_id");
    if ($fee_result && $fee_result->num_rows > 0) {
        $row_fee = $fee_result->fetch_assoc();
        $amount = $row_fee['amount'];
        $type = $conn->real_escape_string($row_fee['fee_name']);
        
        $sql = "INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, status) 
                VALUES ($parent_id, $student_id, '$invoice_number', $amount, '$type', 'Pending')";
        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Invois baru berjaya dijana!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat Sistem: " . $conn->error . "</div>";
        }
    } else {
        $msg = "<div class='alert error'>Ralat: Struktur yuran tidak sah.</div>";
    }
}

// Ambil data untuk dropdown (Ibu bapa, Pelajar, Struktur Yuran)
$parents = $conn->query("SELECT id, full_name AS parent_name FROM parents ORDER BY full_name");
$students = $conn->query("SELECT id, full_name, module FROM students ORDER BY full_name");
$fees = $conn->query("SELECT id, module, fee_name, amount FROM fee_structures WHERE is_active = 1");

// Ambil rekod invois sedia ada
$sql_invoices = "SELECT i.*, p.full_name AS parent_name, s.full_name
                 FROM invoices i
                 JOIN parents p ON i.parent_id = p.id
                 JOIN students s ON i.student_id = s.id
                 ORDER BY i.created_at DESC";
$invoices_result = $conn->query($sql_invoices);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Penjanaan Invois - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #9c27b0; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        select, input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #9c27b0; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #7b1fa2; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-unpaid { background: #f44336; }
        .bg-paid { background: #4caf50; }
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🧾 Penjanaan Invois & Bil Kewangan</h2>
        <?php echo $msg; ?>
        
        <div style="background: #f3e5f5; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #e1bee7;">
            <h3 style="margin-top:0; color:#7b1fa2;">Jana Invois Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Pilih Ibu Bapa / Penjaga</label>
                        <select name="parent_id" required>
                            <option value="">-- Sila Pilih --</option>
                            <?php while($p = $parents->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['parent_name']}</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pilih Pelajar</label>
                        <select name="student_id" required>
                            <option value="">-- Sila Pilih --</option>
                            <?php while($s = $students->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['module']})</option>"; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Pilih Jenis Yuran</label>
                        <select name="fee_id" required>
                            <option value="">-- Sila Pilih --</option>
                            <?php while($f = $fees->fetch_assoc()) echo "<option value='{$f['id']}'>[{$f['module']}] {$f['fee_name']} - RM {$f['amount']}</option>"; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="generate_invoice">Jana Invois Sekarang</button>
            </form>
        </div>

        <h3>Rekod Invois Berdaftar</h3>
        <table>
            <thead>
                <tr>
                    <th>No. Invois</th>
                    <th>Penjaga & Pelajar</th>
                    <th>Butiran Yuran</th>
                    <th>Jumlah (RM)</th>
                    <th>Status Bayaran</th>
                    <th>Tarikh Dijana</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                    <?php while ($row = $invoices_result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight:bold; color:#7b1fa2;"><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['parent_name']); ?></strong><br>
                                <span style="font-size:12px; color:#666;">Pelajar: <?php echo htmlspecialchars($row['full_name']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td style="color:#d32f2f; font-weight:bold;">RM <?php echo number_format($row['amount'], 2); ?></td>
                            <td>
                                <?php if ($row['status'] == 'Paid'): ?>
                                    <span class="badge bg-paid">Lunas (Paid)</span>
                                <?php else: ?>
                                    <span class="badge bg-unpaid">Tertunggak (Unpaid)</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">Tiada rekod invois dijumpai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
