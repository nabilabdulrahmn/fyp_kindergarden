<?php
// teacher_meal_plan.php
// Pelan Pemakanan & Alahan - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$teacher_id = dapatkan_teacher_id($conn);
$approved_mods = dapatkan_modul_diluluskan($conn, $teacher_id);

// --- QUERY ALERGEN & ALAHAN PELAJAR (KATEGORI: AKSES TERHAD IKUT KELAS CIKGU INI SAHAJA DI BAWAH MODUL DILULUSKAN) ---
$allergies_list = [];
if (!empty($approved_mods)) {
    $modules_list = "'" . implode("','", array_map(function($m) use ($conn) { return mysqli_real_escape_string($conn, $m); }, $approved_mods)) . "'";
    $allergy_sql = "
        SELECT DISTINCT s.full_name, s.module, s.allergies, s.health_record, c.class_name
        FROM students s
        JOIN student_classes sc ON s.id = sc.student_id
        JOIN classes c ON sc.class_id = c.id
        WHERE c.teacher_id = '$teacher_id' 
          AND s.status = 'Active' 
          AND s.allergies IS NOT NULL 
          AND s.allergies != ''
          AND s.module IN ($modules_list)
          AND c.module IN ($modules_list)
    ";
    $allergy_res = $conn->query($allergy_sql);
    if ($allergy_res) {
        while ($row = $allergy_res->fetch_assoc()) {
            $allergies_list[] = $row;
        }
    }
}

// --- AMBIL PELAN PEMAKANAN MINGGU INI ---
$meal_plans = [];
$meal_sql = "
    SELECT mp.*, c.class_name 
    FROM meal_plans mp 
    LEFT JOIN classes c ON mp.class_id = c.id 
    WHERE mp.meal_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ORDER BY mp.meal_date ASC, 
             CASE mp.meal_type 
                WHEN 'Breakfast' THEN 1 
                WHEN 'Lunch' THEN 2 
                WHEN 'Snack' THEN 3 
                ELSE 4 
             END ASC
";
$meal_res = $conn->query($meal_sql);
if ($meal_res) {
    while ($row = $meal_res->fetch_assoc()) {
        $meal_plans[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Pelan Pemakanan & Alahan - Guru</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background-color: #f4f7f6; 
            margin: 0; 
            padding: 20px;
        }
        .header-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 5px solid #ffb347;
        }
        .main-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .panel {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .left-panel { flex: 6; }
        .right-panel { flex: 4; border-top: 5px solid #e74c3c; } /* Merah untuk amaran alahan */
        
        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .meal-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .meal-date-header {
            font-weight: bold;
            font-size: 15px;
            color: #d35400;
            background: #fff3e0;
            padding: 6px 12px;
            border-radius: 5px;
            margin-bottom: 12px;
            display: inline-block;
        }
        .meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .meal-item {
            background: #fafafa;
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 6px;
            border-top: 4px solid #ffb347;
        }
        .meal-type {
            font-weight: bold;
            font-size: 13px;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        .meal-desc {
            font-size: 14px;
            color: #2c3e50;
            margin: 6px 0;
            font-weight: 500;
        }
        .meal-allergens {
            font-size: 11px;
            color: #c0392b;
            background: #fdf2f2;
            padding: 4px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        /* Allergy Alerts Panel */
        .allergy-alert-card {
            background: #fff5f5;
            border: 1px solid #ffdada;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            border-left: 5px solid #e74c3c;
        }
        .allergy-student {
            font-weight: bold;
            color: #c53030;
            font-size: 14px;
        }
        .allergy-details {
            font-size: 13px;
            color: #742a2a;
            margin-top: 5px;
        }
        .allergy-meta {
            font-size: 11px;
            color: #9b2c2c;
            margin-top: 3px;
        }
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">🍽️ Pelan Pemakanan & Amaran Alahan Murid</h2>
    </div>

    <div class="main-container">
        
        <!-- Panel Jadual Makanan -->
        <div class="panel left-panel">
            <h3>📅 Menu Pemakanan Mingguan</h3>
            
            <?php if (count($meal_plans) > 0): 
                // Group by date
                $grouped_meals = [];
                foreach ($meal_plans as $m) {
                    $grouped_meals[$m['meal_date']][] = $m;
                }
                
                foreach ($grouped_meals as $date => $meals):
            ?>
                <div class="meal-card">
                    <div class="meal-date-header">
                        📅 <?php echo date('d/m/Y (l)', strtotime($date)); ?>
                    </div>
                    <div class="meal-grid">
                        <?php foreach ($meals as $meal): ?>
                            <div class="meal-item">
                                <span class="meal-type">🍳 <?php echo htmlspecialchars($meal['meal_type']); ?></span>
                                <div class="meal-desc"><?php echo htmlspecialchars($meal['menu_description']); ?></div>
                                <?php if(!empty($meal['allergens'])): ?>
                                    <div class="meal-allergens">⚠️ Alergen: <?php echo htmlspecialchars($meal['allergens']); ?></div>
                                <?php else: ?>
                                    <div style="font-size:11px; color:#7f8c8d;">Tiada alergen khusus</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:50px; color:#888;">Tiada pelan pemakanan direkodkan untuk minggu ini.</p>
            <?php endif; ?>
        </div>

        <!-- Panel Amaran Alahan -->
        <div class="panel right-panel">
            <h3 style="color:#e74c3c;">⚠️ Amaran Alahan Murid Aktif</h3>
            <p style="font-size:12px; color:#7f8c8d; margin-bottom:15px;">Sila rujuk amaran di bawah sebelum menyajikan makanan kepada anak-anak.</p>
            
            <div style="max-height: 550px; overflow-y: auto;">
                <?php if (count($allergies_list) > 0): ?>
                    <?php foreach ($allergies_list as $a): ?>
                        <div class="allergy-alert-card">
                            <div class="allergy-student">👦 <?php echo htmlspecialchars($a['full_name']); ?></div>
                            <div class="allergy-details">
                                <strong>⚠️ Alahan:</strong> <?php echo htmlspecialchars($a['allergies']); ?>
                            </div>
                            <?php if(!empty($a['health_record'])): ?>
                                <div class="allergy-details" style="font-size: 11px;">
                                    <strong>🩺 Nota Kesihatan:</strong> <?php echo htmlspecialchars($a['health_record']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="allergy-meta">
                                Kelas: <?php echo htmlspecialchars($a['class_name']); ?> (<?php echo htmlspecialchars($a['module']); ?>)
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; padding:50px; color:#2ecc71; font-weight:bold;">✓ Tiada amaran alahan murid aktif ditemui.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>


</main>
</body>
</html>
