<?php
// teacher_bus_roster.php
// Jadual Bas & Kepulangan - Guru
session_start();
require 'db.php';

// Pastikan hanya cikgu yang boleh akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'teacher') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Dapatkan teacher_id
$check_teacher = $conn->query("SELECT id FROM teachers WHERE user_id = '$user_id'");
if ($check_teacher->num_rows == 0) {
    $conn->query("INSERT INTO teachers (user_id, full_name) VALUES ('$user_id', '$username')");
    $teacher_id = $conn->insert_id;
} else {
    $teacher_row = $check_teacher->fetch_assoc();
    $teacher_id = $teacher_row['id'];
}

// --- AMBIL SENARAI LALUAN PENGANGKUTAN ---
$routes_list = [];
$routes_res = $conn->query("SELECT * FROM transportation WHERE status = 'Active' ORDER BY route_name ASC");
if ($routes_res) {
    while ($row = $routes_res->fetch_assoc()) {
        $routes_list[] = $row;
    }
}

// --- AMBIL SENARAI PELAJAR DALAM JADUAL BAS ---
$students_bus = [];
$students_sql = "
    SELECT st.*, s.full_name, s.module, t.route_name, t.vehicle_plate, t.driver_name, t.driver_phone
    FROM student_transport st
    JOIN students s ON st.student_id = s.id
    JOIN transportation t ON st.route_id = t.id
    WHERE s.status = 'Active'
    ORDER BY t.route_name ASC, s.full_name ASC
";
$students_res = $conn->query($students_sql);
if ($students_res) {
    while ($row = $students_res->fetch_assoc()) {
        $students_bus[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Jadual Bas & Kepulangan - Guru</title>
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
        .left-panel { flex: 4; }
        .right-panel { flex: 6; border-top: 5px solid #84b6f4; }
        
        h3 { color: #555; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        /* Bus Route Card */
        .route-card {
            background: #fff8f0;
            border: 1px solid #ffe0b2;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #ffb347;
        }
        .route-title {
            font-weight: bold;
            color: #d35400;
            font-size: 15px;
            margin-bottom: 8px;
        }
        .driver-info {
            font-size: 13px;
            color: #555;
            line-height: 1.5;
        }
        
        /* Table Styling */
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        th { background-color: #f9f9f9; padding: 10px; text-align: left; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        .tag { padding: 3px 8px; border-radius: 5px; font-size: 10px; color: white; font-weight: bold; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; }
        .tag-kafa, .tag-kafacare { background: #b5ead7; color: #444;}
        
        .btn-back { display: inline-block; margin-top: 15px; text-decoration: none; color: #666; }
    </style>
</head>
<body>
<?php include 'sidebar_teacher.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

    <div class="header-bar">
        <h2 style="margin:0; color:#ffb347;">🚌 Jadual Laluan Bas & Kepulangan Kanak-kanak</h2>
    </div>

    <div class="main-container">
        
        <!-- Panel Laluan Bas -->
        <div class="panel left-panel">
            <h3>👥 Maklumat Pemandu & Kenderaan</h3>
            
            <?php if (count($routes_list) > 0): ?>
                <?php foreach ($routes_list as $route): ?>
                    <div class="route-card">
                        <div class="route-title">📍 <?php echo htmlspecialchars($route['route_name']); ?></div>
                        <div class="driver-info">
                            <strong>Kenderaan:</strong> <span style="background: #fff; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold; border: 1px solid #ddd;"><?php echo htmlspecialchars($route['vehicle_plate']); ?></span><br>
                            <strong>Pemandu:</strong> <?php echo htmlspecialchars($route['driver_name']); ?><br>
                            <strong>Telefon:</strong> 📞 <?php echo htmlspecialchars($route['driver_phone']); ?><br>
                            <strong>Kapasiti Bas:</strong> <?php echo htmlspecialchars($route['capacity']); ?> org pelajar
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:30px; color:#888;">Tiada laluan bas aktif ditemui.</p>
            <?php endif; ?>
        </div>

        <!-- Panel Senarai Pelajar Mengikut Bas -->
        <div class="panel right-panel">
            <h3>📋 Senarai Roster Kepulangan Pelajar</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>Pelajar / Modul</th>
                        <th>Laluan Bas</th>
                        <th>Alamat Pickup/Dropoff</th>
                        <th>Jadual Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($students_bus) > 0): ?>
                        <?php foreach ($students_bus as $sb): 
                            $tagClass = 'tag-' . strtolower(str_replace(' ', '', $sb['module']));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($sb['full_name']); ?></strong><br>
                                    <span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($sb['module']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($sb['route_name']); ?></strong><br>
                                    <span style="font-size:11px; color:#666;">🚌 <?php echo htmlspecialchars($sb['vehicle_plate']); ?></span>
                                </td>
                                <td style="font-size:12px; color:#555;">
                                    <?php echo nl2br(htmlspecialchars($sb['pickup_address'])); ?>
                                </td>
                                <td style="font-size:12px;">
                                    🌅 Ambil: <?php echo $sb['pickup_time'] ? date('h:i A', strtotime($sb['pickup_time'])) : '-'; ?><br>
                                    🌆 Hantar: <?php echo $sb['dropoff_time'] ? date('h:i A', strtotime($sb['dropoff_time'])) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; color:#888; padding:50px;">Tiada rekod pelajar berdaftar dengan pengangkutan bas sekolah.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>


</main>
</body>
</html>
