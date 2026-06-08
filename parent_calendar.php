<?php
// parent_calendar.php
// Kalendar Sekolah & RSVP - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$msg = '';

// Dapatkan parent_id
$sql_parent = "SELECT id FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];

// Proses RSVP
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['rsvp_submit'])) {
    $event_id = (int)$_POST['event_id'];
    $status = $conn->real_escape_string($_POST['rsvp_status']);
    
    // Sahkan event wujud
    $verify_event = $conn->query("SELECT id FROM calendar_events WHERE id = $event_id LIMIT 1");
    if ($verify_event && $verify_event->num_rows > 0) {
        // Guna INSERT ... ON DUPLICATE KEY UPDATE untuk elakkan duplikasi
        $sql_rsvp = "INSERT INTO event_rsvps (event_id, parent_id, status) 
                     VALUES ($event_id, $parent_id, '$status')
                     ON DUPLICATE KEY UPDATE status = '$status', responded_at = NOW()";
        if ($conn->query($sql_rsvp)) {
            $msg = "<div class='alert success'>RSVP berjaya dikemas kini!</div>";
        } else {
            $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
        }
    }
}

// Ambil acara akan datang
$sql_events = "SELECT ce.*, 
               (SELECT er.status FROM event_rsvps er WHERE er.event_id = ce.id AND er.parent_id = $parent_id LIMIT 1) AS my_rsvp,
               (SELECT COUNT(*) FROM event_rsvps er2 WHERE er2.event_id = ce.id AND er2.status = 'Hadir') AS total_hadir
               FROM calendar_events ce 
               WHERE ce.event_date >= CURDATE()
               ORDER BY ce.event_date ASC, ce.event_time ASC";
$events = $conn->query($sql_events);

// Ambil acara lepas (30 hari)
$sql_past = "SELECT ce.*, 
             (SELECT er.status FROM event_rsvps er WHERE er.event_id = ce.id AND er.parent_id = $parent_id LIMIT 1) AS my_rsvp
             FROM calendar_events ce 
             WHERE ce.event_date < CURDATE() AND ce.event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             ORDER BY ce.event_date DESC";
$past_events = $conn->query($sql_past);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalendar Sekolah & RSVP - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #ffb347; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }
        .alert { padding: 12px 15px; margin-bottom: 15px; border-radius: 8px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }

        .section-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }

        .event-card { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 20px; align-items: flex-start; transition: transform 0.2s; }
        .event-card:hover { transform: translateY(-1px); }
        .event-date-box { background: linear-gradient(135deg, #ffb347, #ff9a76); color: white; border-radius: 12px; padding: 15px; text-align: center; min-width: 80px; flex-shrink: 0; }
        .event-day { font-size: 28px; font-weight: bold; }
        .event-month { font-size: 12px; text-transform: uppercase; }
        .event-info { flex: 1; }
        .event-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 6px; }
        .event-meta { font-size: 13px; color: #888; margin-bottom: 10px; }
        .event-desc { font-size: 14px; color: #555; line-height: 1.5; margin-bottom: 12px; }
        .event-rsvp { display: flex; gap: 10px; align-items: center; }
        .rsvp-btn { padding: 6px 16px; border-radius: 20px; border: 2px solid; cursor: pointer; font-weight: bold; font-size: 13px; transition: 0.3s; }
        .rsvp-hadir { border-color: #28a745; color: #28a745; background: white; }
        .rsvp-hadir:hover, .rsvp-hadir.active { background: #28a745; color: white; }
        .rsvp-tidak { border-color: #dc3545; color: #dc3545; background: white; }
        .rsvp-tidak:hover, .rsvp-tidak.active { background: #dc3545; color: white; }
        .rsvp-status { font-size: 12px; color: #888; margin-left: 10px; }
        .attendees { font-size: 12px; color: #28a745; font-weight: bold; }

        .past-section { margin-top: 30px; }
        .past-event { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); opacity: 0.7; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; background: white; border-radius: 16px; }
    </style>
</head>
<body>
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📆 Kalendar Sekolah & RSVP</h2>
                <div class="subtitle">Acara sekolah dan pengesahan kehadiran anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <?php echo $msg; ?>

        <div class="section-title">📅 Acara Akan Datang</div>

        <?php if ($events && $events->num_rows > 0): ?>
            <?php while ($e = $events->fetch_assoc()): ?>
                <?php
                    $bulan_ms = array('01'=>'Jan','02'=>'Feb','03'=>'Mac','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ogo','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Dis');
                    $day = date('d', strtotime($e['event_date']));
                    $month_num = date('m', strtotime($e['event_date']));
                    $month_ms = isset($bulan_ms[$month_num]) ? $bulan_ms[$month_num] : $month_num;
                ?>
                <div class="event-card">
                    <div class="event-date-box">
                        <div class="event-day"><?php echo $day; ?></div>
                        <div class="event-month"><?php echo $month_ms; ?></div>
                    </div>
                    <div class="event-info">
                        <div class="event-title"><?php echo htmlspecialchars($e['title']); ?></div>
                        <div class="event-meta">
                            <?php if ($e['event_time']): ?>🕐 <?php echo date('h:i A', strtotime($e['event_time'])); ?> | <?php endif; ?>
                            <?php if ($e['location']): ?>📍 <?php echo htmlspecialchars($e['location']); ?><?php endif; ?>
                        </div>
                        <?php if ($e['description']): ?>
                            <div class="event-desc"><?php echo nl2br(htmlspecialchars($e['description'])); ?></div>
                        <?php endif; ?>
                        <div class="event-rsvp">
                            <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                                <button type="submit" name="rsvp_submit" value="1" class="rsvp-btn rsvp-hadir <?php echo ($e['my_rsvp'] == 'Hadir') ? 'active' : ''; ?>">
                                    <input type="hidden" name="rsvp_status" value="Hadir">✅ Hadir
                                </button>
                            </form>
                            <form method="POST" style="display: flex; gap: 8px; align-items: center;">
                                <input type="hidden" name="event_id" value="<?php echo $e['id']; ?>">
                                <button type="submit" name="rsvp_submit" value="1" class="rsvp-btn rsvp-tidak <?php echo ($e['my_rsvp'] == 'Tidak Hadir') ? 'active' : ''; ?>">
                                    <input type="hidden" name="rsvp_status" value="Tidak Hadir">❌ Tidak Hadir
                                </button>
                            </form>
                            <span class="attendees">👥 <?php echo (int)$e['total_hadir']; ?> akan hadir</span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 40px; margin-bottom: 10px;">📆</div>
                <p>Tiada acara yang akan datang buat masa ini.</p>
            </div>
        <?php endif; ?>

        <div class="past-section">
            <div class="section-title" style="color: #888;">📜 Acara Lepas (30 Hari)</div>
            <?php if ($past_events && $past_events->num_rows > 0): ?>
                <?php while ($pe = $past_events->fetch_assoc()): ?>
                    <div class="past-event">
                        <strong><?php echo date('d/m/Y', strtotime($pe['event_date'])); ?></strong> - 
                        <?php echo htmlspecialchars($pe['title']); ?>
                        <?php if ($pe['my_rsvp']): ?>
                            <span style="font-size: 12px; color: <?php echo ($pe['my_rsvp'] == 'Hadir') ? '#28a745' : '#dc3545'; ?>;">
                                (<?php echo $pe['my_rsvp']; ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="past-event" style="text-align: center; color: #aaa;">Tiada acara lepas.</div>
            <?php endif; ?>
        </div>
    </div>

</main>
</body>
</html>
