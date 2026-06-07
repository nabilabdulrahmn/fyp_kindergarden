<?php
// parent_register_child.php
// Borang Pendaftaran Anak Baru — Wizard 5 Langkah
require_once 'auth_guard.php';
sahkan_peranan('parent');
require_once 'db.php';

// ── Helper includes with fallbacks ──
if (file_exists('includes/csrf_helper.php')) { require_once 'includes/csrf_helper.php'; }
else {
    function csrf_input() {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
    }
    function validate_csrf_token($t) { return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t); }
}
if (file_exists('includes/notification_helper.php')) { require_once 'includes/notification_helper.php'; }
else {
    function sendNotification($conn, $uid, $title, $msg, $type='info', $link='') {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $uid, $title, $msg, $type, $link);
        $stmt->execute(); $stmt->close();
    }
    function notifyAdmins($conn, $title, $msg, $type='info', $link='') {
        $r = $conn->query("SELECT id FROM users WHERE role='admin'");
        while ($r && $row = $r->fetch_assoc()) { sendNotification($conn, $row['id'], $title, $msg, $type, $link); }
    }
}
if (file_exists('includes/log_helper.php')) { require_once 'includes/log_helper.php'; }
else {
    function logAction($conn, $action, $status='Success') {
        $uid = $_SESSION['user_id'] ?? 0; $uname = $_SESSION['username'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $uid, $uname, $action, $status, $ip);
        $stmt->execute(); $stmt->close();
    }
}
if (file_exists('includes/upload_helper.php')) { require_once 'includes/upload_helper.php'; }
else {
    function validateUpload($file, $allowed, $maxSize) {
        if ($file['error'] !== UPLOAD_ERR_OK) return ['valid'=>false,'error'=>'Gagal muat naik fail.'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return ['valid'=>false,'error'=>'Jenis fail tidak dibenarkan.'];
        if ($file['size'] > $maxSize) return ['valid'=>false,'error'=>'Saiz fail melebihi had.'];
        return ['valid'=>true,'error'=>''];
    }
    function saveUpload($file, $dir, $prefix='') {
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = $prefix . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = rtrim($dir,'/').'/'.$name;
        return move_uploaded_file($file['tmp_name'], $path) ? $path : false;
    }
}

$username    = $_SESSION['username'];
$uid         = (int)$_SESSION['user_id'];
$parent_id   = dapatkan_parent_id($conn);
$parent_name = $username;

$r = mysqli_query($conn, "SELECT full_name FROM parents WHERE id=$parent_id LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) $parent_name = $row['full_name'] ?: $username;

// ── Create helper tables if missing ──
$conn->query("CREATE TABLE IF NOT EXISTS `application_documents` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `application_id` INT NOT NULL,
    `document_name` VARCHAR(100) NOT NULL,
    `document_type` VARCHAR(50) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `status` VARCHAR(20) DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS `sibling_links` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `parent_id` INT NOT NULL,
    `student_id_1` INT NOT NULL,
    `student_id_2` INT NULL,
    `application_id` INT NULL,
    `verified_by_admin` TINYINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), KEY (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Fetch data for the wizard ──
// Existing children (for sibling check)
$existing_children = [];
$r = $conn->query("SELECT id, full_name FROM students WHERE parent_id=$parent_id AND status='Active' ORDER BY full_name");
if ($r) while ($row = $r->fetch_assoc()) $existing_children[] = $row;

// Classes with enrollment count
$classes_data = [];
$r = $conn->query("SELECT c.id, c.class_name, c.module, t.full_name AS teacher_name,
    (SELECT COUNT(*) FROM student_classes sc WHERE sc.class_id = c.id) AS enrolled
    FROM classes c LEFT JOIN teachers t ON t.id = c.teacher_id ORDER BY c.module, c.class_name");
if ($r) while ($row = $r->fetch_assoc()) $classes_data[] = $row;

// Fee structures
$fees_data = [];
$r = $conn->query("SELECT * FROM fee_structures WHERE is_active=1 ORDER BY module, frequency");
if ($r) while ($row = $r->fetch_assoc()) $fees_data[] = $row;

// Transportation routes
$routes_data = [];
$r = $conn->query("SELECT * FROM transportation WHERE status='Active' ORDER BY route_name");
if ($r) while ($row = $r->fetch_assoc()) $routes_data[] = $row;

// ── POST Handler ──
$success_msg = ''; $error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // CSRF
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = 'Token keselamatan tidak sah. Sila cuba lagi.';
    } else {
        // Collect & validate
        $child_name   = trim($_POST['child_name'] ?? '');
        $child_mykid  = trim($_POST['child_mykid'] ?? '');
        $child_dob    = trim($_POST['child_dob'] ?? '');
        $jantina      = trim($_POST['jantina'] ?? '');
        $bangsa        = trim($_POST['bangsa'] ?? '');
        $agama         = trim($_POST['agama'] ?? '');
        $warganegara   = trim($_POST['warganegara'] ?? '');
        $blood_type   = trim($_POST['blood_type'] ?? '');
        $allergies_arr = $_POST['allergies'] ?? [];
        $allergy_details = trim($_POST['allergy_details'] ?? '');
        $medical_cond = trim($_POST['medical_condition'] ?? '');
        $special_needs = trim($_POST['special_needs_detail'] ?? '');
        $doctor_name  = trim($_POST['doctor_name'] ?? '');
        $doctor_phone = trim($_POST['doctor_phone'] ?? '');
        $selected_class = (int)($_POST['selected_class'] ?? 0);
        $need_transport = (int)($_POST['need_transport'] ?? 0);
        $selected_route = (int)($_POST['selected_route'] ?? 0);
        $is_sibling    = (int)($_POST['is_sibling'] ?? 0);
        $agree_terms   = isset($_POST['agree_terms']);

        if (empty($child_name) || empty($child_mykid) || empty($child_dob)) {
            $error_msg = 'Sila lengkapkan maklumat anak.';
        } elseif ($selected_class <= 0) {
            $error_msg = 'Sila pilih kelas.';
        } elseif (!$agree_terms) {
            $error_msg = 'Sila bersetuju dengan terma dan syarat.';
        } else {
            // Determine module from selected class
            $module = 'Taska';
            foreach ($classes_data as $c) {
                if ($c['id'] == $selected_class) { $module = $c['module']; break; }
            }

            // Compile health record
            $health = "Golongan Darah: $blood_type\n";
            $allergy_str = implode(', ', $allergies_arr);
            if ($allergy_details) $allergy_str .= " — $allergy_details";
            $health .= "Jantina: $jantina | Bangsa: $bangsa | Agama: $agama | Warganegara: $warganegara\n";
            $health .= "Keadaan Perubatan: " . ($medical_cond ?: 'Tiada') . "\n";
            $health .= "Keperluan Khas: " . ($special_needs ?: 'Tiada') . "\n";
            $health .= "Doktor: $doctor_name | Tel: $doctor_phone";

            // Upload child photo
            $photo_path = '';
            if (isset($_FILES['child_photo']) && $_FILES['child_photo']['error'] === UPLOAD_ERR_OK) {
                $v = validateUpload($_FILES['child_photo'], ['jpg','jpeg','png'], 2*1024*1024);
                if ($v['valid']) $photo_path = saveUpload($_FILES['child_photo'], 'uploads/photos/', 'child_');
            }

            // Upload vaccination record
            $vacc_path = '';
            if (isset($_FILES['vaccination_record']) && $_FILES['vaccination_record']['error'] === UPLOAD_ERR_OK) {
                $v = validateUpload($_FILES['vaccination_record'], ['jpg','jpeg','png','pdf'], 5*1024*1024);
                if ($v['valid']) $vacc_path = saveUpload($_FILES['vaccination_record'], 'uploads/documents/', 'vacc_');
            }

            // Insert application
            $notes_json = json_encode(['photo'=>$photo_path, 'vaccination'=>$vacc_path, 'transport'=>$need_transport ? $selected_route : 0]);
            $stmt = $conn->prepare("INSERT INTO applications (parent_id, child_name, child_mykid, child_dob, module, health_record, allergies, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
            $stmt->bind_param("isssssss", $parent_id, $child_name, $child_mykid, $child_dob, $module, $health, $allergy_str, $notes_json);

            if ($stmt->execute()) {
                $app_id = $stmt->insert_id;
                $stmt->close();

                // Upload & insert documents
                $doc_types = [
                    'doc_mykid'   => ['name'=>'Salinan MyKid','type'=>'MyKid'],
                    'doc_ic'      => ['name'=>'Salinan IC Ibu/Bapa','type'=>'Parent IC'],
                    'doc_photo'   => ['name'=>'Gambar Passport','type'=>'Passport Photo'],
                    'doc_vacc'    => ['name'=>'Rekod Vaksinasi','type'=>'Vaccination Record'],
                    'doc_health'  => ['name'=>'Borang Kesihatan','type'=>'Health Declaration']
                ];
                foreach ($doc_types as $field => $info) {
                    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                        $v = validateUpload($_FILES[$field], ['jpg','jpeg','png','pdf'], 5*1024*1024);
                        if ($v['valid']) {
                            $fpath = saveUpload($_FILES[$field], 'uploads/documents/', strtolower(str_replace(' ','_',$info['type'])).'_');
                            if ($fpath) {
                                $ds = $conn->prepare("INSERT INTO application_documents (application_id, document_name, document_type, file_path) VALUES (?, ?, ?, ?)");
                                $ds->bind_param("isss", $app_id, $info['name'], $info['type'], $fpath);
                                $ds->execute(); $ds->close();
                            }
                        }
                    }
                }

                // Sibling links
                if ($is_sibling && !empty($existing_children)) {
                    foreach ($existing_children as $ec) {
                        $sl = $conn->prepare("INSERT INTO sibling_links (parent_id, student_id_1, application_id) VALUES (?, ?, ?)");
                        $sl->bind_param("iii", $parent_id, $ec['id'], $app_id);
                        $sl->execute(); $sl->close();
                    }
                }

                // Notifications
                $ref = 'APP-' . str_pad($app_id, 5, '0', STR_PAD_LEFT);
                sendNotification($conn, $uid, 'Permohonan Pendaftaran Dihantar',
                    "Permohonan pendaftaran berjaya dihantar. No. Rujukan: $ref", 'success', 'parent_home.php');
                notifyAdmins($conn, 'Permohonan Pendaftaran Baru',
                    "Permohonan pendaftaran baru diterima dari " . htmlspecialchars($parent_name) . " untuk " . htmlspecialchars($child_name), 'info', 'admin_enrollment.php');
                logAction($conn, "Permohonan pendaftaran dihantar: $child_name ($ref)", 'Success');

                $success_msg = "Permohonan pendaftaran berjaya dihantar! No. Rujukan: <strong>$ref</strong>. Pihak pentadbiran akan menyemak permohonan anda.";
            } else {
                $error_msg = 'Ralat semasa menyimpan permohonan: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pendaftaran Anak Baru — SMS</title>
    <meta name="description" content="Borang Pendaftaran Anak Baru — Sistem Pengurusan Sekolah"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:100dvh; font-family:'Inter',sans-serif; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:#1e2124; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px;
                    color:rgba(255,255,255,.55); font-size:12px; letter-spacing:.03em; font-weight:500;
                    transition:background .15s, color .15s; text-decoration:none; cursor:pointer; }
        .nav-link:hover { background:rgba(255,255,255,.08); color:rgba(255,255,255,.9); }
        .nav-link.active { background:rgba(255,255,255,.13); color:#fff; border-left:3px solid #84b6f4; padding-left:11px; }
        .accordion-btn.open { background:rgba(255,255,255,.09); color:#fff; }
        .accordion-btn.open .chevron { transform:rotate(90deg); }
        .sub-link { padding:8px 12px; font-size:11px; border-radius:6px;
                    border-left:2px solid rgba(255,255,255,.08); margin-left:4px; }
        .sub-link:hover { border-left-color:rgba(132,182,244,.5); background:rgba(255,255,255,.06); }
        .wizard-step { display:none; }
        .wizard-step.active { display:block; }
        .drop-zone { border:2px dashed #cbd5e1; border-radius:12px; padding:24px; text-align:center;
                     transition:all .2s; cursor:pointer; background:#f8fafc; }
        .drop-zone:hover, .drop-zone.dragover { border-color:#333093; background:#eef2ff; }
        .drop-zone.has-file { border-color:#10b981; background:#ecfdf5; }
        .class-card { transition:all .2s; cursor:pointer; }
        .class-card:hover { transform:translateY(-2px); box-shadow:0 8px 20px -4px rgba(0,0,0,.1); }
        .class-card.selected { ring:2; border-color:#333093; box-shadow:0 0 0 3px rgba(51,48,147,.2); }
        .toggle-switch { position:relative; width:48px; height:26px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; inset:0; background:#cbd5e1; border-radius:26px; transition:.3s; cursor:pointer; }
        .toggle-slider:before { content:''; position:absolute; height:20px; width:20px; left:3px; bottom:3px;
                                background:white; border-radius:50%; transition:.3s; }
        .toggle-switch input:checked + .toggle-slider { background:#333093; }
        .toggle-switch input:checked + .toggle-slider:before { transform:translateX(22px); }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .fade-in { animation:fadeIn .3s ease-out; }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-[260px] bg-[#1a1c2e] flex flex-col py-5 z-50
           transition-transform duration-300 -translate-x-full md:translate-x-0">
    <div class="px-5 mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#3a78c9] flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[18px]">family_restroom</span>
            </div>
            <span class="text-white font-bold text-[15px]">Panel Ibu Bapa</span>
        </div>
        <button class="md:hidden text-white/50 hover:text-white" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4">
        <a href="parent_home.php" class="nav-link">
            <span class="material-symbols-outlined text-[20px]">dashboard</span><span>Dashboard</span>
        </a>
        <button onclick="toggleAcc('acc-anak')" class="accordion-btn nav-link w-full text-left justify-between">
            <div class="flex items-center gap-2.5"><span class="material-symbols-outlined text-[20px]">child_care</span><span>Anak Saya</span></div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200">chevron_right</span>
        </button>
        <div id="acc-anak" class="hidden pl-3 space-y-0.5">
            <a href="profil_anak.php" class="nav-link sub-link">👧 <span>Profil Anak Saya</span></a>
            <a href="sejarah_kehadiran.php" class="nav-link sub-link">📅 <span>Sejarah Kehadiran</span></a>
            <a href="laporan_harian.php" class="nav-link sub-link">📝 <span>Laporan Aktiviti Harian</span></a>
        </div>
        <button onclick="toggleAcc('acc-akademik')" class="accordion-btn nav-link w-full text-left justify-between">
            <div class="flex items-center gap-2.5"><span class="material-symbols-outlined text-[20px]">school</span><span>Akademik &amp; Perkembangan</span></div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200">chevron_right</span>
        </button>
        <div id="acc-akademik" class="hidden pl-3 space-y-0.5">
            <a href="milestone.php" class="nav-link sub-link">📈 <span>Pencapaian &amp; Perkembangan</span></a>
            <a href="report_card_anak.php" class="nav-link sub-link">🎓 <span>Kad Laporan &amp; Ulasan</span></a>
        </div>
        <button onclick="toggleAcc('acc-komunikasi')" class="accordion-btn nav-link w-full text-left justify-between">
            <div class="flex items-center gap-2.5"><span class="material-symbols-outlined text-[20px]">campaign</span><span>Komunikasi</span></div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200">chevron_right</span>
        </button>
        <div id="acc-komunikasi" class="hidden pl-3 space-y-0.5">
            <a href="parent_inbox.php" class="nav-link sub-link">💬 <span>Peti Masuk</span></a>
            <a href="parent_calendar.php" class="nav-link sub-link">📆 <span>Kalendar Sekolah &amp; RSVP</span></a>
            <a href="parent_announcements.php" class="nav-link sub-link">📢 <span>Pengumuman</span></a>
        </div>
        <button onclick="toggleAcc('acc-kewangan')" class="accordion-btn nav-link w-full text-left justify-between open">
            <div class="flex items-center gap-2.5"><span class="material-symbols-outlined text-[20px]">payments</span><span>Kewangan</span></div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200" style="transform:rotate(90deg)">chevron_right</span>
        </button>
        <div id="acc-kewangan" class="pl-3 space-y-0.5">
            <a href="parent_payments.php" class="nav-link sub-link">💳 <span>Pembayaran &amp; Invois</span></a>
            <a href="parent_payment_history.php" class="nav-link sub-link">🧾 <span>Sejarah Pembayaran</span></a>
            <a href="parent_register_child.php" class="nav-link sub-link active">📋 <span>Pendaftaran Anak Baru</span></a>
            <a href="parent_reenrollment.php" class="nav-link sub-link">🔄 <span>Pendaftaran Semula</span></a>
        </div>
        <button onclick="toggleAcc('acc-keselamatan')" class="accordion-btn nav-link w-full text-left justify-between">
            <div class="flex items-center gap-2.5"><span class="material-symbols-outlined text-[20px]">security</span><span>Keselamatan &amp; Pengangkutan</span></div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200">chevron_right</span>
        </button>
        <div id="acc-keselamatan" class="hidden pl-3 space-y-0.5">
            <a href="parent_bus_tracking.php" class="nav-link sub-link">🚌 <span>Jejak Bas Langsung</span></a>
            <a href="parent_checkin_log.php" class="nav-link sub-link">🔐 <span>Log Daftar Masuk/Keluar</span></a>
            <a href="parent_guardians.php" class="nav-link sub-link">👤 <span>Penjaga Sah</span></a>
        </div>
    </nav>
    <div class="px-3 pt-3 border-t border-white/10">
        <a href="logout.php" class="nav-link text-red-400 hover:text-red-300 hover:bg-red-500/10">
            <span class="material-symbols-outlined text-[20px]">logout</span><span>Log Keluar Sistem</span>
        </a>
    </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

<!-- ═══════════ MAIN ═══════════ -->
<main class="md:ml-[260px] min-h-screen">
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-4 sm:px-6 h-[60px] z-40 shadow-sm">
        <div class="flex items-center gap-3">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[16px] sm:text-[20px] font-semibold text-[#191c1e]">Pendaftaran Anak Baru</h1>
                <p class="text-[11px] text-[#777583] hidden sm:block">Borang pendaftaran pelajar baru</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]"><?php echo htmlspecialchars($parent_name); ?></span>
                <span class="text-[11px] font-bold text-[#3a78c9] bg-[#84b6f4]/20 px-2 py-0.5 rounded-full mt-0.5">Ibu Bapa</span>
            </div>
            <div class="w-9 h-9 rounded-full bg-[#3a78c9] border-2 border-[#84b6f4]/50 flex items-center justify-center text-white text-[15px] font-bold">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <div class="pt-[72px] px-3 sm:px-6 pb-8 max-w-[900px] mx-auto">

        <!-- Success / Error Messages -->
        <?php if ($success_msg): ?>
        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-start gap-3 fade-in">
            <span class="material-symbols-outlined text-emerald-600 text-[22px] mt-0.5">check_circle</span>
            <div>
                <p class="text-[14px] font-semibold text-emerald-800">Berjaya!</p>
                <p class="text-[13px] text-emerald-700 mt-1"><?php echo $success_msg; ?></p>
                <a href="parent_home.php" class="inline-flex items-center gap-1 mt-3 text-[13px] font-semibold text-emerald-700 hover:underline">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span> Kembali ke Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-xl flex items-start gap-3 fade-in">
            <span class="material-symbols-outlined text-red-600 text-[22px] mt-0.5">error</span>
            <div>
                <p class="text-[14px] font-semibold text-red-800">Ralat</p>
                <p class="text-[13px] text-red-700 mt-1"><?php echo htmlspecialchars($error_msg); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$success_msg): ?>

        <!-- ── PROGRESS BAR ── -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6 mb-5">
            <div class="flex items-center justify-between relative">
                <?php
                $steps = [
                    ['icon'=>'person','label'=>'Maklumat Anak'],
                    ['icon'=>'medical_information','label'=>'Kesihatan'],
                    ['icon'=>'school','label'=>'Kelas'],
                    ['icon'=>'upload_file','label'=>'Dokumen'],
                    ['icon'=>'fact_check','label'=>'Semakan']
                ];
                foreach ($steps as $i => $step):
                    $num = $i + 1;
                ?>
                <?php if ($i > 0): ?>
                <div class="flex-1 h-1 bg-gray-200 mx-1 sm:mx-2 rounded-full overflow-hidden" id="connector-<?php echo $i; ?>">
                    <div class="h-full bg-[#333093] rounded-full transition-all duration-500" style="width:0%" id="connector-fill-<?php echo $i; ?>"></div>
                </div>
                <?php endif; ?>
                <div class="flex flex-col items-center z-10" id="step-indicator-<?php echo $num; ?>">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[13px] sm:text-[14px] font-bold transition-all duration-300
                        <?php echo $num === 1 ? 'bg-[#333093] text-white shadow-lg shadow-[#333093]/30' : 'bg-gray-200 text-gray-500'; ?>"
                        id="step-circle-<?php echo $num; ?>">
                        <span class="step-num"><?php echo $num; ?></span>
                        <span class="step-check hidden material-symbols-outlined text-[16px]">check</span>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-medium mt-1.5 text-center hidden sm:block
                        <?php echo $num === 1 ? 'text-[#333093]' : 'text-gray-400'; ?>"
                        id="step-label-<?php echo $num; ?>"><?php echo $step['label']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── FORM ── -->
        <form id="wizardForm" method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="current_step" id="currentStep" value="1">
            <input type="hidden" name="is_sibling" id="isSibling" value="0">

            <!-- ═══ STEP 1: MAKLUMAT ANAK ═══ -->
            <div class="wizard-step active fade-in" id="step-1">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-[#333093]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#333093]">person</span>
                        </div>
                        <div>
                            <h2 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e]">Maklumat Anak</h2>
                            <p class="text-[12px] text-[#777583]">Sila lengkapkan maklumat peribadi anak</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Nama Penuh <span class="text-red-500">*</span></label>
                            <input type="text" name="child_name" id="childName" required placeholder="Seperti dalam MyKid"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093] transition">
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">No. MyKid <span class="text-red-500">*</span></label>
                            <input type="text" name="child_mykid" id="childMykid" required placeholder="Cth: 200101-14-0001"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093] transition">
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Tarikh Lahir <span class="text-red-500">*</span></label>
                            <input type="date" name="child_dob" id="childDob" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093] transition"
                                onchange="calculateAge()">
                            <p id="ageDisplay" class="text-[12px] text-[#333093] font-semibold mt-1 hidden">
                                <span class="material-symbols-outlined text-[14px] align-middle">cake</span>
                                <span id="ageText"></span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Jantina <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mt-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jantina" value="Lelaki" required class="w-4 h-4 text-[#333093] focus:ring-[#333093]">
                                    <span class="text-[14px]">Lelaki</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="jantina" value="Perempuan" class="w-4 h-4 text-[#333093] focus:ring-[#333093]">
                                    <span class="text-[14px]">Perempuan</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Bangsa</label>
                            <select name="bangsa" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                                <option value="">— Pilih —</option>
                                <option>Melayu</option><option>Cina</option><option>India</option><option>Lain-lain</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Agama</label>
                            <select name="agama" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                                <option value="">— Pilih —</option>
                                <option>Islam</option><option>Kristian</option><option>Buddha</option><option>Hindu</option><option>Lain-lain</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Kewarganegaraan</label>
                            <select name="warganegara" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                                <option>Warganegara Malaysia</option><option>Bukan Warganegara</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Gambar Anak</label>
                            <div class="drop-zone" id="photoDropZone" onclick="document.getElementById('childPhoto').click()">
                                <input type="file" name="child_photo" id="childPhoto" accept=".jpg,.jpeg,.png" class="hidden" onchange="previewPhoto(this,'photoPreview','photoDropZone')">
                                <div id="photoPlaceholder">
                                    <span class="material-symbols-outlined text-gray-400 text-[36px]">add_a_photo</span>
                                    <p class="text-[13px] text-gray-500 mt-2">Klik untuk muat naik gambar</p>
                                    <p class="text-[11px] text-gray-400">JPG, PNG (Maks 2MB)</p>
                                </div>
                                <div id="photoPreview" class="hidden">
                                    <img id="photoImg" class="w-24 h-24 rounded-xl object-cover mx-auto border-2 border-[#333093]/20">
                                    <p id="photoName" class="text-[12px] text-gray-600 mt-2"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="button" onclick="nextStep(1)" class="bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            Seterusnya <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══ STEP 2: KESIHATAN ═══ -->
            <div class="wizard-step fade-in" id="step-2">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-rose-500">medical_information</span>
                        </div>
                        <div>
                            <h2 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e]">Maklumat Kesihatan</h2>
                            <p class="text-[12px] text-[#777583]">Rekod kesihatan anak untuk keselamatan</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Golongan Darah</label>
                            <select name="blood_type" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                                <option value="">— Pilih —</option>
                                <option>A</option><option>B</option><option>AB</option><option>O</option><option>Tidak Pasti</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-2">Alahan</label>
                            <div class="flex flex-wrap gap-2">
                                <?php $allergies_list = ['Makanan Laut','Kacang','Susu/Laktosa','Debu','Ubat-ubatan','Tiada Alahan'];
                                foreach ($allergies_list as $al): ?>
                                <label class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-100 transition text-[13px]">
                                    <input type="checkbox" name="allergies[]" value="<?php echo $al; ?>"
                                        class="w-4 h-4 text-[#333093] rounded focus:ring-[#333093]"
                                        onchange="handleAllergyChange()">
                                    <?php echo $al; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="allergyDetailWrap" class="hidden mt-3">
                                <input type="text" name="allergy_details" placeholder="Nyatakan butiran alahan..."
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Keadaan Perubatan</label>
                            <textarea name="medical_condition" rows="2" placeholder="Senaraikan keadaan perubatan jika ada..."
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093] resize-none"></textarea>
                        </div>

                        <div class="sm:col-span-2">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div>
                                    <p class="text-[13px] font-semibold text-gray-700">Keperluan Khas</p>
                                    <p class="text-[11px] text-gray-500">Adakah anak anda mempunyai keperluan khas?</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="specialNeedsToggle" onchange="document.getElementById('specialNeedsWrap').classList.toggle('hidden')">
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            <div id="specialNeedsWrap" class="hidden mt-3">
                                <textarea name="special_needs_detail" rows="2" placeholder="Nyatakan keperluan khas..."
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093] resize-none"></textarea>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Nama Doktor</label>
                            <input type="text" name="doctor_name" placeholder="Dr. ..."
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                        </div>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">No. Telefon Klinik</label>
                            <input type="text" name="doctor_phone" placeholder="03-..."
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">Rekod Vaksinasi <span class="text-red-500">*</span></label>
                            <div class="drop-zone" id="vaccDropZone" onclick="document.getElementById('vaccFile').click()">
                                <input type="file" name="vaccination_record" id="vaccFile" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="previewDoc(this,'vaccInfo','vaccDropZone')">
                                <div id="vaccPlaceholder">
                                    <span class="material-symbols-outlined text-gray-400 text-[36px]">vaccines</span>
                                    <p class="text-[13px] text-gray-500 mt-2">Muat naik rekod vaksinasi</p>
                                    <p class="text-[11px] text-gray-400">PDF, JPG, PNG (Maks 5MB)</p>
                                </div>
                                <div id="vaccInfo" class="hidden flex items-center justify-center gap-3">
                                    <span class="material-symbols-outlined text-emerald-500 text-[24px]">task</span>
                                    <div class="text-left">
                                        <p id="vaccFileName" class="text-[13px] font-medium text-gray-700"></p>
                                        <p id="vaccFileSize" class="text-[11px] text-gray-500"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between mt-6">
                        <button type="button" onclick="prevStep(2)" class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali
                        </button>
                        <button type="button" onclick="nextStep(2)" class="bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            Seterusnya <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══ STEP 3: KELAS ═══ -->
            <div class="wizard-step fade-in" id="step-3">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-500">school</span>
                        </div>
                        <div>
                            <h2 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e]">Pemilihan Kelas</h2>
                            <p class="text-[12px] text-[#777583]">Pilih kelas yang sesuai untuk anak anda</p>
                        </div>
                    </div>

                    <!-- Module suggestion -->
                    <div id="moduleSuggestion" class="hidden mb-4 p-3 bg-[#333093]/5 border border-[#333093]/20 rounded-lg">
                        <p class="text-[13px] text-[#333093] font-medium">
                            <span class="material-symbols-outlined text-[16px] align-middle">lightbulb</span>
                            <span id="moduleSuggestText"></span>
                        </p>
                    </div>

                    <!-- Classes grouped by module -->
                    <?php
                    $modules = ['Taska'=>[],'Tadika'=>[],'KAFA Care'=>[]];
                    foreach ($classes_data as $c) $modules[$c['module']][] = $c;
                    $mod_colors = ['Taska'=>['bg-pink-100','text-pink-700','border-pink-200'],
                                   'Tadika'=>['bg-blue-100','text-blue-700','border-blue-200'],
                                   'KAFA Care'=>['bg-amber-100','text-amber-700','border-amber-200']];
                    foreach ($modules as $mod => $classes):
                        if (empty($classes)) continue;
                        $mc = $mod_colors[$mod];
                    ?>
                    <div class="mb-4" data-module="<?php echo $mod; ?>">
                        <h3 class="text-[14px] font-bold text-gray-800 mb-2 flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?php echo $mc[0].' '.$mc[1]; ?>"><?php echo $mod; ?></span>
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($classes as $cls):
                                $capacity = 30;
                                $pct = min(100, round(($cls['enrolled'] / $capacity) * 100));
                                $isFull = $cls['enrolled'] >= $capacity;
                            ?>
                            <label class="class-card block p-4 bg-white border-2 rounded-xl <?php echo $isFull ? 'border-gray-200 opacity-75' : 'border-gray-200 hover:border-[#333093]/50'; ?>"
                                   onclick="selectClass(<?php echo $cls['id']; ?>, this)">
                                <input type="radio" name="selected_class" value="<?php echo $cls['id']; ?>" class="hidden" data-module="<?php echo $cls['module']; ?>">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p class="text-[14px] font-bold text-gray-800"><?php echo htmlspecialchars($cls['class_name']); ?></p>
                                        <p class="text-[12px] text-gray-500 mt-0.5">
                                            <span class="material-symbols-outlined text-[14px] align-middle">person</span>
                                            <?php echo htmlspecialchars($cls['teacher_name'] ?? 'Belum ditetapkan'); ?>
                                        </p>
                                    </div>
                                    <?php if ($isFull): ?>
                                    <span class="px-2 py-0.5 bg-red-100 text-red-600 text-[10px] font-bold rounded-full">PENUH</span>
                                    <?php else: ?>
                                    <span class="px-2 py-0.5 bg-emerald-100 text-emerald-600 text-[10px] font-bold rounded-full">TERSEDIA</span>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-[11px] text-gray-500 mb-1">
                                        <span><?php echo $cls['enrolled']; ?>/<?php echo $capacity; ?> tempat</span>
                                        <span><?php echo $pct; ?>%</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all <?php echo $isFull ? 'bg-red-400' : 'bg-[#333093]'; ?>" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Transport -->
                    <div class="mt-5 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[13px] font-semibold text-gray-700">🚌 Perlukan Pengangkutan?</p>
                                <p class="text-[11px] text-gray-500">Perkhidmatan bas sekolah</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="need_transport" value="1" id="transportToggle"
                                    onchange="document.getElementById('transportWrap').classList.toggle('hidden')">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div id="transportWrap" class="hidden mt-3">
                            <select name="selected_route" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-[14px] focus:ring-2 focus:ring-[#333093]/30 focus:border-[#333093]">
                                <option value="">— Pilih Laluan —</option>
                                <?php foreach ($routes_data as $rt): ?>
                                <option value="<?php echo $rt['id']; ?>"><?php echo htmlspecialchars($rt['route_name']); ?> — <?php echo htmlspecialchars($rt['driver_name']); ?> (<?php echo htmlspecialchars($rt['vehicle_plate']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-between mt-6">
                        <button type="button" onclick="prevStep(3)" class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali
                        </button>
                        <button type="button" onclick="nextStep(3)" class="bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            Seterusnya <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══ STEP 4: DOKUMEN ═══ -->
            <div class="wizard-step fade-in" id="step-4">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-violet-500">upload_file</span>
                        </div>
                        <div>
                            <h2 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e]">Muat Naik Dokumen</h2>
                            <p class="text-[12px] text-[#777583]">Semua dokumen diperlukan untuk proses pendaftaran</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php
                        $docs = [
                            ['field'=>'doc_mykid','label'=>'Salinan MyKid / Sijil Kelahiran','icon'=>'badge','req'=>true],
                            ['field'=>'doc_ic','label'=>'Salinan IC Ibu/Bapa','icon'=>'credit_card','req'=>true],
                            ['field'=>'doc_photo','label'=>'Gambar Passport','icon'=>'photo_camera','req'=>true],
                            ['field'=>'doc_vacc','label'=>'Rekod Vaksinasi','icon'=>'vaccines','req'=>true],
                            ['field'=>'doc_health','label'=>'Borang Pengisytiharan Kesihatan','icon'=>'description','req'=>true],
                        ];
                        foreach ($docs as $di => $doc):
                        ?>
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 mb-1.5">
                                <?php echo ($di+1).'. '.$doc['label']; ?>
                                <?php if ($doc['req']): ?><span class="text-red-500">*</span><?php endif; ?>
                            </label>
                            <div class="drop-zone" id="dz-<?php echo $doc['field']; ?>"
                                 onclick="document.getElementById('file-<?php echo $doc['field']; ?>').click()"
                                 ondragover="event.preventDefault(); this.classList.add('dragover')"
                                 ondragleave="this.classList.remove('dragover')"
                                 ondrop="event.preventDefault(); this.classList.remove('dragover'); handleDrop(event,'file-<?php echo $doc['field']; ?>','info-<?php echo $doc['field']; ?>','dz-<?php echo $doc['field']; ?>')">
                                <input type="file" name="<?php echo $doc['field']; ?>" id="file-<?php echo $doc['field']; ?>" accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                    onchange="previewDoc(this,'info-<?php echo $doc['field']; ?>','dz-<?php echo $doc['field']; ?>')">
                                <div id="ph-<?php echo $doc['field']; ?>">
                                    <span class="material-symbols-outlined text-gray-400 text-[28px]"><?php echo $doc['icon']; ?></span>
                                    <p class="text-[12px] text-gray-500 mt-1">Seret fail ke sini atau <span class="text-[#333093] font-semibold">klik untuk muat naik</span></p>
                                    <p class="text-[10px] text-gray-400">JPG, PNG, PDF (Maks 5MB)</p>
                                </div>
                                <div id="info-<?php echo $doc['field']; ?>" class="hidden">
                                    <div class="flex items-center justify-center gap-3">
                                        <span class="material-symbols-outlined text-emerald-500 text-[24px]">task</span>
                                        <div class="text-left">
                                            <p class="doc-name text-[13px] font-medium text-gray-700"></p>
                                            <p class="doc-size text-[11px] text-gray-500"></p>
                                        </div>
                                        <button type="button" class="ml-2 text-red-400 hover:text-red-600"
                                            onclick="event.stopPropagation(); removeDoc('file-<?php echo $doc['field']; ?>','info-<?php echo $doc['field']; ?>','ph-<?php echo $doc['field']; ?>','dz-<?php echo $doc['field']; ?>')">
                                            <span class="material-symbols-outlined text-[20px]">close</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-[12px] text-blue-700">
                            <span class="material-symbols-outlined text-[16px] align-middle">info</span>
                            Borang Pengisytiharan Kesihatan boleh dimuat turun <a href="#" class="font-bold underline">di sini</a>. Sila cetak, isi, dan muat naik semula.
                        </div>
                    </div>

                    <div class="flex justify-between mt-6">
                        <button type="button" onclick="prevStep(4)" class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali
                        </button>
                        <button type="button" onclick="nextStep(4)" class="bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            Seterusnya <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══ STEP 5: SEMAKAN ═══ -->
            <div class="wizard-step fade-in" id="step-5">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-emerald-500">fact_check</span>
                        </div>
                        <div>
                            <h2 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e]">Semakan & Hantar</h2>
                            <p class="text-[12px] text-[#777583]">Semak semua maklumat sebelum menghantar</p>
                        </div>
                    </div>

                    <!-- Summary: Child Info -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h3 class="text-[13px] font-bold text-gray-600 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">person</span> Maklumat Anak
                        </h3>
                        <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-[13px]">
                            <div><span class="text-gray-500">Nama:</span> <strong id="rev-name"></strong></div>
                            <div><span class="text-gray-500">MyKid:</span> <strong id="rev-mykid"></strong></div>
                            <div><span class="text-gray-500">Tarikh Lahir:</span> <strong id="rev-dob"></strong></div>
                            <div><span class="text-gray-500">Jantina:</span> <strong id="rev-gender"></strong></div>
                            <div><span class="text-gray-500">Bangsa:</span> <strong id="rev-bangsa"></strong></div>
                            <div><span class="text-gray-500">Agama:</span> <strong id="rev-agama"></strong></div>
                        </div>
                    </div>

                    <!-- Summary: Health -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h3 class="text-[13px] font-bold text-gray-600 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">medical_information</span> Kesihatan
                        </h3>
                        <div class="grid grid-cols-2 gap-y-2 gap-x-4 text-[13px]">
                            <div><span class="text-gray-500">Golongan Darah:</span> <strong id="rev-blood"></strong></div>
                            <div><span class="text-gray-500">Alahan:</span> <strong id="rev-allergies"></strong></div>
                            <div class="col-span-2"><span class="text-gray-500">Vaksinasi:</span> <strong id="rev-vacc"></strong></div>
                        </div>
                    </div>

                    <!-- Summary: Class -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h3 class="text-[13px] font-bold text-gray-600 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">school</span> Kelas Dipilih
                        </h3>
                        <p class="text-[14px] font-semibold" id="rev-class"></p>
                        <p class="text-[12px] text-gray-500 mt-1" id="rev-transport"></p>
                    </div>

                    <!-- Summary: Documents -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <h3 class="text-[13px] font-bold text-gray-600 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">upload_file</span> Dokumen
                        </h3>
                        <div id="rev-docs" class="space-y-1 text-[13px]"></div>
                    </div>

                    <!-- Fee estimate -->
                    <div class="mb-4 p-4 bg-[#333093]/5 rounded-xl border border-[#333093]/20">
                        <h3 class="text-[13px] font-bold text-[#333093] uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">payments</span> Anggaran Yuran
                        </h3>
                        <div id="rev-fees" class="space-y-2 text-[13px]"></div>
                        <div id="rev-sibling-discount" class="hidden mt-2 p-2 bg-emerald-50 rounded-lg text-[12px] text-emerald-700 font-medium">
                            ✨ Diskaun adik-beradik mungkin terpakai
                        </div>
                    </div>

                    <!-- Terms -->
                    <label class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-200 rounded-xl cursor-pointer">
                        <input type="checkbox" name="agree_terms" id="agreeTerms" class="w-5 h-5 mt-0.5 text-[#333093] rounded focus:ring-[#333093]">
                        <span class="text-[13px] text-gray-700">
                            Saya mengesahkan bahawa semua maklumat yang diberikan adalah benar dan lengkap.
                            Saya bersetuju dengan <a href="#" class="text-[#333093] font-semibold underline">terma dan syarat</a> pendaftaran.
                        </span>
                    </label>

                    <div class="flex justify-between mt-6">
                        <button type="button" onclick="prevStep(5)" class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[14px] transition flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali
                        </button>
                        <button type="submit" name="submit_application" id="submitBtn"
                            class="bg-[#333093] hover:bg-[#5452b5] text-white px-8 py-3 rounded-lg font-bold text-[15px] transition flex items-center gap-2 shadow-lg shadow-[#333093]/30 disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            <span class="material-symbols-outlined text-[20px]">send</span> Hantar Permohonan
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </div>
</main>

<!-- ═══ SIBLING MODAL ═══ -->
<div id="siblingModal" class="fixed inset-0 z-[60] flex items-center justify-center p-4 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeSiblingModal(false)"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 z-10 fade-in">
        <div class="text-center mb-4">
            <div class="w-14 h-14 bg-[#333093]/10 rounded-full flex items-center justify-center mx-auto mb-3">
                <span class="material-symbols-outlined text-[#333093] text-[28px]">family_restroom</span>
            </div>
            <h3 class="text-[16px] font-bold text-gray-800">Pengesanan Adik-Beradik</h3>
            <p class="text-[13px] text-gray-500 mt-2">Anda mempunyai anak lain yang berdaftar. Adakah anak baru ini adik-beradik kepada:</p>
        </div>
        <div id="siblingList" class="space-y-2 mb-5 max-h-40 overflow-y-auto"></div>
        <div class="flex gap-3">
            <button onclick="closeSiblingModal(false)" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition text-[14px]">
                Tidak
            </button>
            <button onclick="closeSiblingModal(true)" class="flex-1 px-4 py-2.5 bg-[#333093] text-white rounded-lg font-medium hover:bg-[#5452b5] transition text-[14px]">
                Ya, Adik-Beradik
            </button>
        </div>
    </div>
</div>

<script>
// ── Data from PHP ──
const existingChildren = <?php echo json_encode($existing_children); ?>;
const classesData = <?php echo json_encode($classes_data); ?>;
const feesData = <?php echo json_encode($fees_data); ?>;

let currentStep = 1;
const totalSteps = 5;

// ── Sidebar ──
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.toggle('hidden');
}
window.addEventListener('resize', () => {
    const s = document.getElementById('sidebar'), o = document.getElementById('sidebar-overlay');
    if (window.innerWidth >= 768) { s.classList.remove('-translate-x-full'); o.classList.add('hidden'); }
    else s.classList.add('-translate-x-full');
});
function toggleAcc(id) {
    const panel = document.getElementById(id);
    const btn = panel.previousElementSibling;
    panel.classList.toggle('hidden');
    btn && btn.classList.toggle('open');
}

// ── Age calculation ──
function calculateAge() {
    const dob = new Date(document.getElementById('childDob').value);
    if (isNaN(dob)) return;
    const today = new Date();
    let years = today.getFullYear() - dob.getFullYear();
    let months = today.getMonth() - dob.getMonth();
    if (months < 0) { years--; months += 12; }
    if (today.getDate() < dob.getDate()) { months--; if (months < 0) { years--; months += 12; } }
    document.getElementById('ageText').textContent = `Umur: ${years} tahun ${months} bulan`;
    document.getElementById('ageDisplay').classList.remove('hidden');

    // Module suggestion
    const totalMonths = years * 12 + months;
    let suggested = 'Taska';
    if (totalMonths >= 72) suggested = 'KAFA Care';
    else if (totalMonths >= 48) suggested = 'Tadika';
    document.getElementById('moduleSuggestText').textContent = `Berdasarkan umur anak, modul yang dicadangkan: ${suggested}`;
    document.getElementById('moduleSuggestion').classList.remove('hidden');
    document.getElementById('moduleSuggestion').dataset.suggested = suggested;
}

// ── Step navigation ──
function goToStep(step) {
    document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
    document.getElementById('step-' + step).classList.add('active');

    for (let i = 1; i <= totalSteps; i++) {
        const circle = document.getElementById('step-circle-' + i);
        const label = document.getElementById('step-label-' + i);
        const numEl = circle.querySelector('.step-num');
        const checkEl = circle.querySelector('.step-check');

        circle.className = 'w-8 h-8 sm:w-10 sm:h-10 rounded-full flex items-center justify-center text-[13px] sm:text-[14px] font-bold transition-all duration-300';

        if (i < step) {
            circle.classList.add('bg-emerald-500', 'text-white');
            numEl.classList.add('hidden'); checkEl.classList.remove('hidden');
            if (label) { label.className = 'text-[10px] sm:text-[11px] font-medium mt-1.5 text-center hidden sm:block text-emerald-600'; }
        } else if (i === step) {
            circle.classList.add('bg-[#333093]', 'text-white', 'shadow-lg', 'shadow-[#333093]/30');
            numEl.classList.remove('hidden'); checkEl.classList.add('hidden');
            if (label) { label.className = 'text-[10px] sm:text-[11px] font-medium mt-1.5 text-center hidden sm:block text-[#333093]'; }
        } else {
            circle.classList.add('bg-gray-200', 'text-gray-500');
            numEl.classList.remove('hidden'); checkEl.classList.add('hidden');
            if (label) { label.className = 'text-[10px] sm:text-[11px] font-medium mt-1.5 text-center hidden sm:block text-gray-400'; }
        }

        // Connectors
        if (i > 1) {
            const fill = document.getElementById('connector-fill-' + i);
            if (fill) fill.style.width = (i <= step ? '100%' : '0%');
        }
    }
    currentStep = step;
    document.getElementById('currentStep').value = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep(from) {
    if (!validateStep(from)) return;

    // Sibling check after step 1
    if (from === 1 && existingChildren.length > 0) {
        showSiblingModal();
        return;
    }

    // Populate review on step 5
    if (from === 4) populateReview();

    goToStep(from + 1);
}

function prevStep(from) {
    goToStep(from - 1);
}

// ── Validation ──
function validateStep(step) {
    if (step === 1) {
        const name = document.getElementById('childName').value.trim();
        const mykid = document.getElementById('childMykid').value.trim();
        const dob = document.getElementById('childDob').value;
        const gender = document.querySelector('input[name="jantina"]:checked');
        if (!name) { showToast('Sila masukkan nama penuh anak.'); document.getElementById('childName').focus(); return false; }
        if (!mykid) { showToast('Sila masukkan No. MyKid.'); document.getElementById('childMykid').focus(); return false; }
        if (!dob) { showToast('Sila pilih tarikh lahir.'); return false; }
        if (!gender) { showToast('Sila pilih jantina.'); return false; }
    }
    if (step === 3) {
        const cls = document.querySelector('input[name="selected_class"]:checked');
        if (!cls) { showToast('Sila pilih kelas.'); return false; }
    }
    return true;
}

function showToast(msg) {
    let t = document.getElementById('toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'toast';
        t.className = 'fixed top-4 right-4 z-[70] bg-red-600 text-white px-5 py-3 rounded-xl shadow-2xl text-[13px] font-medium flex items-center gap-2 transition-all duration-300';
        document.body.appendChild(t);
    }
    t.innerHTML = '<span class="material-symbols-outlined text-[18px]">warning</span> ' + msg;
    t.style.opacity = '1'; t.style.transform = 'translateY(0)';
    setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateY(-10px)'; }, 3000);
}

// ── Sibling Modal ──
function showSiblingModal() {
    const list = document.getElementById('siblingList');
    list.innerHTML = '';
    existingChildren.forEach(c => {
        list.innerHTML += `<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <div class="w-8 h-8 rounded-full bg-[#333093] flex items-center justify-center text-white text-[13px] font-bold">${c.full_name.charAt(0).toUpperCase()}</div>
            <span class="text-[14px] font-medium text-gray-800">${c.full_name}</span>
        </div>`;
    });
    document.getElementById('siblingModal').classList.remove('hidden');
}

function closeSiblingModal(isSibling) {
    document.getElementById('isSibling').value = isSibling ? '1' : '0';
    document.getElementById('siblingModal').classList.add('hidden');
    if (currentStep === 1) goToStep(2);
}

// ── Class selection ──
function selectClass(id, el) {
    document.querySelectorAll('.class-card').forEach(c => {
        c.classList.remove('selected');
        c.style.borderColor = '';
        c.style.boxShadow = '';
    });
    el.classList.add('selected');
    el.style.borderColor = '#333093';
    el.style.boxShadow = '0 0 0 3px rgba(51,48,147,.2)';
    el.querySelector('input[type="radio"]').checked = true;
}

// ── File previews ──
function previewPhoto(input, previewId, zoneId) {
    const zone = document.getElementById(zoneId);
    const preview = document.getElementById(previewId);
    const placeholder = document.getElementById('photoPlaceholder');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 2*1024*1024) { showToast('Saiz fail melebihi 2MB'); input.value=''; return; }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photoImg').src = e.target.result;
            document.getElementById('photoName').textContent = file.name;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            zone.classList.add('has-file');
        };
        reader.readAsDataURL(file);
    }
}

function previewDoc(input, infoId, zoneId) {
    const info = document.getElementById(infoId);
    const zone = document.getElementById(zoneId);
    const placeholder = zone.querySelector('[id^="ph-"]') || zone.querySelector('[id$="Placeholder"]');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 5*1024*1024) { showToast('Saiz fail melebihi 5MB'); input.value=''; return; }
        const nameEl = info.querySelector('.doc-name') || info.querySelector('[id$="FileName"]');
        const sizeEl = info.querySelector('.doc-size') || info.querySelector('[id$="FileSize"]');
        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = formatSize(file.size);
        info.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
        zone.classList.add('has-file');
    }
}

function removeDoc(fileId, infoId, phId, zoneId) {
    document.getElementById(fileId).value = '';
    document.getElementById(infoId).classList.add('hidden');
    document.getElementById(phId).classList.remove('hidden');
    document.getElementById(zoneId).classList.remove('has-file');
}

function handleDrop(e, fileId, infoId, zoneId) {
    const dt = e.dataTransfer;
    const input = document.getElementById(fileId);
    input.files = dt.files;
    previewDoc(input, infoId, zoneId);
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

// ── Allergy checkbox logic ──
function handleAllergyChange() {
    const checks = document.querySelectorAll('input[name="allergies[]"]:checked');
    const hasAllergy = Array.from(checks).some(c => c.value !== 'Tiada Alahan');
    const noAllergy = Array.from(checks).some(c => c.value === 'Tiada Alahan');
    document.getElementById('allergyDetailWrap').classList.toggle('hidden', !hasAllergy || noAllergy);
    if (noAllergy) {
        document.querySelectorAll('input[name="allergies[]"]').forEach(c => {
            if (c.value !== 'Tiada Alahan') c.checked = false;
        });
    }
}

// ── Review population ──
function populateReview() {
    const f = document.getElementById('wizardForm');
    document.getElementById('rev-name').textContent = f.child_name.value;
    document.getElementById('rev-mykid').textContent = f.child_mykid.value;
    document.getElementById('rev-dob').textContent = f.child_dob.value;
    const g = f.querySelector('input[name="jantina"]:checked');
    document.getElementById('rev-gender').textContent = g ? g.value : '-';
    document.getElementById('rev-bangsa').textContent = f.bangsa.value || '-';
    document.getElementById('rev-agama').textContent = f.agama.value || '-';
    document.getElementById('rev-blood').textContent = f.blood_type.value || '-';

    // Allergies
    const allChecked = Array.from(f.querySelectorAll('input[name="allergies[]"]:checked')).map(c => c.value);
    document.getElementById('rev-allergies').textContent = allChecked.length > 0 ? allChecked.join(', ') : 'Tiada';

    // Vaccination
    const vaccFile = document.getElementById('vaccFile');
    document.getElementById('rev-vacc').textContent = vaccFile.files.length > 0 ? vaccFile.files[0].name : 'Belum dimuat naik';

    // Class
    const selClass = f.querySelector('input[name="selected_class"]:checked');
    if (selClass) {
        const cid = parseInt(selClass.value);
        const cls = classesData.find(c => c.id == cid);
        document.getElementById('rev-class').textContent = cls ? (cls.class_name + ' (' + cls.module + ')') : '-';
    }

    // Transport
    const transToggle = document.getElementById('transportToggle');
    if (transToggle.checked) {
        const routeSel = f.querySelector('select[name="selected_route"]');
        document.getElementById('rev-transport').textContent = '🚌 Pengangkutan: ' + (routeSel.options[routeSel.selectedIndex]?.text || 'Tidak dipilih');
    } else {
        document.getElementById('rev-transport').textContent = 'Tiada pengangkutan diperlukan';
    }

    // Documents
    const docFields = ['doc_mykid','doc_ic','doc_photo','doc_vacc','doc_health'];
    const docLabels = ['Salinan MyKid','IC Ibu/Bapa','Gambar Passport','Rekod Vaksinasi','Borang Kesihatan'];
    const docsDiv = document.getElementById('rev-docs');
    docsDiv.innerHTML = '';
    docFields.forEach((df, i) => {
        const inp = document.getElementById('file-' + df);
        const hasFile = inp && inp.files && inp.files.length > 0;
        docsDiv.innerHTML += `<div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[16px] ${hasFile ? 'text-emerald-500' : 'text-red-400'}">${hasFile ? 'check_circle' : 'cancel'}</span>
            <span>${docLabels[i]}: ${hasFile ? inp.files[0].name : '<span class=\'text-red-500\'>Belum dimuat naik</span>'}</span>
        </div>`;
    });

    // Fees
    const feesDiv = document.getElementById('rev-fees');
    feesDiv.innerHTML = '';
    if (selClass) {
        const mod = classesData.find(c => c.id == parseInt(selClass.value))?.module;
        if (mod) {
            const modFees = feesData.filter(f => f.module === mod);
            modFees.forEach(fee => {
                const freq = fee.frequency === 'Monthly' ? '/bulan' : (fee.frequency === 'Yearly' ? '/tahun' : '(sekali)');
                feesDiv.innerHTML += `<div class="flex justify-between"><span>${fee.fee_name}</span><strong>RM ${parseFloat(fee.amount).toFixed(2)} ${freq}</strong></div>`;
            });
            if (modFees.length === 0) feesDiv.innerHTML = '<p class="text-gray-500">Tiada maklumat yuran</p>';
        }
    }

    // Sibling discount
    if (document.getElementById('isSibling').value === '1') {
        document.getElementById('rev-sibling-discount').classList.remove('hidden');
    }

    // Enable submit when terms checked
    document.getElementById('agreeTerms').addEventListener('change', function() {
        document.getElementById('submitBtn').disabled = !this.checked;
    });
}

// Submit loading state
document.getElementById('wizardForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span> Menghantar...';
    btn.disabled = true;
});
</script>
</body>
</html>
