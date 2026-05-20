<?php
// admin_transport.php
// Pengurusan Pengangkutan & Laluan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_route'])) {
    $route_name = $conn->real_escape_string($_POST['route_name']);
    $vehicle_plate = $conn->real_escape_string($_POST['vehicle_plate']);
    $driver_name = $conn->real_escape_string($_POST['driver_name']);
    $driver_phone = $conn->real_escape_string($_POST['driver_phone']);
    $capacity = (int)$_POST['capacity'];
    
    $sql = "INSERT INTO transportation (route_name, vehicle_plate, driver_name, driver_phone, capacity) 
            VALUES ('$route_name', '$vehicle_plate', '$driver_name', '$driver_phone', $capacity)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Laluan bas berjaya ditambah.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

$sql_routes = "SELECT * FROM transportation ORDER BY route_name ASC";
$result = $conn->query($sql_routes);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengangkutan & Laluan - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #ffc107; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #ffc107; color: #333; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #ffb300; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-active { background: #4caf50; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🚌 Pengurusan Pengangkutan & Laluan Bas</h2>
        <?php echo $msg; ?>
        
        <div style="background: #fff8e1; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffecb3;">
            <h3 style="margin-top:0; color:#f57f17;">Tambah Laluan Bas Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nama Laluan / Kawasan</label>
                        <input type="text" name="route_name" required placeholder="Cth: Laluan A - Taman Melati">
                    </div>
                    <div class="form-group">
                        <label>No. Plat Kenderaan</label>
                        <input type="text" name="vehicle_plate" required placeholder="Cth: WKL 1234">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Pemandu</label>
                        <input type="text" name="driver_name" required>
                    </div>
                    <div class="form-group">
                        <label>No. Telefon Pemandu</label>
                        <input type="text" name="driver_phone" required>
                    </div>
                    <div class="form-group">
                        <label>Kapasiti Pelajar</label>
                        <input type="number" name="capacity" value="20" required>
                    </div>
                </div>
                <button type="submit" name="add_route">+ Tambah Laluan</button>
            </form>
        </div>

        <h3>Senarai Laluan Pengangkutan</h3>
        <table>
            <thead>
                <tr>
                    <th>Laluan / Kawasan</th>
                    <th>Kenderaan</th>
                    <th>Pemandu & Hubungi</th>
                    <th>Kapasiti</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['route_name']); ?></strong></td>
                            <td><span style="background: #eee; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-weight: bold;"><?php echo htmlspecialchars($row['vehicle_plate']); ?></span></td>
                            <td>
                                <?php echo htmlspecialchars($row['driver_name']); ?><br>
                                <span style="font-size:12px; color:#666;">📞 <?php echo htmlspecialchars($row['driver_phone']); ?></span>
                            </td>
                            <td><?php echo $row['capacity']; ?> org</td>
                            <td><span class="badge bg-active"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada laluan direkodkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
