<?php
// admin_expenses.php
// Pelaporan Perbelanjaan & Kewangan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Tambah Perbelanjaan Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    
    $sql = "INSERT INTO expenses (category, description, amount, expense_date, recorded_by) 
            VALUES ('$category', '$description', $amount, '$expense_date', $user_id)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Rekod perbelanjaan berjaya ditambah.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Ambil Rekod Perbelanjaan
$sql_expenses = "SELECT e.*, u.username FROM expenses e LEFT JOIN users u ON e.recorded_by = u.id ORDER BY e.expense_date DESC";
$result = $conn->query($sql_expenses);

// Kira Jumlah Perbelanjaan
$total_expenses = 0;
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Perbelanjaan & Kewangan - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #f44336; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="date"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #f44336; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #d32f2f; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 Laporan Perbelanjaan & Kewangan</h2>
        <?php echo $msg; ?>
        
        <div style="background: #ffebee; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffcdd2;">
            <h3 style="margin-top:0; color:#c62828;">Rekod Perbelanjaan Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category" required>
                            <option value="Operasi">Operasi (Air, Elektrik, Sewa)</option>
                            <option value="Barangan Runcit & Makanan">Barangan Runcit & Makanan</option>
                            <option value="Alat Tulis & BBM">Alat Tulis & Bahan Mengajar</option>
                            <option value="Penyelenggaraan">Penyelenggaraan & Pembaikan</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tarikh Perbelanjaan</label>
                        <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Butiran / Penerangan</label>
                        <input type="text" name="description" required placeholder="Cth: Beli barang mentah untuk dapur">
                    </div>
                    <div class="form-group">
                        <label>Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="Cth: 150.50">
                    </div>
                </div>
                <button type="submit" name="add_expense">Simpan Rekod Perbelanjaan</button>
            </form>
        </div>

        <h3>Sejarah Perbelanjaan</h3>
        <table>
            <thead>
                <tr>
                    <th>Tarikh</th>
                    <th>Kategori</th>
                    <th>Butiran</th>
                    <th>Direkod Oleh</th>
                    <th>Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $total_expenses += $row['amount'];
                    ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['expense_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['category']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td style="font-size: 12px; color: #777;"><?php echo htmlspecialchars($row['username']); ?></td>
                            <td style="color: #d32f2f; font-weight: bold;">RM <?php echo number_format($row['amount'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr style="background-color: #fdfdfd;">
                        <td colspan="4" style="text-align: right; font-weight: bold; font-size: 16px;">JUMLAH KESELURUHAN:</td>
                        <td style="color: #c62828; font-weight: bold; font-size: 16px;">RM <?php echo number_format($total_expenses, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod perbelanjaan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
