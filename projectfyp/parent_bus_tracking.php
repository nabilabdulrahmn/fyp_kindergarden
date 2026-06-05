<?php
// parent_bus_tracking.php
// Jejak Bas Langsung - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Dapatkan parent_id
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Ambil laluan bas untuk anak-anak parent ini sahaja
$sql_routes = "SELECT st.*, s.full_name AS student_name, 
               t.route_name, t.vehicle_plate, t.driver_name, t.driver_phone, t.status AS route_status,
               (SELECT bt.status FROM bus_tracking bt WHERE bt.route_id = st.route_id ORDER BY bt.tracked_at DESC LIMIT 1) AS tracking_status,
               (SELECT bt.eta_minutes FROM bus_tracking bt WHERE bt.route_id = st.route_id ORDER BY bt.tracked_at DESC LIMIT 1) AS eta,
               (SELECT bt.tracked_at FROM bus_tracking bt WHERE bt.route_id = st.route_id ORDER BY bt.tracked_at DESC LIMIT 1) AS last_tracked
               FROM student_transport st 
               INNER JOIN students s ON st.student_id = s.id 
               INNER JOIN transportation t ON st.route_id = t.id 
               WHERE s.parent_id = $parent_id
               ORDER BY s.full_name";
$routes = $conn->query($sql_routes);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jejak Bas Langsung - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #ffb347; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .bus-card { background: white; border-radius: 16px; padding: 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .bus-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .bus-route { font-size: 20px; font-weight: bold; color: #333; }
        .bus-status { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: bold; color: white; }
        .status-enroute { background: #ffb347; }
        .status-arrived { background: #28a745; }
        .status-completed { background: #84b6f4; }
        .status-inactive { background: #ccc; }

        .bus-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .bus-info-item { background: #f8f9fa; padding: 15px; border-radius: 10px; }
        .bus-info-label { font-size: 11px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 4px; font-weight: bold; }
        .bus-info-value { font-size: 15px; color: #333; font-weight: 500; }

        .eta-box { background: linear-gradient(135deg, #fff3cd, #ffecb3); padding: 18px; border-radius: 12px; text-align: center; border: 2px solid #ffb347; }
        .eta-label { font-size: 13px; color: #856404; font-weight: bold; }
        .eta-value { font-size: 36px; font-weight: bold; color: #856404; margin: 5px 0; }
        .eta-unit { font-size: 14px; color: #856404; }

        .child-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; background: #e8f4fd; color: #84b6f4; margin-right: 5px; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .empty-state .icon { font-size: 60px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🚌 Jejak Bas Langsung</h2>
                <div class="subtitle">Pantau lokasi dan status bas anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php if ($routes && $routes->num_rows > 0): ?>
            <?php while ($r = $routes->fetch_assoc()): ?>
                <?php
                    $ts = $r['tracking_status'] ?? '';
                    $status_class = 'status-inactive';
                    if ($ts == 'En Route') $status_class = 'status-enroute';
                    if ($ts == 'Arrived') $status_class = 'status-arrived';
                    if ($ts == 'Completed') $status_class = 'status-completed';
                    $display_status = $ts ? $ts : 'Tiada Maklumat';
                ?>
                <div class="bus-card">
                    <div class="bus-header">
                        <div>
                            <div class="bus-route">🚌 <?php echo htmlspecialchars($r['route_name']); ?></div>
                            <span class="child-badge">👧 <?php echo htmlspecialchars($r['student_name']); ?></span>
                        </div>
                        <span class="bus-status <?php echo $status_class; ?>"><?php echo $display_status; ?></span>
                    </div>

                    <div class="bus-info-grid">
                        <div class="bus-info-item">
                            <div class="bus-info-label">🚗 No. Plat</div>
                            <div class="bus-info-value"><?php echo htmlspecialchars($r['vehicle_plate']); ?></div>
                        </div>
                        <div class="bus-info-item">
                            <div class="bus-info-label">👤 Pemandu</div>
                            <div class="bus-info-value"><?php echo htmlspecialchars($r['driver_name']); ?></div>
                        </div>
                        <div class="bus-info-item">
                            <div class="bus-info-label">📞 Telefon Pemandu</div>
                            <div class="bus-info-value"><?php echo htmlspecialchars($r['driver_phone']); ?></div>
                        </div>
                        <div class="bus-info-item">
                            <div class="bus-info-label">🕐 Jangkaan Ambil</div>
                            <div class="bus-info-value"><?php echo $r['pickup_time'] ? date('h:i A', strtotime($r['pickup_time'])) : '-'; ?></div>
                        </div>
                        <div class="bus-info-item">
                            <div class="bus-info-label">🏠 Jangkaan Hantar</div>
                            <div class="bus-info-value"><?php echo $r['dropoff_time'] ? date('h:i A', strtotime($r['dropoff_time'])) : '-'; ?></div>
                        </div>
                        <div class="bus-info-item">
                            <div class="bus-info-label">📍 Kemas Kini Terakhir</div>
                            <div class="bus-info-value"><?php echo $r['last_tracked'] ? date('d/m H:i', strtotime($r['last_tracked'])) : 'Belum ada'; ?></div>
                        </div>
                    </div>

                    <?php if ($r['eta'] !== null): ?>
                        <div class="eta-box">
                            <div class="eta-label">⏱️ Anggaran Masa Tiba</div>
                            <div class="eta-value"><?php echo (int)$r['eta']; ?></div>
                            <div class="eta-unit">minit</div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">🚌</div>
                <h3>Tiada Pendaftaran Bas</h3>
                <p style="margin-top: 10px;">Anak anda belum didaftarkan untuk perkhidmatan pengangkutan sekolah.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
