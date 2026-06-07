<?php
// teacher_home.php — Teacher-Only Dashboard
// SECURITY: sahkan_peranan('teacher') redirects any non-teacher back to their own dashboard.
require_once 'auth_guard.php';
sahkan_peranan('teacher');
require_once 'db.php';

$username = $_SESSION['username'];
$uid = (int)$_SESSION['user_id'];

// ── Teacher & Class Info ──────────────────────────────────────
$teacher_id   = 0;
$teacher_name = $username;
$class_id     = 0;
$class_name   = 'Tiada kelas ditetapkan';

$r = mysqli_query($conn, "SELECT t.id, t.full_name, c.id AS cid, c.class_name
    FROM teachers t LEFT JOIN classes c ON c.teacher_id = t.id
    WHERE t.user_id = $uid LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) {
    $teacher_id   = (int)$row['id'];
    $teacher_name = htmlspecialchars($row['full_name'] ?: $username);
    $class_id     = (int)($row['cid'] ?? 0);
    $class_name   = htmlspecialchars($row['class_name'] ?? 'Tiada kelas');
}

// ── KPIs ─────────────────────────────────────────────────────
$kpi_students  = 0;
$kpi_present   = 0;
$kpi_absent    = 0;
$kpi_plans     = 0;
$today = date('Y-m-d');

if ($class_id) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM student_classes WHERE class_id=$class_id");
    if ($r) $kpi_students = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance a
        INNER JOIN student_classes sc ON sc.student_id = a.student_id
        WHERE sc.class_id=$class_id AND a.date='$today' AND a.status='Present'");
    if ($r) $kpi_present = (int)mysqli_fetch_assoc($r)['c'];

    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance a
        INNER JOIN student_classes sc ON sc.student_id = a.student_id
        WHERE sc.class_id=$class_id AND a.date='$today' AND a.status='Absent'");
    if ($r) $kpi_absent = (int)mysqli_fetch_assoc($r)['c'];
}

if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM lesson_plans WHERE teacher_id=$teacher_id");
    if ($r) $kpi_plans = (int)mysqli_fetch_assoc($r)['c'];
}

// ── Recent Daily Reports ──────────────────────────────────────
$recent_reports = [];
if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT report_date, activities, notes FROM daily_reports
        WHERE teacher_id=$teacher_id ORDER BY report_date DESC LIMIT 3");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $recent_reports[] = $row;
}

