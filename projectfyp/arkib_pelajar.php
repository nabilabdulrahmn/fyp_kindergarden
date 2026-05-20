<?php
// arkib_pelajar.php
session_start();
require 'db.php';

// Pastikan hanya admin/pengetua yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Ambil HANYA pelajar yang berstatus Withdrawn dan Graduated
$sql = "SELECT * FROM students WHERE status IN ('Withdrawn', 'Graduated') ORDER BY module, full_name ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Alumni & Arkib Pelajar - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 30px; }
        .container { 
            background: white; padding: 30px; border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; border-top: 8px solid #95a5a6; /* Warna kelabu arkib */
        }
        
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        h2 { color: #555; margin: 0; }
        
        .alert-info {
            background-color: #e8f4fd; color: #31708f; padding: 15px; border-radius: 8px;
            font-size: 13px; border-left: 4px solid #31708f; margin-bottom: 20px;
        }

        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background-color: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #333; }
        td { padding: 12px; border-bottom: 1px solid #eee; vertical-align: top; }
        
        .tag { padding: 3px 8px; border-radius: 5px; font-size: 10px; color: white; font-weight: bold; text-transform: uppercase; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa { background: #b5ead7; color: #444; }

        .status-badge { padding: 5px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; display: inline-block;}
        .status-Graduated { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; } /* Biru untuk Graduated */
        .status-Withdrawn { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; } /* Merah untuk Withdrawn */

        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
        
        @media print {
            .btn-back, .no-print, .alert-info { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header-box">
            <h2>🗄️ Direktori Alumni & Arkib Pelajar</h2>
            <button onclick="window.print()" class="no-print" style="padding: 8px 15px; cursor: pointer; background: #555; color: white; border: none; border-radius: 5px;">🖨️ Cetak Rekod</button>
        </div>

        <div class="alert-info">
            <strong>🔒 Integriti Data:</strong> Muka surat ini berkonsepkan <em>Read-Only</em>. Maklumat bekas pelajar disimpan dengan selamat bagi tujuan rekod sejarah (selama 5 tahun) dan tidak boleh dipinda atau dipadam.
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 25%;">Nama Pelajar & Modul</th>
                    <th style="width: 15%;">No. MyKid</th>
                    <th style="width: 25%;">Maklumat Penjaga</th>
                    <th style="width: 15%;">Status Akhir</th>
                    <th style="width: 20%;">Tarikh Direkod (Daftar)</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $tagClass = 'tag-' . strtolower(str_replace(' ', '', $row['module'] ?? ''));
                    ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14px; color: #333;"><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></strong><br>
                                <div style="margin-top: 5px;"><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($row['module'] ?? '-'); ?></span></div>
                            </td>
                            
                            <td style="color: #666;">
                                <?php echo htmlspecialchars($row['mykid_number'] ?? '-'); ?>
                            </td>

                            <td>
                                <strong><?php echo htmlspecialchars($row['parent_name'] ?? 'Tiada Rekod'); ?></strong><br>
                                <span style="color:#777; font-size:12px;">📞 <?php echo htmlspecialchars($row['parent_phone'] ?? '-'); ?></span>
                            </td>

                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <?php echo strtoupper($row['status']); ?>
                                </span>
                            </td>

                            <td style="color: #666; font-size: 12px;">
                                <?php 
                                    // Papar tarikh dari column created_at (tarikh daftar)
                                    echo !empty($row['created_at']) ? date('d-m-Y (h:i A)', strtotime($row['created_at'])) : '-'; 
                                ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:40px; color:#999;">Tiada rekod arkib buat masa ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</body>
</html>