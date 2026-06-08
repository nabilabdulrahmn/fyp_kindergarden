<?php
// admin_doc_verify.php
// Pengesahan Dokumen Sokongan Pendaftaran - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';

// Proses Pengesahan Dokumen
if (isset($_GET['verify'])) {
    $app_id = (int)$_GET['verify'];
    $conn->query("UPDATE applications SET documents_verified = 1 WHERE id = $app_id");
    $msg = "<div class='alert success'>Dokumen permohonan berjaya disahkan.</div>";
}

if (isset($_GET['unverify'])) {
    $app_id = (int)$_GET['unverify'];
    $conn->query("UPDATE applications SET documents_verified = 0 WHERE id = $app_id");
    $msg = "<div class='alert success'>Pengesahan dokumen telah dibatalkan.</div>";
}

$sql_apps = "SELECT a.*, p.full_name AS parent_name 
             FROM applications a
             LEFT JOIN parents p ON a.parent_id = p.id
             ORDER BY a.documents_verified ASC, a.created_at DESC";
$result = $conn->query($sql_apps);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pengesahan Dokumen - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #00bcd4; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 20px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .btn-verify { background: #4caf50; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 12px; }
        .btn-unverify { background: #f44336; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 12px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>📄 Semakan & Pengesahan Dokumen</h2>
        <?php echo $msg; ?>
        <p style="color:#666; font-size:14px; margin-bottom:20px;">Sila pastikan dokumen fizikal (MyKid, Salinan IC Ibu Bapa, Rekod Vaksin) telah diterima sebelum membuat pengesahan.</p>
        
        <table>
            <thead>
                <tr>
                    <th>Pemohon (Pelajar)</th>
                    <th>Penjaga</th>
                    <th>Tarikh Permohonan</th>
                    <th>Status Semasa</th>
                    <th>Tindakan Sistem</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr style="<?php echo $row['documents_verified'] ? 'background:#f1f8e9;' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($row['child_name']); ?></strong><br>
                                <span style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['module']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['parent_name'] ?? 'Tiada Rekod'); ?></td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <?php if ($row['documents_verified']): ?>
                                    <span style="color:#2e7d32; font-weight:bold;">✅ Dokumen Lengkap</span>
                                <?php else: ?>
                                    <span style="color:#d32f2f; font-weight:bold;">❌ Menunggu Dokumen</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['documents_verified']): ?>
                                    <a href="?unverify=<?php echo $row['id']; ?>" class="btn-unverify" onclick="return confirm('Batal pengesahan dokumen ini?');">Batal Pengesahan</a>
                                <?php else: ?>
                                    <a href="?verify=<?php echo $row['id']; ?>" class="btn-verify" onclick="return confirm('Sahkan bahawa semua dokumen fizikal telah disemak?');">Sahkan Dokumen Lengkap</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#999;">Tiada rekod permohonan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
