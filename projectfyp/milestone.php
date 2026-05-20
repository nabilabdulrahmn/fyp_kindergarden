<?php
// milestone.php
// Pencapaian & Perkembangan - Paparan untuk Ibu Bapa sahaja
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
        echo "<script>alert('Akses tidak dibenarkan.'); window.location.href='milestone.php';</script>";
        exit();
    }
}

// Ambil milestone anak yang dipilih
$milestones = array();
$categories = array();
if ($selected_child > 0) {
    $sql_ms = "SELECT m.*, mc.category_name, mc.age_group, t.full_name AS teacher_name
               FROM milestones m 
               INNER JOIN milestone_categories mc ON m.category_id = mc.id 
               LEFT JOIN teachers t ON m.teacher_id = t.id 
               WHERE m.student_id = $selected_child
               ORDER BY mc.category_name, m.observed_date DESC";
    $res_ms = $conn->query($sql_ms);
    if ($res_ms) {
        while ($row = $res_ms->fetch_assoc()) {
            $cat = $row['category_name'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = array();
            }
            $categories[$cat][] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencapaian & Perkembangan - Portal Ibu Bapa</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: linear-gradient(135deg, #e8f4fd 0%, #f0e6ff 100%); min-height: 100vh; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .page-header { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border-left: 6px solid #b084f4; display: flex; align-items: center; justify-content: space-between; }
        .page-header h2 { color: #333; }
        .page-header .subtitle { color: #888; font-size: 13px; margin-top: 4px; }
        .btn-back { text-decoration: none; color: #84b6f4; font-weight: bold; font-size: 14px; padding: 8px 16px; border-radius: 8px; border: 2px solid #84b6f4; transition: 0.3s; }
        .btn-back:hover { background: #84b6f4; color: white; }

        .filter-bar { background: white; border-radius: 16px; padding: 20px 30px; margin-bottom: 25px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); display: flex; gap: 15px; align-items: end; }
        .filter-group { flex: 1; }
        .filter-group label { display: block; font-weight: bold; color: #555; font-size: 13px; margin-bottom: 5px; }
        .filter-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .btn-filter { background: #b084f4; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .btn-filter:hover { background: #9068d4; }

        .category-section { background: white; border-radius: 16px; padding: 25px 30px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .category-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }

        .milestone-item { display: flex; align-items: flex-start; gap: 15px; padding: 14px 0; border-bottom: 1px solid #f5f5f5; }
        .milestone-item:last-child { border-bottom: none; }
        .milestone-status { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
        .status-achieved { background: #d4edda; }
        .status-developing { background: #fff3cd; }
        .status-not-yet { background: #f8d7da; }
        .milestone-info { flex: 1; }
        .milestone-name { font-weight: bold; color: #333; font-size: 15px; }
        .milestone-meta { font-size: 12px; color: #888; margin-top: 4px; }
        .milestone-notes { font-size: 13px; color: #666; margin-top: 6px; background: #f8f9fa; padding: 8px 12px; border-radius: 8px; }
        .status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; color: white; }
        .sb-achieved { background: #28a745; }
        .sb-developing { background: #ffc107; color: #333; }
        .sb-not { background: #dc3545; }

        .empty-state { text-align: center; padding: 50px; color: #aaa; }
        .empty-state .icon { font-size: 50px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div>
                <h2>📈 Pencapaian & Perkembangan</h2>
                <div class="subtitle">Jejak perkembangan anak anda mengikut kategori</div>
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
            <button type="submit" class="btn-filter">🔍 Lihat Perkembangan</button>
        </form>

        <?php if ($selected_child > 0 && count($categories) > 0): ?>
            <?php 
            $cat_keys = array_keys($categories);
            for ($i = 0; $i < count($cat_keys); $i++): 
                $cat_name = $cat_keys[$i];
                $items = $categories[$cat_name];
            ?>
                <div class="category-section">
                    <div class="category-title">🏷️ <?php echo htmlspecialchars($cat_name); ?></div>
                    <?php for ($j = 0; $j < count($items); $j++): ?>
                        <?php 
                            $m = $items[$j];
                            $status_class = 'status-not-yet';
                            $status_icon = '⭕';
                            $badge_class = 'sb-not';
                            if ($m['status'] == 'Telah Capai') { $status_class = 'status-achieved'; $status_icon = '✅'; $badge_class = 'sb-achieved'; }
                            if ($m['status'] == 'Sedang Berkembang') { $status_class = 'status-developing'; $status_icon = '🔄'; $badge_class = 'sb-developing'; }
                        ?>
                        <div class="milestone-item">
                            <div class="milestone-status <?php echo $status_class; ?>"><?php echo $status_icon; ?></div>
                            <div class="milestone-info">
                                <div class="milestone-name">
                                    <?php echo htmlspecialchars($m['milestone_name']); ?>
                                    <span class="status-badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($m['status']); ?></span>
                                </div>
                                <div class="milestone-meta">
                                    <?php if ($m['observed_date']): ?>Tarikh: <?php echo date('d/m/Y', strtotime($m['observed_date'])); ?> | <?php endif; ?>
                                    Guru: <?php echo htmlspecialchars($m['teacher_name'] ?? '-'); ?>
                                </div>
                                <?php if (!empty($m['notes'])): ?>
                                    <div class="milestone-notes"><?php echo nl2br(htmlspecialchars($m['notes'])); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php endfor; ?>
        <?php elseif ($selected_child > 0): ?>
            <div class="category-section empty-state">
                <div class="icon">📈</div>
                <h3>Tiada Rekod Perkembangan</h3>
                <p style="margin-top: 10px;">Guru belum merekodkan pencapaian untuk anak ini.</p>
            </div>
        <?php else: ?>
            <div class="category-section empty-state">
                <div class="icon">👶</div>
                <h3>Sila pilih anak untuk melihat perkembangan.</h3>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
