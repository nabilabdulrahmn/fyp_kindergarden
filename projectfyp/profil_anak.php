<?php
// profil_anak.php
// Profil Anak Saya - Paparan untuk Ibu Bapa sahaja
session_start();
require 'db.php';

// Kawalan akses: Hanya parent dibenarkan
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];

// Dapatkan parent_id dari jadual parents
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai. Sila hubungi admin.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Ambil semua anak yang dimiliki oleh parent ini sahaja
$sql_children = "SELECT s.*, 
                 (SELECT c.class_name FROM student_classes sc 
                  INNER JOIN classes c ON sc.class_id = c.id 
                  WHERE sc.student_id = s.id LIMIT 1) AS class_name
                 FROM students s 
                 WHERE s.parent_id = $parent_id AND s.status = 'Active'
                 ORDER BY s.full_name ASC";
$result = $conn->query($sql_children);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Anak Saya - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #84b6f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; font-size: 22px; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .child-card { background: white; border-radius: 16px; padding: 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); transition: transform 0.2s; }
        .child-card:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(0,0,0,0.1); }
        .child-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .child-avatar { width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, #84b6f4, #b084f4); display: flex; align-items: center; justify-content: center; color: white; font-size: 26px; font-weight: bold; flex-shrink: 0; }
        .child-name { font-size: 20px; color: #333; font-weight: bold; }
        .child-module { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; margin-top: 4px; }
        .module-taska { background: #ff9a76; }
        .module-tadika { background: #77dd77; }
        .module-kafa { background: #84b6f4; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; }
        .info-item { background: #f8f9fa; padding: 14px; border-radius: 10px; }
        .info-label { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: bold; }
        .info-value { font-size: 15px; color: #333; font-weight: 500; }

        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state .icon { font-size: 60px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>👧 Profil Anak Saya</h2>
                <div class="subtitle">Paparan maklumat anak-anak yang berdaftar</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($child = $result->fetch_assoc()): ?>
                <?php
                    $module_class = 'module-taska';
                    if ($child['module'] == 'Tadika') $module_class = 'module-tadika';
                    if ($child['module'] == 'KAFA Care') $module_class = 'module-kafa';
                    $initial = strtoupper(substr($child['full_name'], 0, 1));
                ?>
                <div class="child-card">
                    <div class="child-header">
                        <div class="child-avatar"><?php echo $initial; ?></div>
                        <div>
                            <div class="child-name"><?php echo htmlspecialchars($child['full_name']); ?></div>
                            <span class="child-module <?php echo $module_class; ?>"><?php echo htmlspecialchars($child['module']); ?></span>
                        </div>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">No. MyKid</div>
                            <div class="info-value"><?php echo htmlspecialchars($child['mykid_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kelas</div>
                            <div class="info-value"><?php echo htmlspecialchars($child['class_name'] ?? 'Belum Ditetapkan'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value">✅ <?php echo htmlspecialchars($child['status']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Rekod Kesihatan</div>
                            <div class="info-value"><?php echo htmlspecialchars($child['health_record'] ?? 'Tiada rekod'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Alahan</div>
                            <div class="info-value"><?php echo htmlspecialchars($child['allergies'] ?? 'Tiada alahan direkodkan'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Tarikh Daftar</div>
                            <div class="info-value"><?php echo date('d/m/Y', strtotime($child['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="child-card empty-state">
                <div class="icon">👶</div>
                <h3>Tiada Anak Berdaftar</h3>
                <p style="margin-top: 10px;">Sila daftarkan anak anda melalui <a href="daftar_anak.php" style="color:#84b6f4;">Pendaftaran Adik-Beradik</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
