<?php
// admin_inventory.php
// Sumber & Inventori - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Tambah/Kemaskini Stok
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_stock'])) {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $category = $conn->real_escape_string($_POST['category']);
    $quantity = (int)$_POST['quantity'];
    $unit = $conn->real_escape_string($_POST['unit']);
    $min_stock_level = (int)$_POST['min_stock_level'];
    $location = $conn->real_escape_string($_POST['location']);
    
    // Check if exists
    $check = $conn->query("SELECT id, quantity FROM inventory WHERE item_name = '$item_name' LIMIT 1");
    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $new_qty = $row['quantity'] + $quantity;
        $sql = "UPDATE inventory SET quantity = $new_qty, last_restocked = CURDATE() WHERE id = " . $row['id'];
        $conn->query($sql);
        $msg = "<div class='alert success'>Stok sedia ada berjaya dikemas kini. (+$quantity)</div>";
    } else {
        $sql = "INSERT INTO inventory (item_name, category, quantity, unit, min_stock_level, location, last_restocked) 
                VALUES ('$item_name', '$category', $quantity, '$unit', $min_stock_level, '$location', CURDATE())";
        if ($conn->query($sql)) {
            $msg = "<div class='alert success'>Item baru berjaya ditambah ke inventori.</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    }
}

$sql_inventory = "SELECT * FROM inventory ORDER BY category ASC, item_name ASC";
$result = $conn->query($sql_inventory);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Inventori & Sumber - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #795548; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #795548; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #5d4037; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .warning-stock { color: #d32f2f; font-weight: bold; background: #ffebee; padding: 2px 6px; border-radius: 4px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📦 Pengurusan Sumber & Inventori</h2>
        <?php echo $msg; ?>
        
        <div style="background: #efebe9; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #d7ccc8;">
            <h3 style="margin-top:0; color:#4e342e;">Tambah Stok / Item Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nama Barangan</label>
                        <input type="text" name="item_name" required list="items-list">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="category" required>
                            <option value="Alat Tulis">Alat Tulis</option>
                            <option value="Makanan">Makanan & Minuman</option>
                            <option value="Kebersihan">Barang Kebersihan</option>
                            <option value="Perabot">Perabot/Peralatan</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Kuantiti Masuk</label><input type="number" name="quantity" required min="1"></div>
                    <div class="form-group"><label>Unit (Cth: kotak, rim)</label><input type="text" name="unit" required></div>
                    <div class="form-group"><label>Minima Stok (Amaran)</label><input type="number" name="min_stock_level" value="5"></div>
                    <div class="form-group"><label>Lokasi Simpanan</label><input type="text" name="location"></div>
                </div>
                <button type="submit" name="add_stock">+ Masukkan Ke Inventori</button>
            </form>
        </div>

        <h3>Senarai Inventori Semasa</h3>
        <table>
            <thead>
                <tr>
                    <th>Barangan</th>
                    <th>Kategori / Lokasi</th>
                    <th>Baki Stok</th>
                    <th>Paras Minima</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['item_name']); ?></strong><br>
                                <span style="font-size:11px; color:#aaa;">Last Restock: <?php echo date('d/m/Y', strtotime($row['last_restocked'] ?? date('Y-m-d'))); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['category']); ?><br>
                                <span style="font-size:12px; color:#777;">📍 <?php echo htmlspecialchars($row['location'] ?? 'Tiada'); ?></span>
                            </td>
                            <td style="font-size: 16px; font-weight: bold;">
                                <?php echo $row['quantity']; ?> <span style="font-size: 12px; font-weight: normal;"><?php echo htmlspecialchars($row['unit']); ?></span>
                            </td>
                            <td><?php echo $row['min_stock_level']; ?> <?php echo htmlspecialchars($row['unit']); ?></td>
                            <td>
                                <?php if ($row['quantity'] <= $row['min_stock_level']): ?>
                                    <span class="warning-stock">⚠️ Stok Rendah!</span>
                                <?php else: ?>
                                    <span style="color:#388e3c; font-weight:bold;">✔ Cukup</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod inventori.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
