<?php
// admin_document_verification.php — Pengesahan Dokumen (Document Verification)
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header('Location: login.php');
    exit();
}

$themeColor = '#77dd77';
$msg = '';
$msgType = '';

// ======================== POST HANDLERS ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $uid = $_SESSION['user_id'];
    $uname = $_SESSION['username'];

    // --- Verify Document ---
    if (isset($_POST['verify_document'])) {
        $doc_id = (int)$_POST['document_id'];

        $stmt = $conn->prepare("UPDATE student_documents SET verification_status='Verified', verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $uid, $doc_id);
        $stmt->execute();
        $stmt->close();

        // Get student_id for this document
        $stmt = $conn->prepare("SELECT student_id FROM student_documents WHERE id=?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $drow = $res->fetch_assoc();
        $student_id = $drow['student_id'];
        $stmt->close();

        // Check if ALL documents for this student are now Verified
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM student_documents WHERE student_id=?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $conn->prepare("SELECT COUNT(*) AS verified FROM student_documents WHERE student_id=? AND verification_status='Verified'");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $verified = $stmt->get_result()->fetch_assoc()['verified'];
        $stmt->close();

        if ($total > 0 && $total == $verified) {
            $stmt = $conn->prepare("UPDATE students SET documents_verified=1 WHERE id=?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $stmt->close();
        }

        // System log
        $action = "Mengesahkan dokumen ID: $doc_id untuk pelajar ID: $student_id";
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
        $stmt->bind_param("isss", $uid, $uname, $action, $ip);
        $stmt->execute();
        $stmt->close();

        $msg = 'Dokumen berjaya disahkan.';
        $msgType = 'success';
    }

    // --- Reject Document ---
    if (isset($_POST['reject_document'])) {
        $doc_id = (int)$_POST['document_id'];
        $reason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $conn->prepare("UPDATE student_documents SET verification_status='Rejected', rejection_reason=? WHERE id=?");
        $stmt->bind_param("si", $reason, $doc_id);
        $stmt->execute();
        $stmt->close();

        // Get student_id for logging
        $stmt = $conn->prepare("SELECT student_id FROM student_documents WHERE id=?");
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();
        $student_id = $stmt->get_result()->fetch_assoc()['student_id'];
        $stmt->close();

        // System log
        $action = "Menolak dokumen ID: $doc_id untuk pelajar ID: $student_id. Sebab: $reason";
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, 'Success', ?)");
        $stmt->bind_param("isss", $uid, $uname, $action, $ip);
        $stmt->execute();
        $stmt->close();

        $msg = 'Dokumen telah ditolak.';
        $msgType = 'error';
    }
}

// ======================== FILTERS ========================
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_name = isset($_GET['name']) ? trim($_GET['name']) : '';

// Build query with filters
$where = [];
$params = [];
$types = '';

if ($filter_status !== '' && in_array($filter_status, ['Pending', 'Verified', 'Rejected'])) {
    $where[] = "sd.verification_status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($filter_name !== '') {
    $where[] = "s.full_name LIKE ?";
    $params[] = '%' . $filter_name . '%';
    $types .= 's';
}

$sql = "SELECT sd.*, s.full_name AS student_name, s.module 
        FROM student_documents sd 
        JOIN students s ON sd.student_id = s.id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY sd.uploaded_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result();
$doc_count = $documents ? $documents->num_rows : 0;
$stmt->close();

// Counts for summary
$count_pending = $conn->query("SELECT COUNT(*) AS c FROM student_documents WHERE verification_status='Pending'")->fetch_assoc()['c'];
$count_verified = $conn->query("SELECT COUNT(*) AS c FROM student_documents WHERE verification_status='Verified'")->fetch_assoc()['c'];
$count_rejected = $conn->query("SELECT COUNT(*) AS c FROM student_documents WHERE verification_status='Rejected'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengesahan Dokumen - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 270px; background-color: <?php echo $themeColor; ?>; color: white; padding-top: 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow-y: auto; position: fixed; top: 0; left: 0; height: 100vh; z-index: 100; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h2 { margin: 0; font-size: 22px; }
        .menu-label { padding: 15px 20px 5px; font-size: 11px; font-weight: 700; letter-spacing: 1px; opacity: 0.7; }
        .sidebar a { padding: 12px 20px; text-decoration: none; font-size: 14px; color: white; display: block; border-bottom: 1px solid rgba(255,255,255,0.1); transition: all 0.3s; }
        .sidebar a:hover { background-color: rgba(255,255,255,0.2); padding-left: 25px; }
        .sidebar a.active { background: rgba(255,255,255,0.25); font-weight: bold; border-left: 4px solid white; }

        /* Content */
        .content { margin-left: 270px; flex: 1; padding: 30px 40px; overflow-y: auto; min-height: 100vh; }
        h1 { color: #333; margin-bottom: 5px; font-size: 26px; }
        .subtitle { color: #888; margin-bottom: 25px; font-size: 14px; }

        /* Alert banner */
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; animation: slideDown 0.3s ease; }
        .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
        .alert .close-btn { background: none; border: none; font-size: 20px; cursor: pointer; color: inherit; opacity: 0.7; }
        .alert .close-btn:hover { opacity: 1; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* Summary cards */
        .summary-row { display: flex; gap: 15px; margin-bottom: 25px; }
        .summary-card { flex: 1; background: white; border-radius: 10px; padding: 18px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .summary-card .number { font-size: 28px; font-weight: 700; }
        .summary-card .label { font-size: 12px; color: #888; margin-top: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .summary-pending { border-top: 4px solid #f0ad4e; }
        .summary-pending .number { color: #f0ad4e; }
        .summary-verified { border-top: 4px solid #28a745; }
        .summary-verified .number { color: #28a745; }
        .summary-rejected { border-top: 4px solid #dc3545; }
        .summary-rejected .number { color: #dc3545; }

        /* Filter bar */
        .filter-bar { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); margin-bottom: 25px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .filter-bar label { font-weight: 600; color: #555; font-size: 13px; }
        .filter-bar select, .filter-bar input[type="text"] {
            padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8f9fa; transition: border 0.3s;
        }
        .filter-bar select:focus, .filter-bar input[type="text"]:focus { border-color: <?php echo $themeColor; ?>; outline: none; }
        .filter-bar input[type="text"] { min-width: 200px; }
        .btn-filter { padding: 10px 20px; background: <?php echo $themeColor; ?>; color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; transition: all 0.2s; }
        .btn-filter:hover { background: #5cbd5c; transform: translateY(-1px); }

        /* Document cards grid */
        .doc-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        @media (max-width: 1200px) { .doc-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .doc-grid { grid-template-columns: 1fr; } }

        .doc-card { background: white; border-radius: 12px; box-shadow: 0 3px 15px rgba(0,0,0,0.05); overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; }
        .doc-card:hover { transform: translateY(-3px); box-shadow: 0 6px 25px rgba(0,0,0,0.1); }
        .doc-card.border-pending { border-left: 5px solid #f0ad4e; }
        .doc-card.border-verified { border-left: 5px solid #28a745; }
        .doc-card.border-rejected { border-left: 5px solid #dc3545; }

        .doc-card-header { padding: 18px 20px 12px; border-bottom: 1px solid #f0f0f0; }
        .doc-card-header .student-name { font-size: 16px; font-weight: 700; color: #333; }
        .doc-card-body { padding: 15px 20px; }
        .doc-card-body .info-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 13px; color: #666; }
        .doc-card-body .info-row .label { color: #aaa; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-card-footer { padding: 12px 20px; background: #fafafa; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

        /* Tags & Badges */
        .module-tag { padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 700; color: white; display: inline-block; margin-left: 8px; }
        .tag-taska { background: #ff9aa2; }
        .tag-tadika { background: #a0e8af; color: #1a5928; }
        .tag-kafacare { background: #b5ead7; color: #2d6a4f; }

        .doc-type-badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 700; background: #e8e8e8; color: #555; display: inline-block; }

        .verify-badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; display: inline-block; }
        .verify-pending { background: #fff3cd; color: #856404; }
        .verify-verified { background: #d4edda; color: #155724; }
        .verify-rejected { background: #f8d7da; color: #721c24; }

        .rejection-note { background: #fff5f5; border: 1px solid #fed7d7; border-radius: 6px; padding: 8px 12px; font-size: 12px; color: #c53030; margin-top: 8px; }

        /* Buttons */
        .btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 700; transition: all 0.2s; display: inline-block; text-decoration: none; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .btn-verify { background: #28a745; color: white; }
        .btn-verify:hover { background: #218838; }
        .btn-reject-doc { background: #dc3545; color: white; }
        .btn-reject-doc:hover { background: #c82333; }
        .btn-view { background: #17a2b8; color: white; text-decoration: none; font-size: 12px; padding: 6px 12px; border-radius: 6px; font-weight: 600; }
        .btn-view:hover { background: #138496; }

        /* Reject form */
        .reject-form { display: none; margin-top: 10px; padding: 12px; background: #fff5f5; border-radius: 8px; border: 1px solid #fed7d7; }
        .reject-form textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; font-size: 13px; resize: vertical; min-height: 60px; }
        .reject-form .btn { margin-top: 8px; }

        /* File info */
        .file-info { font-size: 12px; color: #888; display: flex; align-items: center; gap: 5px; }
        .file-info .icon { font-size: 14px; }

        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state .icon { font-size: 56px; margin-bottom: 15px; }
        .empty-state p { font-size: 16px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header"><h2>Admin Panel</h2></div>
    <div class="menu-label">PENGURUSAN PELAJAR</div>
    <a href="senarai_pelajar.php">Senarai Pelajar</a>
    <a href="admin_enrollment.php">Pendaftaran &amp; Pengesahan</a>
    <a href="admin_document_verification.php" class="active">Pengesahan Dokumen</a>
    <a href="admin_enrollment_tracking.php">Status Pendaftaran</a>
    <a href="senarai_kehadiran.php">Kehadiran</a>
    <a href="arkib_pelajar.php">Arkib Pelajar</a>
    <div class="menu-label">KEWANGAN</div>
    <a href="admin_financial.php">Invois &amp; Pembayaran</a>
    <a href="admin_expense_report.php">Perbelanjaan &amp; Laporan</a>
    <div class="menu-label">AKADEMIK</div>
    <a href="aktiviti_kelas.php">Jadual Aktiviti</a>
    <a href="lesson_plan.php">Rancangan Pengajaran</a>
    <div class="menu-label">SISTEM</div>
    <a href="logout.php" style="color:#ff6961;">Log Keluar</a>
</div>

<!-- Content -->
<div class="content">
    <h1>📄 Pengesahan Dokumen</h1>
    <p class="subtitle">Semak dan sahkan dokumen pelajar yang telah dimuat naik.</p>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>" id="alertBanner">
        <span><?php echo htmlspecialchars($msg); ?></span>
        <button class="close-btn" onclick="document.getElementById('alertBanner').style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-row">
        <div class="summary-card summary-pending">
            <div class="number"><?php echo (int)$count_pending; ?></div>
            <div class="label">⏳ Menunggu Pengesahan</div>
        </div>
        <div class="summary-card summary-verified">
            <div class="number"><?php echo (int)$count_verified; ?></div>
            <div class="label">✅ Disahkan</div>
        </div>
        <div class="summary-card summary-rejected">
            <div class="number"><?php echo (int)$count_rejected; ?></div>
            <div class="label">❌ Ditolak</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <label>Status:</label>
        <select name="status">
            <option value="">Semua</option>
            <option value="Pending" <?php echo $filter_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Verified" <?php echo $filter_status === 'Verified' ? 'selected' : ''; ?>>Verified</option>
            <option value="Rejected" <?php echo $filter_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
        <label>Nama Pelajar:</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($filter_name); ?>" placeholder="Cari nama pelajar...">
        <button type="submit" class="btn-filter">🔍 Tapis</button>
    </form>

    <!-- Document Cards -->
    <?php if ($doc_count > 0): ?>
    <div class="doc-grid">
        <?php while ($doc = $documents->fetch_assoc()):
            $modSlug = strtolower(str_replace(' ', '', $doc['module']));
            $tagClass = 'tag-' . $modSlug;
            $borderClass = 'border-' . strtolower($doc['verification_status']);
            $badgeClass = 'verify-' . strtolower($doc['verification_status']);
        ?>
        <div class="doc-card <?php echo $borderClass; ?>">
            <div class="doc-card-header">
                <span class="student-name"><?php echo htmlspecialchars($doc['student_name']); ?></span>
                <span class="module-tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($doc['module']); ?></span>
            </div>
            <div class="doc-card-body">
                <div class="info-row">
                    <span class="doc-type-badge">📎 <?php echo htmlspecialchars($doc['document_type']); ?></span>
                    <span class="verify-badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($doc['verification_status']); ?></span>
                </div>
                <div class="info-row">
                    <div>
                        <div class="label">Nama Fail</div>
                        <div class="file-info"><span class="icon">📁</span> <?php echo htmlspecialchars($doc['original_filename'] ?? '-'); ?></div>
                    </div>
                </div>
                <div class="info-row">
                    <div>
                        <div class="label">Tarikh Muat Naik</div>
                        <span><?php echo !empty($doc['uploaded_at']) ? date('d-m-Y', strtotime($doc['uploaded_at'])) : '-'; ?></span>
                    </div>
                    <?php if (!empty($doc['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-view">👁 Lihat Dokumen</a>
                    <?php endif; ?>
                </div>

                <?php if ($doc['verification_status'] === 'Rejected' && !empty($doc['rejection_reason'])): ?>
                <div class="rejection-note">
                    <strong>Sebab Penolakan:</strong> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($doc['verification_status'] === 'Pending'): ?>
            <div class="doc-card-footer">
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                    <button type="submit" name="verify_document" class="btn btn-verify">✔ Sahkan</button>
                </form>
                <button type="button" class="btn btn-reject-doc" onclick="toggleReject(<?php echo $doc['id']; ?>)">✖ Tolak</button>

                <div class="reject-form" id="reject-form-<?php echo $doc['id']; ?>">
                    <form method="POST">
                        <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                        <label style="font-size:12px;font-weight:600;color:#666;">Sebab Penolakan:</label>
                        <textarea name="rejection_reason" placeholder="Nyatakan sebab penolakan..." required></textarea>
                        <button type="submit" name="reject_document" class="btn btn-reject-doc">Hantar Penolakan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="icon">📂</div>
        <p>Tiada dokumen untuk disemak</p>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleReject(docId) {
    var form = document.getElementById('reject-form-' + docId);
    if (form.style.display === 'block') {
        form.style.display = 'none';
    } else {
        form.style.display = 'block';
    }
}
</script>

</body>
</html>
