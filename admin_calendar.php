<?php
// admin_calendar.php
// Pengurusan Kalendar Sekolah & Acara - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Tambah Acara Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_event'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $event_date = $conn->real_escape_string($_POST['event_date']);
    $event_time = !empty($_POST['event_time']) ? "'" . $conn->real_escape_string($_POST['event_time']) . "'" : 'NULL';
    $location = $conn->real_escape_string($_POST['location']);

    $sql = "INSERT INTO calendar_events (title, description, event_date, event_time, location, created_by) 
            VALUES ('$title', '$description', '$event_date', $event_time, '$location', $user_id)";
            
    if ($conn->query($sql)) {
        $msg = "<div class='alert success'>Acara berjaya ditambah ke dalam kalendar!</div>";
    } else {
        $msg = "<div class='alert error'>Ralat: " . $conn->error . "</div>";
    }
}

// Padam Acara
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM calendar_events WHERE id = $del_id");
    header("Location: admin_calendar.php");
    exit();
}

// Ambil senarai acara akan datang
$sql_events = "SELECT e.*, 
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'Hadir') as rsvp_yes,
                (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'Tidak Hadir') as rsvp_no
               FROM calendar_events e 
               ORDER BY e.event_date ASC";
$events = $conn->query($sql_events);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Kalendar Sekolah - Admin</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); max-width: 1000px; margin: auto; border-top: 8px solid #ffb347; }
        h2 { color: #333; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 13px; }
        input[type="text"], input[type="date"], input[type="time"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        button { background: #ffb347; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #e09e3e; }
        
        .event-card { border: 1px solid #eee; padding: 15px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #ffb347; }
        .event-date { background: #f8f9fa; padding: 10px; border-radius: 8px; text-align: center; min-width: 80px; }
        .event-date .day { font-size: 24px; font-weight: bold; color: #333; line-height: 1; }
        .event-date .month { font-size: 12px; color: #777; text-transform: uppercase; }
        .event-info { flex: 1; margin: 0 20px; }
        .event-info h4 { margin: 0 0 5px 0; color: #333; font-size: 16px; }
        .event-info p { margin: 0; color: #666; font-size: 13px; }
        .event-meta { font-size: 12px; color: #999; margin-top: 5px; display: flex; gap: 15px; }
        
        .rsvp-stats { background: #f0f8ff; padding: 8px 15px; border-radius: 6px; text-align: center; font-size: 12px; border: 1px solid #cce5ff; }
        .rsvp-stats span.yes { color: #28a745; font-weight: bold; }
        .rsvp-stats span.no { color: #dc3545; font-weight: bold; }
        
        .btn-del { background: #ff6961; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; margin-left: 15px; }
        .btn-back { display: inline-block; margin-top: 20px; text-decoration: none; color: #666; font-size: 14px; }
    </style>
</head>
<body>
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <h2>📆 Pengurusan Kalendar Sekolah</h2>
        <?php echo $msg; ?>
        
        <!-- Form Tambah Acara -->
        <div style="background: #fdfbf7; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #f9edd9;">
            <h3 style="margin-top:0; color:#555;">Tambah Acara Baru</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Tajuk Acara</label>
                        <input type="text" name="title" required placeholder="Cth: Sukaneka Tahunan 2026">
                    </div>
                    <div class="form-group">
                        <label>Tarikh</label>
                        <input type="date" name="event_date" required>
                    </div>
                    <div class="form-group">
                        <label>Masa (Pilihan)</label>
                        <input type="time" name="event_time">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Butiran / Penerangan</label>
                        <textarea name="description" rows="2" placeholder="Cth: Sila pakai baju sukan sekolah."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Lokasi</label>
                        <input type="text" name="location" placeholder="Cth: Padang Sekolah">
                    </div>
                </div>
                <button type="submit" name="add_event">Tambah ke Kalendar</button>
            </form>
        </div>

        <!-- Senarai Acara -->
        <h3>Jadual Acara Sekolah</h3>
        <?php if ($events && $events->num_rows > 0): ?>
            <?php while($row = $events->fetch_assoc()): ?>
                <div class="event-card">
                    <div class="event-date">
                        <div class="day"><?php echo date('d', strtotime($row['event_date'])); ?></div>
                        <div class="month"><?php echo date('M Y', strtotime($row['event_date'])); ?></div>
                    </div>
                    <div class="event-info">
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p><?php echo htmlspecialchars($row['description']); ?></p>
                        <div class="event-meta">
                            <span>🕒 <?php echo !empty($row['event_time']) ? date('h:i A', strtotime($row['event_time'])) : 'Sepanjang Hari'; ?></span>
                            <span>📍 <?php echo !empty($row['location']) ? htmlspecialchars($row['location']) : 'Tiada Maklumat Lokasi'; ?></span>
                        </div>
                    </div>
                    <div class="rsvp-stats">
                        RSVP Terkini<br>
                        <span class="yes">✅ <?php echo $row['rsvp_yes']; ?> Hadir</span> &bull; 
                        <span class="no">❌ <?php echo $row['rsvp_no']; ?> Tdk Hadir</span>
                    </div>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-del" onclick="return confirm('Pasti mahu padam acara ini?');">Padam</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:30px; color:#999; border: 1px dashed #ccc; border-radius: 8px;">Tiada acara direkodkan dalam kalendar.</div>
        <?php endif; ?>
        
        <a href="home.php" class="btn-back">⬅️ Kembali ke Dashboard Utama</a>
    </div>

</main>
</body>
</html>
