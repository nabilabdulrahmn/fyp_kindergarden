<?php
// admin_visitors.php
// Rekod Pelawat & Keselamatan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Proses Daftar Pelawat Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_visitor'])) {
    $visitor_name = $conn->real_escape_string($_POST['visitor_name']);
    $ic_number = $conn->real_escape_string($_POST['ic_number']);
    $purpose = $conn->real_escape_string($_POST['purpose']);
    $visiting_whom = $conn->real_escape_string($_POST['visiting_whom']);
    $relationship = $conn->real_escape_string($_POST['relationship']);
    $badge_number = $conn->real_escape_string($_POST['badge_number']);
    $check_in = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO visitor_logs (visitor_name, ic_number, purpose, visiting_whom, relationship, check_in, badge_number, recorded_by) 
            VALUES ('$visitor_name', '$ic_number', '$purpose', '$visiting_whom', '$relationship', '$check_in', '$badge_number', $user_id)";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Pelawat berjaya didaftarkan.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Proses Daftar Keluar Pelawat
if (isset($_GET['checkout_id'])) {
    $checkout_id = (int)$_GET['checkout_id'];
    $check_out_time = date('Y-m-d H:i:s');
    $conn->query("UPDATE visitor_logs SET check_out = '$check_out_time' WHERE id = $checkout_id");
    header("Location: admin_visitors.php");
    exit();
}

// Ambil rekod pelawat (Hari Ini secara lalai)
$sql_visitors = "SELECT v.*, u.username 
                 FROM visitor_logs v
                 LEFT JOIN users u ON v.recorded_by = u.id
                 ORDER BY v.check_in DESC LIMIT 100";
$result = $conn->query($sql_visitors);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Rekod Pelawat & Keselamatan - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #607d8b; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #607d8b; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #455a64; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-active { background: #f44336; }
        .bg-done { background: #9e9e9e; }
        
        .btn-checkout { background: #4caf50; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>🪪 Rekod Pelawat & Keselamatan</h2>
        <?php echo $msg; ?>
        
        <div style="background: #eceff1; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
            <h3 style="margin-top:0; color:#455a64;">Daftar Pelawat Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group"><label>Nama Penuh Pelawat</label><input type="text" name="visitor_name" required></div>
                    <div class="form-group"><label>No. KP / Passport</label><input type="text" name="ic_number" required></div>
                    <div class="form-group"><label>No. Pas / Lencana</label><input type="text" name="badge_number" placeholder="Cth: P-01"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hubungan / Peranan</label>
                        <select name="relationship" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background: white; height: 40px; font-family: inherit;">
                            <option value="Ibu Bapa / Penjaga">Ibu Bapa / Penjaga</option>
                            <option value="Adik-Beradik">Adik-Beradik</option>
                            <option value="Anak Saudara">Anak Saudara (Niece / Nephew)</option>
                            <option value="Staf / Kontraktor">Staf / Kontraktor</option>
                            <option value="Pelawat Umum">Pelawat Umum</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Tujuan Lawatan</label><input type="text" name="purpose" required></div>
                    <div class="form-group"><label>Berjumpa Dengan (Staf/Pelajar)</label><input type="text" name="visiting_whom" required></div>
                </div>
                <button type="submit" name="add_visitor">Daftar Masuk Pelawat</button>
            </form>
        </div>

        <h3>Buku Log Pelawat (Terkini)</h3>
        <table>
            <thead>
                <tr>
                    <th>Pelawat</th>
                    <th>Tujuan & Berjumpa</th>
                    <th>Masa Masuk</th>
                    <th>Masa Keluar</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['visitor_name']); ?></strong> 
                                <span style="font-size:11px; background:#e0f2f1; color:#004d40; padding:2px 6px; border-radius:3px; font-weight:600; margin-left:5px;">
                                    <?php echo htmlspecialchars($row['relationship'] ?? 'Pelawat'); ?>
                                </span><br>
                                <span style="font-size:12px; color:#666;">KP: <?php echo htmlspecialchars($row['ic_number']); ?></span>
                                <?php if(!empty($row['badge_number'])): ?>
                                    | <span style="font-size:12px; color:#d32f2f; font-weight:bold;">Pas: <?php echo htmlspecialchars($row['badge_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['purpose']); ?></strong><br>
                                <span style="font-size:12px; color:#666;">Jumpa: <?php echo htmlspecialchars($row['visiting_whom']); ?></span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($row['check_in'])); ?><br>
                                <strong style="color:#1565c0;"><?php echo date('h:i A', strtotime($row['check_in'])); ?></strong>
                            </td>
                            <td>
                                <?php if (!empty($row['check_out'])): ?>
                                    <strong style="color:#2e7d32;"><?php echo date('h:i A', strtotime($row['check_out'])); ?></strong>
                                <?php else: ?>
                                    <span class="badge bg-active">Dalam Premis</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (empty($row['check_out'])): ?>
                                    <a href="?checkout_id=<?php echo $row['id']; ?>" class="btn-checkout" onclick="return confirm('Daftar keluar pelawat ini?');">Daftar Keluar</a>
                                <?php else: ?>
                                    <span class="badge bg-done">Selesai</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod pelawat.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
