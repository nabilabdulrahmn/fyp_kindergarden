<?php
// parent_reenrollment.php — Pendaftaran Semula Anak
// Re-enrollment page for existing children
require_once 'auth_guard.php';
sahkan_peranan('parent');
require_once 'db.php';

// ── Include helpers with fallbacks ──
if (file_exists('includes/csrf_helper.php')) {
    require_once 'includes/csrf_helper.php';
} else {
    if (!function_exists('csrf_input')) {
        function csrf_input() {
            if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
        }
    }
    if (!function_exists('validate_csrf_token')) {
        function validate_csrf_token($t) {
            return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
        }
    }
}

if (file_exists('includes/log_helper.php')) {
    require_once 'includes/log_helper.php';
} else {
    if (!function_exists('logAction')) {
        function logAction($conn, $action, $status = 'Success') { return true; }
    }
}

if (file_exists('includes/notification_helper.php')) {
    require_once 'includes/notification_helper.php';
} else {
    if (!function_exists('sendNotification')) {
        function sendNotification($conn, $user_id, $title, $msg, $type = 'info', $link = null) { return true; }
    }
    if (!function_exists('notifyAdmins')) {
        function notifyAdmins($conn, $title, $msg, $type = 'info', $link = null) { return 0; }
    }
}

// ── Create reenrollments table if not exists ──
$create_table_sql = "CREATE TABLE IF NOT EXISTS reenrollments (
    id INT(11) NOT NULL AUTO_INCREMENT,
    student_id INT(11) NOT NULL,
    parent_id INT(11) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    requested_class_id INT(11) NULL,
    status ENUM('Pending','Confirmed','Cancelled') DEFAULT 'Pending',
    notes TEXT NULL,
    health_update TEXT NULL,
    transport_update TEXT NULL,
    confirmed_by INT(11) NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_re_student (student_id),
    KEY fk_re_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
mysqli_query($conn, $create_table_sql);

// ── Session data ──
$username = $_SESSION['username'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);
$parent_id = dapatkan_parent_id($conn);

if (!$parent_id) {
    header('Location: parent_home.php');
    exit();
}

// Get parent name
$parent_name = $username;
$r = mysqli_query($conn, "SELECT full_name FROM parents WHERE id=$parent_id LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) {
    $parent_name = htmlspecialchars($row['full_name'] ?: $username);
}

// ── Academic Year ──
$current_month = (int)date('m');
$current_year = (int)date('Y');
if ($current_month >= 10) {
    $next_academic_year = ($current_year + 1) . '/' . ($current_year + 2);
} else {
    $next_academic_year = $current_year . '/' . ($current_year + 1);
}

// ── Messages ──
$success_msg = '';
$error_msg = '';

