<?php
// admin_facility.php
// Fasiliti & Penyelenggaraan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Kemaskini Status Penyelenggaraan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $req_id = (int)$_POST['req_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    if ($new_status == 'Completed') {
        $sql = "UPDATE facility_requests SET status = '$new_status', resolved_at = NOW() WHERE id = $req_id";
    } else {
        $sql = "UPDATE facility_requests SET status = '$new_status', resolved_at = NULL WHERE id = $req_id";
    }
    
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Status laporan kerosakan berjaya dikemas kini.</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

$sql_reqs = "SELECT f.*, u.username, u.role 
             FROM facility_requests f
             JOIN users u ON f.requested_by = u.id
             ORDER BY FIELD(f.status, 'Pending', 'In Progress', 'Completed'), f.priority DESC, f.created_at DESC";
$result = $conn->query($sql_reqs);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Penyelenggaraan Fasiliti - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #607d8b; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; color: white; }
        .bg-pending { background: #f44336; }
        .bg-progress { background: #ff9800; }
        .bg-resolved { background: #4caf50; }
        .priority-high { color: #d32f2f; font-weight: bold; }
        
        .form-update { display: flex; gap: 5px; flex-direction: column; }
        select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; font-size: 13px; width: 100%; box-sizing: border-box;}
        button { background: #607d8b; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        button:hover { background: #455a64; }
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>🏗️ Laporan Kerosakan & Penyelenggaraan Fasiliti</h2>
        <?php echo $msg; ?>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">Tarikh Lapor</th>
                    <th style="width: 20%;">Dilapor Oleh</th>
                    <th style="width: 35%;">Lokasi & Isu (Tahap)</th>
                    <th style="width: 30%;">Tindakan & Status Terkini</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $statusClass = 'bg-pending';
                        if($row['status'] == 'In Progress') $statusClass = 'bg-progress';
                        if($row['status'] == 'Completed') $statusClass = 'bg-resolved';
                    ?>
                        <tr style="<?php echo $row['status'] == 'Completed' ? 'background:#f9f9f9; opacity:0.8;' : ''; ?>">
                            <td><?php echo date('d/m/Y h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                                <span style="font-size:12px; color:#666; text-transform:capitalize;"><?php echo htmlspecialchars($row['role']); ?></span>
                            </td>
                            <td>
                                <strong>Lokasi: <?php echo htmlspecialchars($row['location']); ?></strong><br>
                                <span style="font-size:13px; color:#444;"><?php echo htmlspecialchars($row['issue_description']); ?></span><br>
                                <span style="font-size:11px; <?php echo ($row['priority'] == 'High' || $row['priority'] == 'Urgent') ? 'color:#d32f2f;font-weight:bold;' : 'color:#888;'; ?>">
                                    Kecemasan: <?php echo htmlspecialchars($row['priority']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="margin-bottom: 5px;">Status Semasa: <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></div>
                                <?php if($row['status'] == 'Completed' && $row['resolved_at']): ?>
                                    <div style="font-size: 11px; color: #4caf50; margin-bottom: 5px;">Selesai pada: <?php echo date('d/m/Y H:i', strtotime($row['resolved_at'])); ?></div>
                                <?php endif; ?>
                                <form method="POST" class="form-update">
                                    <input type="hidden" name="req_id" value="<?php echo $row['id']; ?>">
                                    <select name="status">
                                        <option value="Pending" <?php echo $row['status'] == 'Pending' ? 'selected' : ''; ?>>Tunggu Tindakan (Pending)</option>
                                        <option value="In Progress" <?php echo $row['status'] == 'In Progress' ? 'selected' : ''; ?>>Sedang Dibaiki (In Progress)</option>
                                        <option value="Completed" <?php echo $row['status'] == 'Completed' ? 'selected' : ''; ?>>Selesai Dibaiki (Completed)</option>
                                    </select>
                                    <button type="submit" name="update_status">Kemaskini Tindakan</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada laporan kerosakan setakat ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
