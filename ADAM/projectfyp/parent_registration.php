<?php
session_start();
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'parent') {
    header('Location: login.php');
    exit();
}
require 'db.php';

$themeColor = '#84b6f4';

// Get parent_id
$parent_q = $conn->prepare('SELECT id FROM parents WHERE user_id = ?');
$parent_q->bind_param('i', $_SESSION['user_id']);
$parent_q->execute();
$parent_id = $parent_q->get_result()->fetch_assoc()['id'];

$success_msg = '';
$error_msg = '';

// ── POST ACTIONS ──────────────────────────────────────────────

// 1. Register new sibling
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_sibling') {
    $full_name    = trim($_POST['full_name'] ?? '');
    $mykid_number = trim($_POST['mykid_number'] ?? '');
    $dob          = trim($_POST['date_of_birth'] ?? '');
    $module       = trim($_POST['module'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $postcode     = trim($_POST['postcode'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $health       = trim($_POST['health_record'] ?? '');
    $allergies    = trim($_POST['allergies'] ?? '');

    $valid_modules = ['Taska','Tadika','KAFA Care'];

    if($full_name === '' || $mykid_number === '' || $dob === '' || !in_array($module, $valid_modules)) {
        $error_msg = 'Sila lengkapkan semua maklumat yang diperlukan.';
    } else {
        $stmt = $conn->prepare("INSERT INTO students (parent_id, full_name, mykid_number, date_of_birth, module, health_record, allergies, address, postcode, state, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param('isssssssss', $parent_id, $full_name, $mykid_number, $dob, $module, $health, $allergies, $address, $postcode, $state);

        if($stmt->execute()) {
            $new_student_id = $stmt->insert_id;

            // enrollment history
            $eh = $conn->prepare("INSERT INTO enrollment_history (student_id, from_status, to_status, changed_by, changed_at, notes) VALUES (?, NULL, 'Pending', ?, NOW(), 'Pendaftaran baru oleh ibu bapa')");
            $eh->bind_param('ii', $new_student_id, $_SESSION['user_id']);
            $eh->execute();

            // handle file uploads
            @mkdir('uploads/documents', 0777, true);

            $file_fields = [
                'mykid_copy'      => 'MyKid Copy',
                'vaccination_card' => 'Vaccination Card'
            ];
            foreach($file_fields as $field => $doc_type) {
                if(isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                    $filename = time() . '_' . basename($_FILES[$field]['name']);
                    $filepath = 'uploads/documents/' . $filename;
                    if(move_uploaded_file($_FILES[$field]['tmp_name'], $filepath)) {
                        $dq = $conn->prepare("INSERT INTO student_documents (student_id, document_type, file_path, original_filename, uploaded_at, verification_status) VALUES (?, ?, ?, ?, NOW(), 'Pending')");
                        $orig = basename($_FILES[$field]['name']);
                        $dq->bind_param('isss', $new_student_id, $doc_type, $filepath, $orig);
                        $dq->execute();
                    }
                }
            }

            $success_msg = 'Anak berjaya didaftarkan! Status pendaftaran: Menunggu Kelulusan.';
        } else {
            $error_msg = 'Ralat semasa mendaftar. Sila cuba lagi.';
        }
    }
}

// 2. Re-enroll
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reenroll') {
    $student_id = intval($_POST['student_id'] ?? 0);
    // get old status (security check: must belong to parent)
    $chk = $conn->prepare("SELECT status FROM students WHERE id = ? AND parent_id = ?");
    $chk->bind_param('ii', $student_id, $parent_id);
    $chk->execute();
    $chk_r = $chk->get_result()->fetch_assoc();
    if($chk_r) {
        $old_status = $chk_r['status'];
        $upd = $conn->prepare("UPDATE students SET status = 'Pending' WHERE id = ? AND parent_id = ?");
        $upd->bind_param('ii', $student_id, $parent_id);
        $upd->execute();

        $eh = $conn->prepare("INSERT INTO enrollment_history (student_id, from_status, to_status, changed_by, changed_at, notes) VALUES (?, ?, 'Pending', ?, NOW(), 'Permohonan daftar semula')");
        $eh->bind_param('isi', $student_id, $old_status, $_SESSION['user_id']);
        $eh->execute();

        $success_msg = 'Permohonan daftar semula telah dihantar.';
    } else {
        $error_msg = 'Pelajar tidak ditemui.';
    }
}

// 3. Upload document for existing child
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_document') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $doc_type   = trim($_POST['document_type'] ?? '');
    $valid_docs = ['MyKid Copy','Birth Certificate','Health Record','Vaccination Card','Photo'];

    // verify student belongs to parent
    $chk = $conn->prepare("SELECT id FROM students WHERE id = ? AND parent_id = ?");
    $chk->bind_param('ii', $student_id, $parent_id);
    $chk->execute();
    if($chk->get_result()->num_rows > 0 && in_array($doc_type, $valid_docs)) {
        if(isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            @mkdir('uploads/documents', 0777, true);
            $filename = time() . '_' . basename($_FILES['doc_file']['name']);
            $filepath = 'uploads/documents/' . $filename;
            if(move_uploaded_file($_FILES['doc_file']['tmp_name'], $filepath)) {
                $dq = $conn->prepare("INSERT INTO student_documents (student_id, document_type, file_path, original_filename, uploaded_at, verification_status) VALUES (?, ?, ?, ?, NOW(), 'Pending')");
                $orig = basename($_FILES['doc_file']['name']);
                $dq->bind_param('isss', $student_id, $doc_type, $filepath, $orig);
                $dq->execute();
                $success_msg = 'Dokumen berjaya dimuat naik.';
            } else {
                $error_msg = 'Gagal memuat naik fail.';
            }
        } else {
            $error_msg = 'Sila pilih fail untuk dimuat naik.';
        }
    } else {
        $error_msg = 'Maklumat tidak sah.';
    }
}

// ── FETCH CHILDREN ────────────────────────────────────────────
$children_q = $conn->prepare("SELECT * FROM students WHERE parent_id = ? ORDER BY created_at DESC");
$children_q->bind_param('i', $parent_id);
$children_q->execute();
$children = $children_q->get_result();

// Helper: module color
function getModuleColor($module) {
    switch($module) {
        case 'Taska':     return '#ff9aa2';
        case 'Tadika':    return '#a0e8af';
        case 'KAFA Care': return '#b5ead7';
        default:          return '#84b6f4';
    }
}

// Helper: status badge
function getStatusBadge($status) {
    switch($status) {
        case 'Pending':   return ['bg'=>'#ffeeba','color'=>'#856404'];
        case 'Active':    return ['bg'=>'#d4edda','color'=>'#155724'];
        case 'Graduated': return ['bg'=>'#cce5ff','color'=>'#004085'];
        case 'Withdrawn': return ['bg'=>'#f8d7da','color'=>'#721c24'];
        default:          return ['bg'=>'#e2e3e5','color'=>'#383d41'];
    }
}

$doc_types = ['MyKid Copy','Birth Certificate','Health Record','Vaccination Card','Photo'];
$malaysian_states = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Perak','Perlis','Pulau Pinang','Sabah','Sarawak','Selangor','Terengganu','WP Kuala Lumpur','WP Putrajaya','WP Labuan'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pendaftaran Anak — Portal Ibu Bapa</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f7f6;
    color: #333;
    min-height: 100vh;
    display: flex;
}

/* ── SIDEBAR ─────────────────────────────────────── */
.sidebar {
    width: 270px;
    min-height: 100vh;
    background: linear-gradient(180deg, <?php echo $themeColor; ?> 0%, #5a9ae6 100%);
    color: #fff;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 100;
    display: flex;
    flex-direction: column;
    padding-top: 0;
    box-shadow: 4px 0 20px rgba(0,0,0,0.08);
    overflow-y: auto;
}
.sidebar-header {
    padding: 28px 24px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.15);
}
.sidebar-header h2 {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.menu-label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 22px 24px 8px;
    opacity: 0.55;
}
.sidebar a {
    display: block;
    padding: 12px 24px;
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.sidebar a:hover {
    background: rgba(255,255,255,0.12);
    color: #fff;
}
.sidebar a.active {
    background: rgba(255,255,255,0.25);
    font-weight: bold;
    border-left: 4px solid white;
    color: #fff;
}

/* ── MAIN CONTENT ────────────────────────────────── */
.main-content {
    margin-left: 270px;
    flex: 1;
    padding: 32px 36px;
    min-height: 100vh;
}
.page-title {
    font-size: 26px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 6px;
}
.page-subtitle {
    font-size: 14px;
    color: #7f8c8d;
    margin-bottom: 28px;
}

/* ── ALERTS ──────────────────────────────────────── */
.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 22px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideDown 0.3s ease;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* ── FLEX ROW LAYOUT ─────────────────────────────── */
.content-row {
    display: flex;
    gap: 28px;
    align-items: flex-start;
}
.children-section {
    flex: 2;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.register-section {
    flex: 1;
    min-width: 340px;
}

/* ── CARDS ───────────────────────────────────────── */
.card {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.card-header {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.card-header h3 {
    font-size: 17px;
    font-weight: 700;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-body {
    padding: 0 24px 22px;
}

/* ── PILLS & BADGES ──────────────────────────────── */
.module-pill {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    letter-spacing: 0.3px;
}
.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* ── CHILD INFO ──────────────────────────────────── */
.child-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px 24px;
    margin-bottom: 16px;
}
.info-item {
    font-size: 13px;
}
.info-label {
    color: #95a5a6;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 2px;
}
.info-value {
    color: #2c3e50;
    font-weight: 500;
}

/* ── DOCUMENT STATUS ─────────────────────────────── */
.doc-status-section {
    margin-top: 12px;
    padding-top: 14px;
    border-top: 1px solid #eef1f0;
}
.doc-status-title {
    font-size: 12px;
    font-weight: 700;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 10px;
}
.doc-status-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.doc-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f8f9fa;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    color: #555;
    position: relative;
}
.doc-item .tooltip-text {
    visibility: hidden;
    background: #333;
    color: #fff;
    text-align: center;
    border-radius: 8px;
    padding: 6px 12px;
    position: absolute;
    z-index: 10;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    font-size: 11px;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.2s;
    pointer-events: none;
}
.doc-item .tooltip-text::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #333 transparent transparent transparent;
}
.doc-item:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

/* ── COLLAPSIBLE UPLOAD ──────────────────────────── */
.upload-toggle {
    margin-top: 14px;
}
.upload-toggle-btn {
    background: none;
    border: 1px dashed #bdc3c7;
    border-radius: 10px;
    padding: 10px 18px;
    font-size: 13px;
    color: #7f8c8d;
    cursor: pointer;
    width: 100%;
    text-align: center;
    transition: all 0.2s ease;
}
.upload-toggle-btn:hover {
    border-color: <?php echo $themeColor; ?>;
    color: <?php echo $themeColor; ?>;
    background: rgba(132,182,244,0.05);
}
.upload-panel {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.35s ease, padding 0.35s ease;
    background: #f8f9fa;
    border-radius: 10px;
    margin-top: 10px;
}
.upload-panel.open {
    max-height: 300px;
    padding: 16px;
}
.upload-panel form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: flex-end;
}
.upload-panel select,
.upload-panel input[type="file"] {
    flex: 1;
    min-width: 140px;
    padding: 8px 12px;
    border: 1px solid #dde1e3;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    background: #fff;
}

/* ── BUTTONS ─────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 22px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s ease;
}
.btn-primary {
    background: linear-gradient(135deg, <?php echo $themeColor; ?>, #5a9ae6);
    color: #fff;
    box-shadow: 0 3px 10px rgba(90,154,230,0.3);
}
.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(90,154,230,0.4);
}
.btn-sm {
    padding: 7px 16px;
    font-size: 12px;
    border-radius: 8px;
}
.btn-outline {
    background: transparent;
    border: 1.5px solid <?php echo $themeColor; ?>;
    color: <?php echo $themeColor; ?>;
}
.btn-outline:hover {
    background: <?php echo $themeColor; ?>;
    color: #fff;
}
.btn-reenroll {
    background: linear-gradient(135deg, #ffeeba, #ffd966);
    color: #856404;
    font-weight: 600;
}
.btn-reenroll:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255,217,102,0.4);
}

/* ── REGISTRATION FORM ───────────────────────────── */
.section-title {
    font-size: 16px;
    font-weight: 700;
    color: #2c3e50;
    padding: 22px 24px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.reg-form {
    padding: 20px 24px 24px;
}
.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #555;
    margin-bottom: 6px;
}
.form-group label .required {
    color: #e74c3c;
}
.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #dde1e3;
    border-radius: 10px;
    font-size: 13px;
    font-family: inherit;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    background: #fff;
}
.form-control:focus {
    outline: none;
    border-color: <?php echo $themeColor; ?>;
    box-shadow: 0 0 0 3px rgba(132,182,244,0.15);
}
textarea.form-control {
    resize: vertical;
    min-height: 60px;
}
.file-input-wrapper {
    position: relative;
}
.file-input-wrapper input[type="file"] {
    width: 100%;
    padding: 8px 12px;
    border: 1.5px dashed #ccd1d3;
    border-radius: 10px;
    font-size: 12px;
    font-family: inherit;
    background: #fafbfc;
    cursor: pointer;
    transition: border-color 0.2s;
}
.file-input-wrapper input[type="file"]:hover {
    border-color: <?php echo $themeColor; ?>;
}

/* ── EMPTY STATE ─────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #bdc3c7;
}
.empty-state .icon {
    font-size: 48px;
    margin-bottom: 12px;
}
.empty-state p {
    font-size: 15px;
    font-weight: 500;
}

/* ── ANIMATIONS ──────────────────────────────────── */
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.card { animation: fadeIn 0.4s ease; }

/* ── RESPONSIVE ──────────────────────────────────── */
@media (max-width: 1100px) {
    .content-row { flex-direction: column; }
    .register-section { min-width: unset; }
}
@media (max-width: 768px) {
    .sidebar { width: 220px; }
    .main-content { margin-left: 220px; padding: 20px; }
    .child-info-grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ── SIDEBAR ──────────────────────────────────────── -->
<div class='sidebar'>
  <div class='sidebar-header'><h2>Portal Ibu Bapa</h2></div>
  <div class='menu-label'>ANAK SAYA</div>
  <a href='home.php'>Dashboard</a>
  <a href='parent_registration.php' class='active'>Pendaftaran Anak</a>
  <div class='menu-label'>KEWANGAN</div>
  <a href='parent_invoices.php'>Invois &amp; Pembayaran</a>
  <a href='parent_receipts.php'>Sejarah Pembayaran</a>
  <div class='menu-label'>AKADEMIK</div>
  <a href='home.php'>Laporan Aktiviti</a>
  <a href='home.php'>Prestasi Anak</a>
  <div class='menu-label'>AKAUN</div>
  <a href='logout.php' style='color:#ff6961;'>Log Keluar</a>
</div>

<!-- ── MAIN CONTENT ────────────────────────────────── -->
<div class="main-content">
    <h1 class="page-title">Pendaftaran Anak</h1>
    <p class="page-subtitle">Urus pendaftaran dan dokumen anak anda di sini.</p>

    <?php if($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <div class="content-row">

        <!-- ── SECTION 1: Registered Children ──────────── -->
        <div class="children-section">
            <h2 style="font-size:18px; font-weight:700; color:#2c3e50; margin-bottom:4px;">📋 Anak-Anak Berdaftar</h2>

            <?php if($children->num_rows === 0): ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="icon">👶</div>
                        <p>Tiada anak berdaftar lagi.</p>
                        <p style="font-size:13px; margin-top:6px; color:#ccc;">Sila daftarkan anak anda menggunakan borang di sebelah.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php while($child = $children->fetch_assoc()):
                    $moduleColor = getModuleColor($child['module']);
                    $statusBadge = getStatusBadge($child['status']);

                    // fetch documents for this child
                    $docs_q = $conn->prepare("SELECT document_type, verification_status, rejection_reason FROM student_documents WHERE student_id = ?");
                    $docs_q->bind_param('i', $child['id']);
                    $docs_q->execute();
                    $docs_result = $docs_q->get_result();
                    $uploaded_docs = [];
                    while($d = $docs_result->fetch_assoc()) {
                        $uploaded_docs[$d['document_type']] = $d;
                    }
                ?>
                <div class="card" style="border-left: 5px solid <?php echo $moduleColor; ?>;">
                    <div class="card-header">
                        <h3>
                            <?php echo htmlspecialchars($child['full_name']); ?>
                            <span class="module-pill" style="background:<?php echo $moduleColor; ?>;">
                                <?php echo htmlspecialchars($child['module']); ?>
                            </span>
                        </h3>
                        <span class="status-badge" style="background:<?php echo $statusBadge['bg']; ?>; color:<?php echo $statusBadge['color']; ?>;">
                            <?php echo htmlspecialchars($child['status']); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="child-info-grid">
                            <div class="info-item">
                                <div class="info-label">No. MyKid</div>
                                <div class="info-value"><?php echo htmlspecialchars($child['mykid_number']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tarikh Lahir</div>
                                <div class="info-value"><?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></div>
                            </div>
                        </div>

                        <!-- Document Status -->
                        <div class="doc-status-section">
                            <div class="doc-status-title">Status Dokumen</div>
                            <div class="doc-status-grid">
                                <?php foreach($doc_types as $dt): ?>
                                    <div class="doc-item">
                                        <?php if(isset($uploaded_docs[$dt])): ?>
                                            <?php
                                            $vs = $uploaded_docs[$dt]['verification_status'];
                                            if($vs === 'Verified'): ?>
                                                ✅
                                            <?php elseif($vs === 'Pending'): ?>
                                                ⏳
                                            <?php elseif($vs === 'Rejected'): ?>
                                                ❌
                                                <span class="tooltip-text"><?php echo htmlspecialchars($uploaded_docs[$dt]['rejection_reason'] ?? 'Tiada sebab diberikan'); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            ➕
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($dt); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Re-enroll button -->
                        <?php if($child['status'] === 'Graduated' || $child['status'] === 'Withdrawn'): ?>
                            <form method="POST" style="margin-top:14px;">
                                <input type="hidden" name="action" value="reenroll">
                                <input type="hidden" name="student_id" value="<?php echo $child['id']; ?>">
                                <button type="submit" class="btn btn-reenroll btn-sm">🔄 Daftar Semula</button>
                            </form>
                        <?php endif; ?>

                        <!-- Collapsible Upload -->
                        <div class="upload-toggle">
                            <button type="button" class="upload-toggle-btn" onclick="toggleUpload(this)">📎 Muat Naik Dokumen</button>
                            <div class="upload-panel">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_document">
                                    <input type="hidden" name="student_id" value="<?php echo $child['id']; ?>">
                                    <select name="document_type" required>
                                        <option value="">Jenis Dokumen</option>
                                        <?php foreach($doc_types as $dt): ?>
                                            <option value="<?php echo htmlspecialchars($dt); ?>"><?php echo htmlspecialchars($dt); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="file" name="doc_file" required>
                                    <button type="submit" class="btn btn-primary btn-sm">Muat Naik</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ── SECTION 2: Register New Sibling ─────────── -->
        <div class="register-section">
            <div class="card">
                <div class="section-title">➕ Daftar Adik-Beradik Baru</div>
                <form class="reg-form" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="register_sibling">

                    <div class="form-group">
                        <label>Nama Penuh Anak <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" required placeholder="cth: Ahmad bin Abu">
                    </div>

                    <div class="form-group">
                        <label>No. MyKid <span class="required">*</span></label>
                        <input type="text" name="mykid_number" class="form-control" required placeholder="cth: 120101-01-1234">
                    </div>

                    <div class="form-group">
                        <label>Tarikh Lahir <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Modul <span class="required">*</span></label>
                        <select name="module" class="form-control" required>
                            <option value="">Pilih modul...</option>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Alamat penuh"></textarea>
                    </div>

                    <div class="form-group" style="display:flex; gap:10px;">
                        <div style="flex:1;">
                            <label>Poskod</label>
                            <input type="text" name="postcode" class="form-control" placeholder="cth: 50000">
                        </div>
                        <div style="flex:2;">
                            <label>Negeri</label>
                            <select name="state" class="form-control">
                                <option value="">Pilih negeri...</option>
                                <?php foreach($malaysian_states as $st): ?>
                                    <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Rekod Kesihatan</label>
                        <textarea name="health_record" class="form-control" rows="2" placeholder="Maklumat kesihatan (jika ada)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Alahan</label>
                        <textarea name="allergies" class="form-control" rows="2" placeholder="Senarai alahan (jika ada)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>📄 Salinan MyKid</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="mykid_copy">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>💉 Kad Vaksinasi</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="vaccination_card">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width:100%; margin-top:6px;">
                        🎓 Daftar Anak Baru
                    </button>
                </form>
            </div>
        </div>

    </div><!-- .content-row -->
</div><!-- .main-content -->

<script>
function toggleUpload(btn) {
    const panel = btn.nextElementSibling;
    panel.classList.toggle('open');
    btn.textContent = panel.classList.contains('open') ? '✕ Tutup' : '📎 Muat Naik Dokumen';
}
</script>

</body>
</html>