// ══════════════════════════════════════════════════════════════
// POST HANDLERS
// ══════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error_msg = 'Token keselamatan tidak sah. Sila cuba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Single Re-enrollment ──
        if ($action === 'reenroll_single') {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $requested_class_id = !empty($_POST['requested_class_id']) ? (int)$_POST['requested_class_id'] : null;
            $notes = trim($_POST['notes'] ?? '');
            $health_update = trim($_POST['health_update'] ?? '');
            $transport_update = trim($_POST['transport_update'] ?? '');

            // Validate student belongs to parent
            $check = $conn->prepare("SELECT id, full_name FROM students WHERE id = ? AND parent_id = ? AND status = 'Active'");
            $check->bind_param('ii', $student_id, $parent_id);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows === 0) {
                $error_msg = 'Pelajar tidak sah atau tidak aktif.';
            } else {
                $student_row = $check_result->fetch_assoc();
                $student_name = $student_row['full_name'];

                // Check no existing non-cancelled reenrollment
                $existing = $conn->prepare("SELECT id FROM reenrollments WHERE student_id = ? AND academic_year = ? AND status != 'Cancelled' LIMIT 1");
                $existing->bind_param('is', $student_id, $next_academic_year);
                $existing->execute();
                $existing_result = $existing->get_result();

                if ($existing_result->num_rows > 0) {
                    $error_msg = 'Pendaftaran semula untuk pelajar ini bagi tahun akademik ' . htmlspecialchars($next_academic_year) . ' sudah wujud.';
                } else {
                    $insert = $conn->prepare("INSERT INTO reenrollments (student_id, parent_id, academic_year, requested_class_id, notes, health_update, transport_update) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $insert->bind_param('iisisss', $student_id, $parent_id, $next_academic_year, $requested_class_id, $notes, $health_update, $transport_update);

                    if ($insert->execute()) {
                        $success_msg = 'Pendaftaran semula untuk ' . htmlspecialchars($student_name) . ' berjaya dihantar!';
                        logAction($conn, "Pendaftaran semula dihantar untuk pelajar: $student_name (ID: $student_id), Tahun: $next_academic_year", 'Success');
                        notifyAdmins($conn,
                            'Pendaftaran Semula Baharu',
                            "Ibu bapa telah menghantar pendaftaran semula untuk $student_name bagi tahun akademik $next_academic_year.",
                            'info',
                            'admin_reenrollments.php'
                        );
                    } else {
                        $error_msg = 'Ralat semasa memproses pendaftaran semula. Sila cuba lagi.';
                    }
                    $insert->close();
                }
                $existing->close();
            }
            $check->close();
        }

        // ── Bulk Re-enrollment ──
        elseif ($action === 'reenroll_all') {
            $student_ids = $_POST['bulk_student_ids'] ?? [];
            $bulk_classes = $_POST['bulk_class'] ?? [];
            $bulk_notes = $_POST['bulk_notes'] ?? [];

            if (empty($student_ids)) {
                $error_msg = 'Tiada anak dipilih untuk pendaftaran semula.';
            } else {
                $enrolled_names = [];
                $failed_count = 0;

                foreach ($student_ids as $idx => $sid) {
                    $sid = (int)$sid;
                    $class_id = !empty($bulk_classes[$idx]) ? (int)$bulk_classes[$idx] : null;
                    $note = trim($bulk_notes[$idx] ?? '');

                    // Validate ownership
                    $check = $conn->prepare("SELECT id, full_name FROM students WHERE id = ? AND parent_id = ? AND status = 'Active'");
                    $check->bind_param('ii', $sid, $parent_id);
                    $check->execute();
                    $cr = $check->get_result();

                    if ($cr->num_rows === 0) {
                        $failed_count++;
                        $check->close();
                        continue;
                    }
                    $s_row = $cr->fetch_assoc();
                    $check->close();

                    // Check no existing reenrollment
                    $ex = $conn->prepare("SELECT id FROM reenrollments WHERE student_id = ? AND academic_year = ? AND status != 'Cancelled' LIMIT 1");
                    $ex->bind_param('is', $sid, $next_academic_year);
                    $ex->execute();
                    $er = $ex->get_result();

                    if ($er->num_rows > 0) {
                        $failed_count++;
                        $ex->close();
                        continue;
                    }
                    $ex->close();

                    $ins = $conn->prepare("INSERT INTO reenrollments (student_id, parent_id, academic_year, requested_class_id, notes) VALUES (?, ?, ?, ?, ?)");
                    $ins->bind_param('iisis', $sid, $parent_id, $next_academic_year, $class_id, $note);

                    if ($ins->execute()) {
                        $enrolled_names[] = $s_row['full_name'];
                    } else {
                        $failed_count++;
                    }
                    $ins->close();
                }

                if (!empty($enrolled_names)) {
                    $names_list = implode(', ', $enrolled_names);
                    $success_msg = 'Pendaftaran semula berjaya dihantar untuk: ' . htmlspecialchars($names_list);
                    logAction($conn, "Pendaftaran semula pukal dihantar untuk: $names_list, Tahun: $next_academic_year", 'Success');
                    notifyAdmins($conn,
                        'Pendaftaran Semula Pukal',
                        "Ibu bapa telah menghantar pendaftaran semula pukal untuk: $names_list bagi tahun akademik $next_academic_year.",
                        'info',
                        'admin_reenrollments.php'
                    );
                }
                if ($failed_count > 0) {
                    $error_msg .= ($error_msg ? ' ' : '') . "$failed_count pendaftaran gagal diproses.";
                }
            }
        }

        // ── Cancel Re-enrollment ──
        elseif ($action === 'cancel_reenrollment') {
            $reenroll_id = (int)($_POST['reenroll_id'] ?? 0);

            // Validate ownership
            $check = $conn->prepare("SELECT r.id, s.full_name FROM reenrollments r JOIN students s ON s.id = r.student_id WHERE r.id = ? AND r.parent_id = ? AND r.status = 'Pending'");
            $check->bind_param('ii', $reenroll_id, $parent_id);
            $check->execute();
            $cr = $check->get_result();

            if ($cr->num_rows === 0) {
                $error_msg = 'Pendaftaran semula tidak dijumpai atau tidak boleh dibatalkan.';
            } else {
                $row = $cr->fetch_assoc();
                $upd = $conn->prepare("UPDATE reenrollments SET status = 'Cancelled' WHERE id = ?");
                $upd->bind_param('i', $reenroll_id);

                if ($upd->execute()) {
                    $success_msg = 'Pendaftaran semula untuk ' . htmlspecialchars($row['full_name']) . ' telah dibatalkan.';
                    logAction($conn, "Pendaftaran semula dibatalkan untuk: {$row['full_name']} (Reenroll ID: $reenroll_id)", 'Success');
                } else {
                    $error_msg = 'Ralat semasa membatalkan pendaftaran semula.';
                }
                $upd->close();
            }
            $check->close();
        }
    }
}

