<?php
// admin_staff.php
// Direktori & Pengurusan Staf - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $ic_number = $conn->real_escape_string($_POST['ic_number']);
    $position = $conn->real_escape_string($_POST['position']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $hire_date = $conn->real_escape_string($_POST['hire_date']);
    
    $sql = "INSERT INTO staff (full_name, ic_number, position, phone, email, hire_date) 
            VALUES ('$full_name', '$ic_number', '$position', '$phone', '$email', '$hire_date')";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Rekod staf baru berjaya didaftarkan.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

$sql_staff = "SELECT * FROM staff ORDER BY status ASC, full_name ASC";
$result = $conn->query($sql_staff);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengurusan Staf - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #3f51b5; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="email"], input[type="date"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #3f51b5; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #303f9f; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-active { background: #4caf50; }
        .bg-inactive { background: #f44336; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>👥 Direktori & Pengurusan Staf</h2>
        <?php echo $msg; ?>
        
        <div style="background: #e8eaf6; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #c5cae9;">
            <h3 style="margin-top:0; color:#283593;">Pendaftaran Staf / Guru Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nama Penuh</label>
                        <input type="text" name="full_name" required>
                    </div>
                    <div class="form-group">
                        <label>No. Kad Pengenalan</label>
                        <input type="text" name="ic_number" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Jawatan</label>
                        <input type="text" name="position" required placeholder="Cth: Guru Tadika, Kerani">
                    </div>
                    <div class="form-group">
                        <label>No. Telefon</label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label>Emel</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Tarikh Mula Kerja</label>
                        <input type="date" name="hire_date" required>
                    </div>
                </div>
                <button type="submit" name="add_staff">+ Daftar Staf</button>
            </form>
        </div>

        <h3>Senarai Direktori Staf</h3>
        <table>
            <thead>
                <tr>
                    <th>Nama & Jawatan</th>
                    <th>Maklumat Perhubungan</th>
                    <th>Tarikh Lantikan</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                                <span style="font-size:12px; color:#3f51b5; font-weight:bold;"><?php echo htmlspecialchars($row['position']); ?></span>
                                <span style="font-size:11px; color:#aaa;"> (IC: <?php echo htmlspecialchars($row['ic_number']); ?>)</span>
                            </td>
                            <td>
                                📞 <?php echo htmlspecialchars($row['phone']); ?><br>
                                📧 <?php echo htmlspecialchars($row['email'] ?? '-'); ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['hire_date'])); ?></td>
                            <td>
                                <?php if ($row['status'] == 'Active'): ?>
                                    <span class="badge bg-active">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-inactive">Berhenti</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod staf direkodkan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