// ── Upcoming Activities ───────────────────────────────────────
$upcoming = [];
if ($teacher_id) {
    $r = mysqli_query($conn, "SELECT activity_name, activity_date, activity_time, status
        FROM activity_schedules WHERE teacher_id=$teacher_id AND activity_date >= '$today'
        ORDER BY activity_date ASC LIMIT 3");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $upcoming[] = $row;
}

function time_ago($dt) {
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->days > 0) return $diff->days . ' hari lepas';
    if ($diff->h > 0)    return $diff->h  . ' jam lepas';
    if ($diff->i > 0)    return $diff->i  . ' min lepas';
    return 'Baru sahaja';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard Guru — SMS</title>
    <meta name="description" content="Panel Guru — Sistem Pengurusan Sekolah"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:max(884px,100dvh); font-family:'Inter',sans-serif; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:#1e2124; }
        ::-webkit-scrollbar-thumb { background:#444; border-radius:10px; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px;
                    color:rgba(255,255,255,.55); font-size:12px; letter-spacing:.03em; font-weight:500;
                    transition:background .15s, color .15s; text-decoration:none; cursor:pointer; }
        .nav-link:hover { background:rgba(255,255,255,.08); color:rgba(255,255,255,.9); }
        .nav-link.active { background:rgba(255,255,255,.13); color:#fff; border-left:3px solid #ffb347; padding-left:11px; }
        .accordion-btn.open { background:rgba(255,255,255,.09); color:#fff; }
        .accordion-btn.open .chevron { transform:rotate(90deg); }
        .sub-link { padding:8px 12px; font-size:11px; border-radius:6px;
                    border-left:2px solid rgba(255,255,255,.08); margin-left:4px; }
        .sub-link:hover { border-left-color:rgba(255,179,71,.5); background:rgba(255,255,255,.06); }
        /* ── Cards ── */
        .bento-card { transition:transform .2s ease, box-shadow .2s ease; }
        .bento-card:hover { transform:translateY(-2px); box-shadow:0 10px 20px -4px rgba(0,0,0,.06); }
        .kpi-card { background:#fff; border-radius:12px; padding:20px 22px;
                    box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid rgba(199,197,212,.2); }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-[260px] bg-[#1a1c2e] flex flex-col py-5 z-50
           transition-transform duration-300 -translate-x-full md:translate-x-0">

    <div class="px-5 mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#c97a2a] flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[18px]">school</span>
            </div>
            <span class="text-white font-bold text-[15px]">Panel Guru</span>
        </div>
        <button class="md:hidden text-white/50 hover:text-white" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4">
        <a href="teacher_home.php" class="nav-link active">
            <span class="material-symbols-outlined text-[20px]">dashboard</span><span>Dashboard</span>
        </a>

        <a href="profile_saya.php" class="nav-link">
            <span class="material-symbols-outlined text-[20px]">account_circle</span><span>Profil Saya</span>
        </a>

        <!-- ── Pengurusan Kelas ── -->
        <button onclick="toggleAcc('acc-kelas')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">school</span>
                <span>Pengurusan Kelas</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-kelas">chevron_right</span>
        </button>
        <div id="acc-kelas" class="hidden pl-3 space-y-0.5">
            <a href="ambil_kehadiran.php"         class="nav-link sub-link">📋 <span>Kehadiran Harian</span></a>
            <a href="maklumat_pelajar_lengkap.php" class="nav-link sub-link">📂 <span>Profil Pelajar &amp; Kesihatan</span></a>
            <a href="lesson_plan.php"             class="nav-link sub-link">📚 <span>Rancangan Mengajar</span></a>
            <a href="aktiviti_kelas.php"          class="nav-link sub-link">🗓️ <span>Jadual Aktiviti</span></a>
        </div>

        <!-- ── Perkembangan Pelajar ── -->
        <button onclick="toggleAcc('acc-perkembangan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">monitoring</span>
                <span>Perkembangan Pelajar</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-perkembangan">chevron_right</span>
        </button>
        <div id="acc-perkembangan" class="hidden pl-3 space-y-0.5">
            <a href="perkembangan.php" class="nav-link sub-link">📈 <span>Perkembangan Kanak-kanak</span></a>
            <a href="report_card.php"  class="nav-link sub-link">🎓 <span>Prestasi &amp; Kad Laporan</span></a>
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
            <a href="teacher_daily_report.php"  class="nav-link sub-link">📝 <span>Laporan Aktiviti Harian</span></a>
            <a href="teacher_announcements.php" class="nav-link sub-link">📢 <span>Pengumuman Kelas</span></a>
            <a href="teacher_inbox.php"         class="nav-link sub-link">💬 <span>Mesej &amp; Maklum Balas</span></a>
        </div>

        <!-- ── Operasi ── -->
        <button onclick="toggleAcc('acc-operasi')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">engineering</span>
                <span>Operasi</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-operasi">chevron_right</span>
        </button>
        <div id="acc-operasi" class="hidden pl-3 space-y-0.5">
            <a href="teacher_inventory_request.php" class="nav-link sub-link">📦 <span>Permohonan Inventori</span></a>
            <a href="teacher_facility_request.php"  class="nav-link sub-link">🏗️ <span>Aduan Fasiliti</span></a>
            <a href="teacher_meal_plan.php"         class="nav-link sub-link">🍽️ <span>Pelan Pemakanan &amp; Alahan</span></a>
            <a href="teacher_bus_roster.php"        class="nav-link sub-link">🚌 <span>Jadual Bas &amp; Kepulangan</span></a>
        </div>

        <!-- ── Keselamatan ── -->
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">security</span>
                <span>Keselamatan</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-keselamatan">chevron_right</span>
        </button>
        <div id="acc-keselamatan" class="hidden pl-3 space-y-0.5">
            <a href="teacher_checkin.php" class="nav-link sub-link">🔐 <span>Pengesahan Daftar Masuk</span></a>
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
                <h1 class="text-[20px] font-semibold text-[#191c1e]">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
                <p class="text-[12px] text-[#777583]">Sistem Pengurusan Sekolah (SMS)</p>
            </div>
        </div>
        <a href="profile_saya.php" class="flex items-center gap-3 hover:opacity-85 transition group">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e] group-hover:text-[#c97a2a] transition"><?php echo $teacher_name; ?></span>
                <span class="text-[11px] font-bold text-[#c97a2a] bg-[#ffb347]/20 px-2 py-0.5 rounded-full mt-0.5">Guru Kelas</span>
            </div>
            <div class="relative">
                <div class="w-11 h-11 rounded-full bg-[#c97a2a] border-2 border-[#ffb347]/50 shadow
                            flex items-center justify-center text-white text-[18px] font-bold select-none">
                    <?php echo strtoupper(substr($username,0,1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
        </a>
    </header>

    <!-- Alert Banner -->
    <div class="fixed top-[68px] right-0 w-full md:w-[calc(100%-260px)] bg-[#fff3e0] text-[#7c4d00]
                flex items-center justify-center gap-2 py-[7px] px-4 z-30 text-[12px] font-medium">
        <span class="material-symbols-outlined text-[16px]">schedule</span>
        <span>Peringatan: Sila kemaskini kehadiran kelas sebelum jam 10:00 pagi. Semak alahan pelajar sebelum sesi makan.</span>
        <a href="ambil_kehadiran.php" class="font-bold underline ml-2 hover:opacity-80">Ambil Kehadiran</a>
    </div>

    <!-- Content -->
    <div class="pt-[110px] px-6 pb-8 max-w-[1440px] mx-auto">

        <!-- Hero Bento -->
        <div class="grid grid-cols-12 gap-5 mb-5">
            <!-- Welcome Card -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-xl p-7 relative overflow-hidden shadow-sm border border-[#c7c5d4]/20 bento-card">
                <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div class="max-w-sm">
                        <p class="text-[12px] text-[#c97a2a] font-bold uppercase tracking-wider mb-1">Kelas Anda</p>
                        <h2 class="text-[28px] font-bold text-[#191c1e] leading-tight mb-2"><?php echo $class_name; ?></h2>
                        <p class="text-[14px] text-[#464552] mb-5">
                            Anda mempunyai <strong><?php echo $kpi_students; ?> pelajar</strong> dalam kelas ini.
                            Hari ini: <span class="text-green-600 font-bold"><?php echo $kpi_present; ?> hadir</span>,
                            <span class="text-red-500 font-bold"><?php echo $kpi_absent; ?> tidak hadir</span>.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a href="ambil_kehadiran.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#c97a2a] text-white rounded-lg text-[13px] font-semibold hover:opacity-90 transition">
                                <span class="material-symbols-outlined text-[18px]">how_to_reg</span>Ambil Kehadiran
                            </a>
                            <a href="teacher_daily_report.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#ffb347]/20 text-[#7c4d00] rounded-lg text-[13px] font-semibold hover:bg-[#ffb347]/30 transition">
                                <span class="material-symbols-outlined text-[18px]">edit_note</span>Laporan Harian
                            </a>
                        </div>
                    </div>
                    <div class="hidden sm:flex w-44 h-44 rounded-2xl bg-[#ffb347]/15 items-center justify-center flex-shrink-0 border-4 border-white shadow-lg transform rotate-2">
                        <span class="material-symbols-outlined text-[#c97a2a] text-[80px]">class</span>
                    </div>
                </div>
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-[#ffb347]/10 rounded-full blur-3xl pointer-events-none"></div>
            </div>

            <!-- Right Cards -->
            <div class="col-span-12 lg:col-span-4 grid grid-cols-2 gap-5">
                <!-- Date -->
                <div class="col-span-2 bg-[#fff3e0] rounded-xl p-5 flex flex-col justify-between bento-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-[#7c4d00] font-bold">Tarikh Hari Ini</p>
                            <h3 class="text-[15px] font-semibold text-[#191c1e] mt-1"><?php echo date('l, d M Y'); ?></h3>
                        </div>
                        <span class="material-symbols-outlined text-[36px] text-[#c97a2a]">calendar_today</span>
                    </div>
                    <p class="text-[12px] text-[#7c4d00] mt-3 font-medium">
                        Minggu <?php echo date('W'); ?> — Sesi <?php echo date('Y'); ?>
                    </p>
                </div>
                <!-- Quick Actions -->
                <div class="col-span-2 bg-[#1a1c2e] text-white rounded-xl p-5 bento-card">
                    <h3 class="text-[15px] font-semibold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">bolt</span>Tindakan Pantas
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="lesson_plan.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">menu_book</span>
                            <span class="text-[11px] font-medium">Rancangan Mengajar</span>
                        </a>
                        <a href="perkembangan.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">monitoring</span>
                            <span class="text-[11px] font-medium">Perkembangan</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-[#ffb347]/20 flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[#c97a2a] text-[22px]">groups</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Pelajar Dalam Kelas</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $kpi_students; ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-green-600 text-[22px]">how_to_reg</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Hadir Hari Ini</p>
                <h4 class="text-[34px] font-bold text-green-600 leading-none"><?php echo $kpi_present; ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-red-500 text-[22px]">person_off</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Tidak Hadir</p>
                <h4 class="text-[34px] font-bold text-red-500 leading-none"><?php echo $kpi_absent; ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-[#d6e3ff] flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[#0f3e78] text-[22px]">menu_book</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Rancangan Mengajar</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $kpi_plans; ?></h4>
            </div>
        </div>

        <!-- Bottom Row: Upcoming Activities + Recent Reports -->
        <div class="grid grid-cols-12 gap-5">
            <!-- Upcoming Activities -->
            <div class="col-span-12 lg:col-span-7 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h3 class="text-[16px] font-semibold">Aktiviti Akan Datang</h3>
                        <p class="text-[12px] text-[#777583] mt-0.5">Jadual aktiviti kelas anda</p>
                    </div>
                    <a href="aktiviti_kelas.php" class="text-[#c97a2a] font-semibold text-[13px] hover:underline">Lihat Semua</a>
                </div>
                <?php if (!empty($upcoming)): ?>
                <div class="space-y-3">
                    <?php foreach ($upcoming as $act): ?>
                    <div class="flex items-center gap-4 p-3 bg-[#f7f9fb] rounded-lg">
                        <div class="w-10 h-10 rounded-lg bg-[#ffb347]/20 flex-shrink-0 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#c97a2a] text-[20px]">event</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold truncate"><?php echo htmlspecialchars($act['activity_name']); ?></p>
                            <p class="text-[11px] text-[#777583]"><?php echo date('d M Y', strtotime($act['activity_date'])); ?> — <?php echo htmlspecialchars($act['activity_time']); ?></p>
                        </div>
                        <span class="text-[10px] px-2 py-1 rounded-full font-medium flex-shrink-0
                            <?php echo ($act['status']==='Completed') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                            <?php echo $act['status']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-10 text-[#777583]">
                    <span class="material-symbols-outlined text-[48px] opacity-30 block mb-2">event_busy</span>
                    <p class="text-[13px]">Tiada aktiviti dijadualkan.</p>
                    <a href="aktiviti_kelas.php" class="text-[#c97a2a] text-[12px] font-semibold mt-2 inline-block hover:underline">Tambah Aktiviti</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Reports -->
            <div class="col-span-12 lg:col-span-5 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20 flex flex-col">
                <h3 class="text-[16px] font-semibold mb-5">Laporan Harian Terkini</h3>
                <div class="flex-1 space-y-4">
                    <?php if (!empty($recent_reports)): ?>
                        <?php foreach ($recent_reports as $rep): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#fff3e0] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#c97a2a] text-[18px]">description</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold"><?php echo date('d M Y', strtotime($rep['report_date'])); ?></p>
                                <p class="text-[11px] text-[#464552] line-clamp-2"><?php echo htmlspecialchars(substr($rep['activities'],0,80)); ?>...</p>
                                <span class="text-[10px] text-[#777583] mt-0.5 block"><?php echo time_ago($rep['report_date']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <!-- Default items if no DB records -->
                    <div class="flex gap-3">
                        <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#fff3e0] flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#c97a2a] text-[18px]">info</span>
                        </div>
                        <div>
                            <p class="text-[13px] font-semibold">Tiada laporan lagi</p>
                            <p class="text-[11px] text-[#464552]">Mula buat laporan harian anda untuk kelas ini.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="teacher_daily_report.php"
                   class="mt-5 block w-full py-2 text-center border-2 border-[#e0e3e5] rounded-lg
                          text-[13px] font-semibold hover:bg-[#f2f4f6] transition">
                    Lihat Semua Laporan
                </a>
            </div>
        </div>
    </div>
</main>

<script>
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
</script>
</body>
</html>