// ══════════════════════════════════════════════════════════════
// DATA QUERIES
// ══════════════════════════════════════════════════════════════

// ── Active Students ──
$students = [];
$stmt = $conn->prepare("
    SELECT s.*, c.class_name, c.module AS class_module, c.id AS class_id, t.full_name AS teacher_name
    FROM students s
    LEFT JOIN student_classes sc ON sc.student_id = s.id
    LEFT JOIN classes c ON c.id = sc.class_id
    LEFT JOIN teachers t ON t.id = c.teacher_id
    WHERE s.parent_id = ? AND s.status = 'Active'
    ORDER BY s.full_name
");
$stmt->bind_param('i', $parent_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

// ── Check reenrollment status for each child ──
foreach ($students as &$student) {
    $student['reenrollment'] = null;
    $re_stmt = $conn->prepare("
        SELECT r.*, c.class_name AS requested_class_name
        FROM reenrollments r
        LEFT JOIN classes c ON c.id = r.requested_class_id
        WHERE r.student_id = ? AND r.academic_year = ? AND r.status != 'Cancelled'
        LIMIT 1
    ");
    $re_stmt->bind_param('is', $student['id'], $next_academic_year);
    $re_stmt->execute();
    $re_result = $re_stmt->get_result();
    if ($re_row = $re_result->fetch_assoc()) {
        $student['reenrollment'] = $re_row;
    }
    $re_stmt->close();
}
unset($student);

// ── All Classes (for dropdown, grouped by module) ──
$classes = [];
$cls_result = mysqli_query($conn, "
    SELECT c.id, c.class_name, c.module,
           (SELECT COUNT(*) FROM student_classes sc2 WHERE sc2.class_id = c.id) AS enrolled_count
    FROM classes c
    ORDER BY c.module, c.class_name
");
if ($cls_result) {
    while ($row = mysqli_fetch_assoc($cls_result)) {
        $classes[] = $row;
    }
}

// Group classes by module
$classes_by_module = [];
foreach ($classes as $cls) {
    $classes_by_module[$cls['module']][] = $cls;
}

// ── Active Transportation Routes ──
$routes = [];
$route_result = mysqli_query($conn, "SELECT id, route_name, vehicle_plate, driver_name, driver_phone FROM transportation WHERE status = 'Active' ORDER BY route_name");
if ($route_result) {
    while ($row = mysqli_fetch_assoc($route_result)) {
        $routes[] = $row;
    }
}

// Children without existing reenrollment (for bulk modal)
$children_no_reenroll = array_filter($students, fn($s) => $s['reenrollment'] === null);

// Avatar colors
$avatar_colors = ['#3a78c9', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#16a085'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pendaftaran Semula — SMS</title>
    <meta name="description" content="Pendaftaran Semula Anak — Sistem Pengurusan Sekolah"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:max(884px,100dvh); font-family:'Inter',sans-serif; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:#1e2124; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }
        /* ── Sidebar nav ── */
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
        /* ── Modal ── */
        .modal-overlay { transition:opacity .2s ease; }
        .modal-content { transition:transform .2s ease, opacity .2s ease; }
        .modal-overlay.active { opacity:1; pointer-events:auto; }
        .modal-overlay.active .modal-content { transform:scale(1); opacity:1; }
        /* ── Toggle switch ── */
        .toggle-switch { position:relative; width:44px; height:24px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; cursor:pointer; top:0; left:0; right:0; bottom:0;
                         background:#cbd5e1; border-radius:24px; transition:.2s; }
        .toggle-slider:before { content:""; position:absolute; height:18px; width:18px; left:3px; bottom:3px;
                                background:white; border-radius:50%; transition:.2s; }
        .toggle-switch input:checked + .toggle-slider { background:#333093; }
        .toggle-switch input:checked + .toggle-slider:before { transform:translateX(20px); }
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

        <!-- ── Anak Saya ── -->
        <button onclick="toggleAcc('acc-anak')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">child_care</span>
                <span>Anak Saya</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-anak">chevron_right</span>
        </button>
        <div id="acc-anak" class="hidden pl-3 space-y-0.5">
            <a href="profil_anak.php"      class="nav-link sub-link">👧 <span>Profil Anak Saya</span></a>
            <a href="sejarah_kehadiran.php" class="nav-link sub-link">📅 <span>Sejarah Kehadiran</span></a>
            <a href="laporan_harian.php"   class="nav-link sub-link">📝 <span>Laporan Aktiviti Harian</span></a>
        </div>

        <!-- ── Akademik & Perkembangan ── -->
        <button onclick="toggleAcc('acc-akademik')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">school</span>
                <span>Akademik &amp; Perkembangan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-akademik">chevron_right</span>
        </button>
        <div id="acc-akademik" class="hidden pl-3 space-y-0.5">
            <a href="milestone.php"        class="nav-link sub-link">📈 <span>Pencapaian &amp; Perkembangan</span></a>
            <a href="report_card_anak.php" class="nav-link sub-link">🎓 <span>Kad Laporan &amp; Ulasan</span></a>
        </div>

        <!-- ── Komunikasi ── -->
        <button onclick="toggleAcc('acc-komunikasi')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">campaign</span>
                <span>Komunikasi</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-komunikasi">chevron_right</span>
        </button>
        <div id="acc-komunikasi" class="hidden pl-3 space-y-0.5">
            <a href="parent_inbox.php"          class="nav-link sub-link">💬 <span>Peti Masuk</span></a>
            <a href="parent_calendar.php"       class="nav-link sub-link">📆 <span>Kalendar Sekolah &amp; RSVP</span></a>
            <a href="parent_announcements.php"  class="nav-link sub-link">📢 <span>Pengumuman</span></a>
        </div>

        <!-- ── Kewangan ── -->
        <button onclick="toggleAcc('acc-kewangan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span>Kewangan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-kewangan">chevron_right</span>
        </button>
        <div id="acc-kewangan" class="hidden pl-3 space-y-0.5">
            <a href="parent_payments.php"        class="nav-link sub-link">💳 <span>Pembayaran &amp; Invois</span></a>
            <a href="parent_payment_history.php" class="nav-link sub-link">🧾 <span>Sejarah Pembayaran</span></a>
            <a href="daftar_anak.php"            class="nav-link sub-link">📋 <span>Pendaftaran Adik-Beradik</span></a>
        </div>

        <!-- ── Keselamatan & Pengangkutan ── -->
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">security</span>
                <span>Keselamatan &amp; Pengangkutan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-keselamatan">chevron_right</span>
        </button>
        <div id="acc-keselamatan" class="hidden pl-3 space-y-0.5">
            <a href="parent_bus_tracking.php" class="nav-link sub-link">🚌 <span>Jejak Bas Langsung</span></a>
            <a href="parent_checkin_log.php"  class="nav-link sub-link">🔐 <span>Log Daftar Masuk/Keluar</span></a>
            <a href="parent_guardians.php"    class="nav-link sub-link">👤 <span>Penjaga Sah</span></a>
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

    <!-- Top Bar -->
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-6 h-[68px] z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[20px] font-semibold text-[#191c1e]">Pendaftaran Semula</h1>
                <p class="text-[12px] text-[#777583]">Sistem Pengurusan Sekolah (SMS)</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]"><?php echo $parent_name; ?></span>
                <span class="text-[11px] font-bold text-[#3a78c9] bg-[#84b6f4]/20 px-2 py-0.5 rounded-full mt-0.5">Ibu Bapa / Penjaga</span>
            </div>
            <div class="relative">
                <div class="w-11 h-11 rounded-full bg-[#3a78c9] border-2 border-[#84b6f4]/50 shadow
                            flex items-center justify-center text-white text-[18px] font-bold select-none">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
        </div>
    </header>

    <!-- Content -->
    <div class="pt-[88px] px-4 sm:px-6 pb-8 max-w-[1440px] mx-auto">

        <!-- Success/Error Messages -->
        <?php if ($success_msg): ?>
        <div class="mb-5 flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-[13px]">
            <span class="material-symbols-outlined text-green-600 text-[22px] flex-shrink-0">check_circle</span>
            <span class="font-medium"><?php echo $success_msg; ?></span>
            <button onclick="this.parentElement.remove()" class="ml-auto text-green-400 hover:text-green-600 flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
        <div class="mb-5 flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-[13px]">
            <span class="material-symbols-outlined text-red-500 text-[22px] flex-shrink-0">error</span>
            <span class="font-medium"><?php echo $error_msg; ?></span>
            <button onclick="this.parentElement.remove()" class="ml-auto text-red-400 hover:text-red-600 flex-shrink-0">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Page Header Card -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6 mb-5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-xl bg-[#333093]/10 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[#333093] text-[32px]">how_to_reg</span>
                    </div>
                    <div>
                        <h2 class="text-[22px] sm:text-[26px] font-bold text-[#191c1e]">Pendaftaran Semula Anak</h2>
                        <p class="text-[13px] text-[#777583] mt-0.5">Daftar semula anak anda untuk tahun akademik akan datang</p>
                    </div>
                </div>
                <?php if (count($children_no_reenroll) > 1): ?>
                <button onclick="openBulkModal()"
                        class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2.5 rounded-lg font-medium text-[13px]
                               inline-flex items-center gap-2 transition-colors whitespace-nowrap">
                    <span class="material-symbols-outlined text-[18px]">playlist_add_check</span>
                    DAFTAR SEMULA SEMUA
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Academic Year Banner -->
        <div class="bg-[#333093]/5 border border-[#333093]/15 rounded-xl p-4 mb-6 flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-[#333093]/10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[#333093] text-[22px]">calendar_today</span>
            </div>
            <div>
                <p class="text-[11px] text-[#333093] font-bold uppercase tracking-wider">Tahun Akademik</p>
                <p class="text-[18px] font-bold text-[#191c1e]"><?php echo htmlspecialchars($next_academic_year); ?></p>
            </div>
        </div>

        <?php if (empty($students)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6">
            <div class="text-center py-16">
                <div class="w-20 h-20 rounded-full bg-[#f7f9fb] flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-[#777583] text-[48px] opacity-50">child_care</span>
                </div>
                <h3 class="text-[18px] font-semibold text-[#191c1e] mb-2">Tiada Anak Aktif Berdaftar</h3>
                <p class="text-[13px] text-[#777583] mb-5 max-w-sm mx-auto">
                    Anda perlu mendaftarkan anak terlebih dahulu sebelum boleh membuat pendaftaran semula.
                </p>
                <a href="parent_register_child.php"
                   class="inline-flex items-center gap-2 bg-[#333093] hover:bg-[#5452b5] text-white px-5 py-2.5 rounded-lg font-medium text-[13px] transition-colors">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    Daftar Anak Sekarang
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- Children Cards -->
        <div class="space-y-4">
            <?php foreach ($students as $index => $child):
                $avatar_color = $avatar_colors[$index % count($avatar_colors)];
                $first_letter = strtoupper(mb_substr($child['full_name'], 0, 1));
                $reenroll = $child['reenrollment'];
                $module_badge_class = match($child['class_module'] ?? $child['module'] ?? '') {
                    'Taska' => 'bg-blue-100 text-blue-700',
                    'Tadika' => 'bg-purple-100 text-purple-700',
                    'KAFA Care' => 'bg-amber-100 text-amber-700',
                    default => 'bg-gray-100 text-gray-600'
                };
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-4 sm:p-6 hover:shadow-md transition-shadow">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <!-- Left: Child info -->
                    <div class="flex items-center gap-4 flex-1 min-w-0">
                        <div class="w-14 h-14 rounded-full flex-shrink-0 flex items-center justify-center text-white text-[22px] font-bold shadow-md"
                             style="background-color: <?php echo $avatar_color; ?>;">
                            <?php echo $first_letter; ?>
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-[16px] sm:text-[18px] font-bold text-[#191c1e] truncate">
                                <?php echo htmlspecialchars($child['full_name']); ?>
                            </h3>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                <?php if (!empty($child['class_name'])): ?>
                                <span class="text-[12px] text-[#464552] font-medium"><?php echo htmlspecialchars($child['class_name']); ?></span>
                                <span class="text-[#c7c5d4]">•</span>
                                <?php endif; ?>
                                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider <?php echo $module_badge_class; ?>">
                                    <?php echo htmlspecialchars($child['class_module'] ?? $child['module'] ?? 'N/A'); ?>
                                </span>
                            </div>
                            <?php if (!empty($child['teacher_name'])): ?>
                            <p class="text-[12px] text-[#777583] mt-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">person</span>
                                Guru: <?php echo htmlspecialchars($child['teacher_name']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right: Re-enrollment status -->
                    <div class="flex-shrink-0 w-full sm:w-auto">
                        <?php if ($reenroll): ?>
                            <?php
                                $status_class = match($reenroll['status']) {
                                    'Pending' => 'bg-amber-50 border-amber-200 text-amber-800',
                                    'Confirmed' => 'bg-green-50 border-green-200 text-green-800',
                                    'Cancelled' => 'bg-red-50 border-red-200 text-red-800',
                                    default => 'bg-gray-50 border-gray-200 text-gray-600'
                                };
                                $badge_class = match($reenroll['status']) {
                                    'Pending' => 'bg-amber-100 text-amber-700',
                                    'Confirmed' => 'bg-green-100 text-green-700',
                                    'Cancelled' => 'bg-red-100 text-red-600',
                                    default => 'bg-gray-100 text-gray-600'
                                };
                                $status_icon = match($reenroll['status']) {
                                    'Pending' => 'schedule',
                                    'Confirmed' => 'verified',
                                    'Cancelled' => 'cancel',
                                    default => 'help'
                                };
                                $status_label = match($reenroll['status']) {
                                    'Pending' => 'Menunggu Pengesahan',
                                    'Confirmed' => 'Disahkan',
                                    'Cancelled' => 'Dibatalkan',
                                    default => $reenroll['status']
                                };
                            ?>
                            <div class="border rounded-xl p-4 <?php echo $status_class; ?>">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="material-symbols-outlined text-[18px]"><?php echo $status_icon; ?></span>
                                    <span class="text-[11px] px-2.5 py-0.5 rounded-full font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                        <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <?php if (!empty($reenroll['requested_class_name'])): ?>
                                <p class="text-[12px] mb-1">
                                    <span class="font-medium">Kelas dimohon:</span> <?php echo htmlspecialchars($reenroll['requested_class_name']); ?>
                                </p>
                                <?php endif; ?>
                                <p class="text-[11px] opacity-75">
                                    Dihantar: <?php echo date('d M Y, H:i', strtotime($reenroll['created_at'])); ?>
                                </p>
                                <?php if ($reenroll['status'] === 'Confirmed' && !empty($reenroll['confirmed_at'])): ?>
                                <p class="text-[11px] opacity-75 mt-0.5">
                                    Disahkan: <?php echo date('d M Y, H:i', strtotime($reenroll['confirmed_at'])); ?>
                                </p>
                                <?php endif; ?>

                                <?php if ($reenroll['status'] === 'Pending'): ?>
                                <form method="POST" class="mt-3" onsubmit="return confirm('Adakah anda pasti ingin membatalkan pendaftaran semula ini?')">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="action" value="cancel_reenrollment">
                                    <input type="hidden" name="reenroll_id" value="<?php echo (int)$reenroll['id']; ?>">
                                    <button type="submit"
                                            class="text-[12px] font-medium text-red-600 hover:text-red-800 border border-red-300 hover:bg-red-50
                                                   px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                        Batal Pendaftaran
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <button onclick="openModal(<?php echo (int)$child['id']; ?>, '<?php echo addslashes(htmlspecialchars($child['full_name'])); ?>', '<?php echo addslashes(htmlspecialchars($child['class_module'] ?? $child['module'] ?? '')); ?>', <?php echo (int)($child['class_id'] ?? 0); ?>)"
                                    class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2.5 rounded-lg font-medium text-[13px]
                                           inline-flex items-center gap-2 transition-colors w-full sm:w-auto justify-center">
                                <span class="material-symbols-outlined text-[18px]">app_registration</span>
                                Mula Pendaftaran Semula
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ═══════════ SINGLE RE-ENROLLMENT MODAL ═══════════ -->
<div id="reenrollModal" class="modal-overlay fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4 opacity-0 pointer-events-none">
    <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto transform scale-95 opacity-0">
        <form method="POST" id="reenrollForm">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="reenroll_single">
            <input type="hidden" name="student_id" id="modal_student_id">

            <!-- Header -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-xl z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-[18px] font-bold text-[#191c1e]">Pendaftaran Semula</h3>
                        <p class="text-[13px] text-[#777583] mt-0.5" id="modal_child_name_label">—</p>
                    </div>
                    <button type="button" onclick="closeModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-[#464552]">close</span>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 space-y-5">
                <!-- Academic Year (read-only) -->
                <div>
                    <label class="block text-[12px] font-bold text-[#464552] uppercase tracking-wider mb-1.5">Tahun Akademik</label>
                    <input type="text" value="<?php echo htmlspecialchars($next_academic_year); ?>" readonly
                           class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-[14px] font-semibold text-[#191c1e] cursor-not-allowed">
                </div>

                <!-- Class Selection -->
                <div>
                    <label class="block text-[12px] font-bold text-[#464552] uppercase tracking-wider mb-1.5">Kelas Yang Dimohon</label>
                    <select name="requested_class_id" id="modal_class_select"
                            class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-[14px] text-[#191c1e]
                                   focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors">
                        <option value="">— Pilih kelas —</option>
                        <?php foreach ($classes_by_module as $module => $module_classes): ?>
                        <optgroup label="<?php echo htmlspecialchars($module); ?>">
                            <?php foreach ($module_classes as $cls): ?>
                            <option value="<?php echo (int)$cls['id']; ?>"
                                    data-module="<?php echo htmlspecialchars($cls['module']); ?>">
                                <?php echo htmlspecialchars($cls['class_name']); ?>
                                (<?php echo (int)$cls['enrolled_count']; ?> murid)
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-[#777583] mt-1">Kelas semasa akan dipilih secara automatik jika tersedia.</p>
                </div>

                <!-- Transport Update Toggle -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[12px] font-bold text-[#464552] uppercase tracking-wider">Perubahan Pengangkutan</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle_transport" onchange="toggleTransportSection()">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div id="transport_section" class="hidden space-y-2">
                        <select name="transport_update" id="modal_transport_select"
                                class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-[14px] text-[#191c1e]
                                       focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors">
                            <option value="">— Pilih laluan —</option>
                            <?php foreach ($routes as $route): ?>
                            <option value="<?php echo htmlspecialchars($route['route_name'] . ' (' . $route['vehicle_plate'] . ')'); ?>">
                                <?php echo htmlspecialchars($route['route_name']); ?> — <?php echo htmlspecialchars($route['vehicle_plate']); ?>
                                (Pemandu: <?php echo htmlspecialchars($route['driver_name']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Health Update Toggle -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[12px] font-bold text-[#464552] uppercase tracking-wider">Perubahan Maklumat Kesihatan</label>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle_health" onchange="toggleHealthSection()">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div id="health_section" class="hidden">
                        <textarea name="health_update" id="modal_health_textarea" rows="3"
                                  placeholder="Cth: Alahan baharu, keperluan perubatan khas, dll."
                                  class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-[14px] text-[#191c1e]
                                         focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors resize-none"></textarea>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-[12px] font-bold text-[#464552] uppercase tracking-wider mb-1.5">Catatan Tambahan</label>
                    <textarea name="notes" rows="3"
                              placeholder="Sebarang nota atau permintaan tambahan..."
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-[14px] text-[#191c1e]
                                     focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors resize-none"></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 rounded-b-xl flex items-center justify-end gap-3">
                <button type="button" onclick="closeModal()"
                        class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[13px] transition-colors">
                    Batal
                </button>
                <button type="submit"
                        class="bg-[#333093] hover:bg-[#5452b5] text-white px-5 py-2.5 rounded-lg font-medium text-[13px] transition-colors inline-flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    Hantar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════ BULK RE-ENROLLMENT MODAL ═══════════ -->
<?php if (count($children_no_reenroll) > 1): ?>
<div id="bulkModal" class="modal-overlay fixed inset-0 bg-black/60 z-[60] flex items-center justify-center p-4 opacity-0 pointer-events-none">
    <div class="modal-content bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto transform scale-95 opacity-0">
        <form method="POST" id="bulkForm">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="reenroll_all">

            <!-- Header -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 rounded-t-xl z-10">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-[18px] font-bold text-[#191c1e]">Pendaftaran Semula Pukal</h3>
                        <p class="text-[13px] text-[#777583] mt-0.5">
                            Daftar semula semua anak untuk tahun akademik <?php echo htmlspecialchars($next_academic_year); ?>
                        </p>
                    </div>
                    <button type="button" onclick="closeBulkModal()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-[#464552]">close</span>
                    </button>
                </div>
            </div>

            <!-- Body -->
            <div class="px-6 py-5 space-y-4">
                <?php $bulk_idx = 0; foreach ($children_no_reenroll as $child):
                    $b_avatar_color = $avatar_colors[$bulk_idx % count($avatar_colors)];
                    $b_first_letter = strtoupper(mb_substr($child['full_name'], 0, 1));
                    $child_module = $child['class_module'] ?? $child['module'] ?? '';
                ?>
                <div class="border border-gray-200 rounded-xl p-4 hover:border-[#333093]/30 transition-colors">
                    <input type="hidden" name="bulk_student_ids[<?php echo $bulk_idx; ?>]" value="<?php echo (int)$child['id']; ?>">

                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full flex-shrink-0 flex items-center justify-center text-white text-[14px] font-bold"
                             style="background-color: <?php echo $b_avatar_color; ?>;">
                            <?php echo $b_first_letter; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="text-[14px] font-bold text-[#191c1e] truncate"><?php echo htmlspecialchars($child['full_name']); ?></h4>
                            <p class="text-[11px] text-[#777583]">
                                <?php echo htmlspecialchars($child['class_name'] ?? 'Tiada kelas'); ?>
                                <?php if ($child_module): ?> • <?php echo htmlspecialchars($child_module); ?><?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-[#464552] uppercase tracking-wider mb-1">Kelas Dimohon</label>
                            <select name="bulk_class[<?php echo $bulk_idx; ?>]"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg text-[13px] text-[#191c1e]
                                           focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors">
                                <option value="">— Kekalkan semasa —</option>
                                <?php foreach ($classes_by_module as $module => $module_classes): ?>
                                <optgroup label="<?php echo htmlspecialchars($module); ?>">
                                    <?php foreach ($module_classes as $cls): ?>
                                    <option value="<?php echo (int)$cls['id']; ?>"
                                            <?php if ((int)($child['class_id'] ?? 0) === (int)$cls['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($cls['class_name']); ?>
                                        (<?php echo (int)$cls['enrolled_count']; ?> murid)
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-[#464552] uppercase tracking-wider mb-1">Catatan</label>
                            <input type="text" name="bulk_notes[<?php echo $bulk_idx; ?>]"
                                   placeholder="Nota pilihan..."
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-[13px] text-[#191c1e]
                                          focus:ring-2 focus:ring-[#333093]/20 focus:border-[#333093] transition-colors">
                        </div>
                    </div>
                </div>
                <?php $bulk_idx++; endforeach; ?>
            </div>

            <!-- Footer -->
            <div class="sticky bottom-0 bg-white border-t border-gray-100 px-6 py-4 rounded-b-xl flex items-center justify-between">
                <p class="text-[12px] text-[#777583]">
                    <span class="font-bold text-[#333093]"><?php echo count($children_no_reenroll); ?></span> anak akan didaftar semula
                </p>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeBulkModal()"
                            class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-5 py-2.5 rounded-lg font-medium text-[13px] transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                            class="bg-[#333093] hover:bg-[#5452b5] text-white px-5 py-2.5 rounded-lg font-medium text-[13px] transition-colors inline-flex items-center gap-2">
                        <span class="material-symbols-outlined text-[16px]">done_all</span>
                        Hantar Semua
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ═══════════ SIDEBAR ═══════════
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
    const btn   = panel.previousElementSibling;
    panel.classList.toggle('hidden');
    btn && btn.classList.toggle('open');
}
// Auto-open accordion matching current sub-page
(function() {
    const page = location.pathname.split('/').pop();
    document.querySelectorAll('.sub-link').forEach(a => {
        if (a.getAttribute('href') === page) {
            const panel = a.closest('[id^="acc-"]');
            if (panel) { panel.classList.remove('hidden'); panel.previousElementSibling?.classList.add('open'); }
            a.classList.add('active');
        }
    });
})();

// ═══════════ SINGLE MODAL ═══════════
function openModal(studentId, childName, childModule, currentClassId) {
    document.getElementById('modal_student_id').value = studentId;
    document.getElementById('modal_child_name_label').textContent = childName;

    // Pre-select current class if it exists
    const classSelect = document.getElementById('modal_class_select');
    if (currentClassId > 0) {
        classSelect.value = currentClassId;
    } else {
        classSelect.value = '';
    }

    // Reset toggles
    document.getElementById('toggle_transport').checked = false;
    document.getElementById('transport_section').classList.add('hidden');
    document.getElementById('toggle_health').checked = false;
    document.getElementById('health_section').classList.add('hidden');

    // Show modal
    const modal = document.getElementById('reenrollModal');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('reenrollModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function toggleTransportSection() {
    const section = document.getElementById('transport_section');
    const toggle = document.getElementById('toggle_transport');
    section.classList.toggle('hidden', !toggle.checked);
    if (!toggle.checked) {
        document.getElementById('modal_transport_select').value = '';
    }
}

function toggleHealthSection() {
    const section = document.getElementById('health_section');
    const toggle = document.getElementById('toggle_health');
    section.classList.toggle('hidden', !toggle.checked);
    if (!toggle.checked) {
        document.getElementById('modal_health_textarea').value = '';
    }
}

// ═══════════ BULK MODAL ═══════════
function openBulkModal() {
    const modal = document.getElementById('bulkModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeBulkModal() {
    const modal = document.getElementById('bulkModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close modals on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});
</script>
</body>
</html>
