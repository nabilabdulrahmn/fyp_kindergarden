<?php
// report_card_anak.php
// Kad Laporan & Ulasan - Paparan untuk Ibu Bapa sahaja
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

// Ambil senarai anak
$sql_children = "SELECT id, full_name FROM students WHERE parent_id = $parent_id AND status = 'Active' ORDER BY full_name";
$children = $conn->query($sql_children);

$selected_child = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;

// Sahkan anak milik parent ini
if ($selected_child > 0) {
    $verify = $conn->query("SELECT id FROM students WHERE id = $selected_child AND parent_id = $parent_id LIMIT 1");
    if (!$verify || $verify->num_rows == 0) {
        echo "<script>alert('Akses tidak dibenarkan.'); window.location.href='report_card_anak.php';</script>";
        exit();
    }
}

// Ambil report cards
$report_cards = array();
if ($selected_child > 0) {
    $sql_rc = "SELECT * FROM report_cards WHERE student_id = $selected_child ORDER BY created_at DESC";
    $res_rc = $conn->query($sql_rc);
    if ($res_rc) {
        while ($row = $res_rc->fetch_assoc()) {
            $report_cards[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kad Laporan & Ulasan - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #77dd77; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .filter-bar { background: white; border-radius: 16px; padding: 20px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: end; }
        .filter-group { flex: 1; }
        .filter-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #77dd77; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-filter:hover { background: #5cc55c; }

        .rc-card { background: white; border-radius: 16px; padding: 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .rc-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .rc-term { font-size: 20px; font-weight: bold; color: #333; }
        .rc-date { font-size: 13px; color: #888; }

        .scores-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .score-item { text-align: center; padding: 20px; border-radius: 12px; background: #f8f9fa; }
        .score-label { font-size: 12px; text-transform: uppercase; color: #888; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: bold; }
        .score-value { font-size: 24px; font-weight: bold; color: #333; }

        .comment-section { background: #f0f8e8; padding: 20px; border-radius: 12px; border-left: 4px solid #77dd77; }
        .comment-label { font-weight: bold; color: #555; margin-bottom: 8px; font-size: 14px; }
        .comment-text { color: #444; line-height: 1.6; font-size: 14px; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; }
        .empty-state .icon { font-size: 50px; margin-bottom: 15px; }
    </style>
</head>
<body>
<?php include 'sidebar_parent.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">
    <div class="container">
        <div class="page-header">
            <div>
                <h2>🎓 Kad Laporan & Ulasan</h2>
                <div class="subtitle">Lihat prestasi akademik anak anda</div>
            </div>
            <a href="home.php" class="btn-back">⬅️ Dashboard</a>
        </div>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Pilih Anak</label>
                <select name="child_id" required>
                    <option value="">-- Pilih Anak --</option>
                    <?php if ($children) { while ($c = $children->fetch_assoc()): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($selected_child == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['full_name']); ?>
                        </option>
                    <?php endwhile; } ?>
                </select>
            </div>
            <button type="submit" class="btn-filter">🔍 Lihat Kad Laporan</button>
        </form>

        <?php if ($selected_child > 0 && count($report_cards) > 0): ?>
            <?php for ($i = 0; $i < count($report_cards); $i++): ?>
                <?php $rc = $report_cards[$i]; ?>
                <div class="rc-card">
                    <div class="rc-header">
                        <div class="rc-term">📋 <?php echo htmlspecialchars($rc['term']); ?></div>
                        <div class="rc-date">Tarikh: <?php echo date('d/m/Y', strtotime($rc['created_at'])); ?></div>
                    </div>
                    <div class="scores-grid">
                        <div class="score-item">
                            <div class="score-label">📖 Membaca</div>
                            <div class="score-value"><?php echo htmlspecialchars($rc['reading_score'] ?? '-'); ?></div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">✍️ Menulis</div>
                            <div class="score-value"><?php echo htmlspecialchars($rc['writing_score'] ?? '-'); ?></div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">🤝 Tingkah Laku</div>
                            <div class="score-value"><?php echo htmlspecialchars($rc['behaviour_score'] ?? '-'); ?></div>
                        </div>
                        <div class="score-item">
                            <div class="score-label">💬 Interaksi</div>
                            <div class="score-value"><?php echo htmlspecialchars($rc['interaction_score'] ?? '-'); ?></div>
                        </div>
                    </div>
                    <?php if (!empty($rc['teacher_comment'])): ?>
                        <div class="comment-section">
                            <div class="comment-label">💬 Ulasan Guru:</div>
                            <div class="comment-text"><?php echo nl2br(htmlspecialchars($rc['teacher_comment'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        <?php elseif ($selected_child > 0): ?>
            <div class="rc-card empty-state">
                <div class="icon">🎓</div>
                <h3>Tiada Kad Laporan</h3>
                <p style="margin-top: 10px;">Kad laporan belum dikeluarkan untuk anak ini.</p>
            </div>
        <?php else: ?>
            <div class="rc-card empty-state">
                <div class="icon">📋</div>
                <h3>Sila pilih anak untuk melihat kad laporan.</h3>
            </div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
