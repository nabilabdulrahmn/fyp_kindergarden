<?php
// admin_enrollment.php
// Pemantauan Status Pendaftaran - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Kemaskini Status Pendaftaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $app_id = (int)$_POST['app_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $sql = "UPDATE applications SET status = '$new_status' WHERE id = $app_id";
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Status pendaftaran berjaya dikemas kini.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Ambil rekod permohonan pendaftaran
$sql_apps = "SELECT a.*, p.full_name AS parent_name, p.phone_number AS parent_phone
             FROM applications a
             LEFT JOIN parents p ON a.parent_id = p.id
             ORDER BY a.created_at DESC";
$result = $conn->query($sql_apps);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Status Pendaftaran Pelajar - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1100px; margin: auto; border-top: 8px solid #03a9f4; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white; display: inline-block;}
        .bg-pending { background: #ff9800; }
        .bg-interview { background: #2196f3; }
        .bg-approved { background: #4caf50; }
        .bg-rejected { background: #f44336; }
        
        select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px;}
        .btn-update { background: #03a9f4; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-update:hover { background: #0288d1; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📋 Pemantauan Status Pendaftaran Pelajar</h2>
        <?php echo $msg; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Tarikh Mohon</th>
                    <th>Nama Pelajar & Modul</th>
                    <th>Nama Penjaga & Telefon</th>
                    <th>Status Dokumen</th>
                    <th>Status Terkini</th>
                    <th>Tindakan (Kemaskini)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $statusClass = 'bg-pending';
                        if($row['status'] == 'Approved') $statusClass = 'bg-approved';
                        if($row['status'] == 'Rejected') $statusClass = 'bg-rejected';
                        if($row['status'] == 'Waitlisted' || $row['status'] == 'Interview') $statusClass = 'bg-interview';
                    ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['child_name']); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['module']); ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['parent_name'] ?? '-'); ?><br>
                                <span style="font-size:12px; color:#666;">📞 <?php echo htmlspecialchars($row['parent_phone'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <?php if ($row['documents_verified']): ?>
                                    <span style="color:#4caf50; font-weight:bold;">✔ Disahkan</span>
                                <?php else: ?>
                                    <span style="color:#f44336; font-weight:bold;">❌ Belum Sah</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="app_id" value="<?php echo $row['id']; ?>">
                                    <select name="status">
                                        <option value="Pending" <?php echo $row['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Waitlisted" <?php echo $row['status'] == 'Waitlisted' ? 'selected' : ''; ?>>Waitlisted</option>
                                        <option value="Approved" <?php echo $row['status'] == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="Rejected" <?php echo $row['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn-update">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#999;">Tiada rekod permohonan pendaftaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>
</body>
</html>
