<?php
// admin_fees.php
// Pemantauan Yuran & Struktur Pembayaran - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Tambah Struktur Yuran Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fee'])) {
    $module = $conn->real_escape_string($_POST['module']);
    $fee_name = $conn->real_escape_string($_POST['fee_name']);
    $amount = (float)$_POST['amount'];
    $frequency = $conn->real_escape_string($_POST['frequency']);
    
    $sql = "INSERT INTO fee_structures (module, fee_name, amount, frequency) VALUES ('$module', '$fee_name', $amount, '$frequency')";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Struktur Yuran Baru berjaya ditambah.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Tukar Status Aktif
if (isset($_GET['toggle_active'])) {
    $id = (int)$_GET['toggle_active'];
    $conn->query("UPDATE fee_structures SET is_active = NOT is_active WHERE id = $id");
    header("Location: admin_fees.php");
    exit();
}

// Ambil Struktur Yuran
$sql_fees = "SELECT * FROM fee_structures ORDER BY module ASC, fee_name ASC";
$result = $conn->query($sql_fees);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Struktur Yuran - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #ff9800; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #ff9800; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #e68a00; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-active { background: #4caf50; }
        .bg-inactive { background: #9e9e9e; }
        
        .btn-toggle { background: #607d8b; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>💰 Pemantauan Struktur Yuran</h2>
        <?php echo $msg; ?>
        
        <div style="background: #fff8e1; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffecb3;">
            <h3 style="margin-top:0; color:#ff8f00;">Tambah Struktur Yuran Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Modul (Kelas)</label>
                        <select name="module" required>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Nama Yuran</label>
                        <input type="text" name="fee_name" required placeholder="Cth: Yuran Bulanan Tadika">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kekerapan Bayaran</label>
                        <select name="frequency" required>
                            <option value="Monthly">Bulanan</option>
                            <option value="Yearly">Tahunan</option>
                            <option value="One-Time">Sekali Sahaja (Pendaftaran)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="Cth: 250.00">
                    </div>
                </div>
                <button type="submit" name="add_fee">+ Tambah Yuran</button>
            </form>
        </div>

        <h3>Senarai Struktur Yuran Semasa</h3>
        <table>
            <thead>
                <tr>
                    <th>Modul</th>
                    <th>Nama Yuran</th>
                    <th>Kekerapan</th>
                    <th>Jumlah (RM)</th>
                    <th>Status</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['module']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['fee_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['frequency']); ?></td>
                            <td style="color: #d32f2f; font-weight: bold;">RM <?php echo number_format($row['amount'], 2); ?></td>
                            <td>
                                <?php if ($row['is_active']): ?>
                                    <span class="badge bg-active">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-inactive">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?toggle_active=<?php echo $row['id']; ?>" class="btn-toggle">
                                    <?php echo $row['is_active'] ? 'Nyahaktifkan' : 'Aktifkan'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">Tiada struktur yuran direkodkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
