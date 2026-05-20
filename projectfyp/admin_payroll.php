<?php
// admin_payroll.php
// Sumber Manusia & Penggajian - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $staff_id = (int)$_POST['staff_id'];
    $month_input = (int)$_POST['month'];
    $year_input = (int)$_POST['year'];
    $month_str = sprintf("%04d-%02d", $year_input, $month_input);
    
    $basic_salary = (float)$_POST['basic_salary'];
    $allowances = (float)$_POST['allowances'];
    $deductions = (float)$_POST['deductions'];
    $net_salary = $basic_salary + $allowances - $deductions;
    
    $sql = "INSERT INTO payroll (staff_id, month, basic_salary, allowances, deductions, net_salary) 
            VALUES ($staff_id, '$month_str', $basic_salary, $allowances, $deductions, $net_salary)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Slip gaji berjaya diproses dan direkodkan.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Ambil staf aktif untuk dropdown
$active_staff = $conn->query("SELECT id, full_name, position FROM staff WHERE status = 'Active' ORDER BY full_name");

// Ambil sejarah gaji
$sql_payroll = "SELECT p.*, s.full_name, s.position 
                FROM payroll p
                JOIN staff s ON p.staff_id = s.id
                ORDER BY p.month DESC, s.full_name ASC";
$result = $conn->query($sql_payroll);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>HR & Penggajian - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #009688; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #009688; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #00796b; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-paid { background: #4caf50; }
        .bg-pending { background: #ff9800; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>💼 Penggajian & Sumber Manusia (Payroll)</h2>
        <?php echo $msg; ?>
        
        <div style="background: #e0f2f1; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #b2dfdb;">
            <h3 style="margin-top:0; color:#00695c;">Proses Pembayaran Gaji</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Pilih Staf</label>
                        <select name="staff_id" required>
                            <option value="">-- Sila Pilih Staf Aktif --</option>
                            <?php while($s = $active_staff->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['position']})</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bulan Penggajian</label>
                        <select name="month" required>
                            <?php for($m=1; $m<=12; ++$m) echo "<option value='$m' ".($m == date('n') ? 'selected' : '').">".date('F', mktime(0, 0, 0, $m, 1))."</option>"; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Gaji Pokok (RM)</label><input type="number" step="0.01" name="basic_salary" required placeholder="Cth: 1500.00"></div>
                    <div class="form-group"><label>Elaun (RM)</label><input type="number" step="0.01" name="allowances" value="0.00"></div>
                    <div class="form-group"><label>Potongan (RM)</label><input type="number" step="0.01" name="deductions" value="0.00"></div>
                </div>
                <button type="submit" name="process_payroll">Rekod & Jana Slip Gaji</button>
            </form>
        </div>

        <h3>Rekod Penggajian (Sejarah)</h3>
        <table>
            <thead>
                <tr>
                    <th>Bulan/Tahun</th>
                    <th>Nama Staf</th>
                    <th>Perincian (Pokok + Elaun - Potongan)</th>
                    <th>Gaji Bersih (RM)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($row['month']."-01")); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['position']); ?></span>
                            </td>
                            <td style="font-size: 12px;">
                                Pokok: <?php echo number_format($row['basic_salary'], 2); ?><br>
                                Elaun: <?php echo number_format($row['allowances'], 2); ?><br>
                                Potongan: <?php echo number_format($row['deductions'], 2); ?>
                            </td>
                            <td style="color: #2e7d32; font-weight: bold; font-size:16px;">RM <?php echo number_format($row['net_salary'], 2); ?></td>
                            <td>
                                <?php if ($row['payment_status'] == 'Paid'): ?>
                                    <span class="badge bg-paid">Telah Dibayar</span>
                                <?php else: ?>
                                    <span class="badge bg-pending">Tertunggak</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod gaji dijumpai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
