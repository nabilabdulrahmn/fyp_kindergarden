<?php
// maklumat_pelajar_lengkap.php
session_start();
require 'db.php';

// Pastikan admin ATAU teacher yang boleh akses (Kemas kini baru)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    header("Location: login.php");
    exit();
}

// Ambil data pelajar
$sql = "SELECT * FROM students ORDER BY module, full_name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Profil Lengkap Pelajar - Sistem Childcare</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 30px; }
        .container { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; border-top: 8px solid #84b6f4; 
        }
        h2 { color: #555; margin-bottom: 25px; text-align: center; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .tag { padding: 3px 8px; border-radius: 5px; font-size: 10px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa { background: #b5ead7; color: #444; }

        .health-box { background: #fff5f5; padding: 5px; border-radius: 4px; border: 1px solid #ffdada; color: #c53030; font-size: 12px; }
        .no-allergy { color: #999; font-style: italic; }
        
        .info-box { font-size: 11px; color: #666; margin-top: 5px; background: #fafafa; padding: 5px; border-radius: 4px; border: 1px solid #eee;}
        
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        
        @media print {
            .btn-back, .no-print { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>📋 Profil & Maklumat Lengkap Pelajar</h2>
        
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; background: #555; color: white; border: none; border-radius: 5px;">🖨️ Cetak Laporan</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 20%;">Maklumat Pelajar</th>
                    <th style="width: 25%;">Alamat Kediaman</th>
                    <th style="width: 25%;">Maklumat Penjaga & Majikan</th>
                    <th style="width: 30%;">Rekod Kesihatan & Alahan</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module'] ?? ''));
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14px;"><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></strong><br>
                                <span style="color:#777; font-size:12px;">MyKid: <?php echo htmlspecialchars($row['mykid_number'] ?? '-'); ?></span><br>
                                <div style="margin-top: 5px;"><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($row['module'] ?? '-'); ?></span></div>
                            </td>
                            
                            <td>
                                <?php echo nl2br(htmlspecialchars($row['address'] ?? 'Tiada Rekod Alamat')); ?><br>
                                <strong><?php echo htmlspecialchars($row['postcode'] ?? ''); ?> <?php echo htmlspecialchars($row['state'] ?? ''); ?></strong>
                            </td>

                            <td>
                                <strong><?php echo htmlspecialchars($row['parent_name'] ?? 'Tiada Rekod'); ?></strong><br>
                                <span style="color:#777; font-size:12px;">📞 <?php echo htmlspecialchars($row['parent_phone'] ?? '-'); ?></span><br>
                                <span style="color:#777; font-size:12px;">📧 <?php echo htmlspecialchars($row['parent_email'] ?? '-'); ?></span>
                                
                                <div class="info-box">
                                    <strong>Majikan:</strong> <?php echo htmlspecialchars($row['employer_name'] ?? '-'); ?>
                                </div>
                            </td>

                            <td>
                                <strong style="font-size: 12px; color: #555;">Status Umum:</strong><br>
                                <?php echo !empty($row['health_record']) ? htmlspecialchars($row['health_record']) : '<span style="color:#999; font-size:11px;">Tiada masalah direkodkan.</span>'; ?>
                                
                                <div style="margin-top: 8px;">
                                    <?php if(!empty($row['allergies'])): ?>
                                        <div class="health-box">⚠️ <strong>Alahan:</strong> <?php echo htmlspecialchars($row['allergies']); ?></div>
                                    <?php else: ?>
                                        <span class="no-allergy" style="font-size:11px;">Tiada Alahan Makanan/Ubat</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center; padding:30px; color:#999;">Tiada rekod pelajar dalam sistem.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</body>
</html>