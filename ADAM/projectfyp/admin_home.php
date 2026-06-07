<?php
// admin_home.php — Admin-Only Dashboard
// SECURITY: sahkan_peranan('admin') redirects any non-admin back to their own dashboard.
require_once 'auth_guard.php';
sahkan_peranan('admin');
require_once 'db.php';

$username = $_SESSION['username'];

// ── Live KPI Queries ──────────────────────────────────────────
$kpi_students = $kpi_staff = 0;
$kpi_attendance = 0.0;
$kpi_fees = 0.0;
$attendance_monthly = [0,0,0,0,0];
$recent_students = [];

// 1. Total active students
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM students WHERE status='Active'");
if ($r) $kpi_students = (int)mysqli_fetch_assoc($r)['c'];

// 2. Today's attendance %
$today = date('Y-m-d');
$r_p = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance WHERE date='$today' AND status='Present'");
$present = ($r_p) ? (int)mysqli_fetch_assoc($r_p)['c'] : 0;
$kpi_attendance = ($kpi_students > 0) ? round(($present / $kpi_students) * 100, 1) : 0;

// 3. Outstanding fees (Pending + Overdue)
$r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM invoices WHERE status IN ('Pending','Overdue')");
if ($r) $kpi_fees = (float)mysqli_fetch_assoc($r)['t'];

// 4. Active staff
$r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM staff WHERE status='Active'");
if ($r) $kpi_staff = (int)mysqli_fetch_assoc($r)['c'];

// 5. Monthly attendance trend (last 5 months)
$year = date('Y');
$cur_m = (int)date('n');
$month_labels = [];
for ($i = 4; $i >= 0; $i--) {
    $m = $cur_m - $i; $y = $year;
    if ($m <= 0) { $m += 12; $y--; }
    $ym = sprintf('%04d-%02d', $y, $m);
    $month_labels[] = date('M', mktime(0,0,0,$m,1,$y));
    $idx = 4 - $i;
    $r_d = mysqli_query($conn, "SELECT COUNT(DISTINCT date) AS c FROM attendance WHERE date LIKE '$ym%'");
    $days = ($r_d) ? max(1,(int)mysqli_fetch_assoc($r_d)['c']) : 1;
    $r_p2 = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance WHERE date LIKE '$ym%' AND status='Present'");
    $pres = ($r_p2) ? (int)mysqli_fetch_assoc($r_p2)['c'] : 0;
    $tot_pos = $kpi_students * $days;
    $attendance_monthly[$idx] = ($tot_pos > 0) ? round(($pres / $tot_pos) * 100) : 0;
}

// 6. Recent 3 registered students
$r = mysqli_query($conn, "SELECT s.full_name, s.module, s.created_at, c.class_name
    FROM students s
    LEFT JOIN student_classes sc ON sc.student_id = s.id
    LEFT JOIN classes c ON c.id = sc.class_id
    ORDER BY s.created_at DESC LIMIT 3");
if ($r) while($row = mysqli_fetch_assoc($r)) $recent_students[] = $row;

// ── Bar chart heights ─────────────────────────────────────────
$bar_max = max(1, max($attendance_monthly));
$bar_heights = array_map(fn($v) => max(5, round(($v / $bar_max) * 90)), $attendance_monthly);

// Peak bar index
$peak_idx = array_search(max($bar_heights), $bar_heights);

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
    <title>Dashboard Admin — SMS</title>
    <meta name="description" content="Panel Pentadbir — Sistem Pengurusan Sekolah"/>
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
        .nav-link.active { background:rgba(255,255,255,.13); color:#fff; border-left:3px solid #e2dfff; padding-left:11px; }
        .accordion-btn.open { background:rgba(255,255,255,.09); color:#fff; }
        .accordion-btn.open .chevron { transform:rotate(90deg); }
        .sub-link { padding:8px 12px; font-size:11px; border-radius:6px;
                    border-left:2px solid rgba(255,255,255,.08); margin-left:4px; }
        .sub-link:hover { border-left-color:rgba(226,223,255,.5); background:rgba(255,255,255,.06); }
        /* ── Cards ── */
        .bento-card { transition:transform .2s ease, box-shadow .2s ease; }
        .bento-card:hover { transform:translateY(-2px); box-shadow:0 10px 20px -4px rgba(0,0,0,.06); }
        .kpi-card { background:#fff; border-radius:12px; padding:20px 22px;
                    box-shadow:0 1px 3px rgba(0,0,0,.06); border:1px solid rgba(199,197,212,.2); }
        /* ── Bar chart ── */
        .bar-col { flex:1; border-radius:6px 6px 0 0; position:relative; cursor:pointer;
                   transition:background .15s; background:rgba(226,223,255,.18); }
        .bar-col:hover, .bar-col.active-bar { background:rgba(226,223,255,.55); }
        .bar-col.active-bar { border:2px solid #6360d1; }
        .bar-tip { position:absolute; top:-26px; left:50%; transform:translateX(-50%);
                   background:#1a1a2e; color:#fff; padding:2px 7px; border-radius:4px;
                   font-size:10px; white-space:nowrap; opacity:0; transition:opacity .15s; pointer-events:none; }
        .bar-col:hover .bar-tip, .bar-col.active-bar .bar-tip { opacity:1; }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-[260px] bg-[#1a1c2e] flex flex-col py-5 z-50
           transition-transform duration-300 -translate-x-full md:translate-x-0">

    <!-- Logo -->
    <div class="px-5 mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-[#5452b5] flex items-center justify-center">
                <span class="material-symbols-outlined text-white text-[18px]">school</span>
            </div>
            <span class="text-white font-bold text-[15px]">Panel Admin</span>
        </div>
        <button class="md:hidden text-white/50 hover:text-white" onclick="toggleSidebar()">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4">

        <a href="admin_home.php" class="nav-link active">
            <span class="material-symbols-outlined text-[20px]">dashboard</span>
            <span>Dashboard</span>
        </a>

        <!-- ── Akademik & Pelajar ── -->
        <button onclick="toggleAcc('acc-akademik')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">school</span>
                <span>Akademik &amp; Pelajar</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-akademik">chevron_right</span>
        </button>
        <div id="acc-akademik" class="hidden pl-3 space-y-0.5">
            <a href="maklumat_pelajar_lengkap.php" class="nav-link sub-link">📁 <span>Direktori Pelajar &amp; Kesihatan</span></a>
            <a href="senarai_kehadiran.php"         class="nav-link sub-link">📊 <span>Rekod Kehadiran Keseluruhan</span></a>
            <a href="arkib_pelajar.php"             class="nav-link sub-link">🗄️ <span>Arkib &amp; Alumni</span></a>
            <a href="jadual_aktiviti.php"           class="nav-link sub-link">🗓️ <span>Jadual Aktiviti Induk</span></a>
            <a href="rancangan_mengajar.php"        class="nav-link sub-link">📚 <span>Semakan Rancangan Mengajar</span></a>
            <a href="laporan_akademik.php"          class="nav-link sub-link">📈 <span>Laporan Prestasi Akademik</span></a>
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
            <a href="admin_announcements.php" class="nav-link sub-link">📢 <span>Pengumuman Sekolah</span></a>
            <a href="admin_calendar.php"      class="nav-link sub-link">📆 <span>Pengurusan Kalendar</span></a>
            <a href="admin_inbox.php"         class="nav-link sub-link">💬 <span>Peti Masuk &amp; Maklum Balas</span></a>
        </div>

        <!-- ── Kewangan & Pendaftaran ── -->
        <button onclick="toggleAcc('acc-kewangan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span>Kewangan &amp; Pendaftaran</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-kewangan">chevron_right</span>
        </button>
        <div id="acc-kewangan" class="hidden pl-3 space-y-0.5">
            <a href="senarai_pelajar.php"   class="nav-link sub-link">🎓 <span>Permohonan Baru &amp; Senarai Menunggu</span></a>
            <a href="admin_doc_verify.php"  class="nav-link sub-link">📄 <span>Pengesahan Dokumen</span></a>
            <a href="admin_enrollment.php"  class="nav-link sub-link">📋 <span>Status Pendaftaran</span></a>
            <a href="admin_fees.php"        class="nav-link sub-link">💰 <span>Pemantauan Yuran</span></a>
            <a href="admin_invoices.php"    class="nav-link sub-link">🧾 <span>Penjanaan Invois</span></a>
            <a href="admin_expenses.php"    class="nav-link sub-link">📊 <span>Laporan Perbelanjaan</span></a>
        </div>

        <!-- ── Operasi & Sumber ── -->
        <button onclick="toggleAcc('acc-operasi')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">settings_accessibility</span>
                <span>Operasi &amp; Sumber</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-operasi">chevron_right</span>
        </button>
        <div id="acc-operasi" class="hidden pl-3 space-y-0.5">
            <a href="admin_staff.php"      class="nav-link sub-link">👥 <span>Direktori Staf</span></a>
            <a href="admin_payroll.php"    class="nav-link sub-link">💼 <span>Penggajian (Payroll)</span></a>
            <a href="admin_transport.php"  class="nav-link sub-link">🚌 <span>Pengangkutan &amp; Laluan</span></a>
            <a href="admin_meal_plans.php" class="nav-link sub-link">🍽️ <span>Perancangan Pemakanan</span></a>
            <a href="admin_inventory.php"  class="nav-link sub-link">📦 <span>Inventori &amp; Sumber</span></a>
            <a href="admin_facility.php"   class="nav-link sub-link">🏗️ <span>Fasiliti &amp; Penyelenggaraan</span></a>
        </div>

        <!-- ── Keselamatan & Sistem ── -->
        <button onclick="toggleAcc('acc-keselamatan')"
                class="accordion-btn nav-link w-full text-left justify-between" aria-expanded="false">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">security</span>
                <span>Keselamatan &amp; Sistem</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200"
                  id="chevron-acc-keselamatan">chevron_right</span>
        </button>
        <div id="acc-keselamatan" class="hidden pl-3 space-y-0.5">
            <a href="admin_checkin_monitor.php" class="nav-link sub-link">🔐 <span>Pantauan Daftar Masuk/Keluar</span></a>
            <a href="admin_visitors.php"        class="nav-link sub-link">🪪 <span>Log Pelawat</span></a>
            <a href="lulus_pendaftaran.php"     class="nav-link sub-link">✅ <span>Kelulusan Pengguna</span></a>
            <a href="manage_users.php"          class="nav-link sub-link">👤 <span>Peranan &amp; Akses Pengguna</span></a>
            <a href="sys_logs.php"              class="nav-link sub-link">🕵️ <span>Log Sistem</span></a>
        </div>
    </nav>

    <!-- Logout -->
    <div class="px-3 pt-3 border-t border-white/10">
        <a href="logout.php" class="nav-link text-red-400 hover:text-red-300 hover:bg-red-500/10">
            <span class="material-symbols-outlined text-[20px]">logout</span>
            <span>Log Keluar Sistem</span>
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
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]">System Controller</span>
                <span class="text-[11px] font-bold text-[#333093] bg-[#e2dfff]/60 px-2 py-0.5 rounded-full mt-0.5">Administrator</span>
            </div>
            <div class="relative">
                <div class="w-11 h-11 rounded-full bg-[#333093] border-2 border-[#e2dfff] shadow
                            flex items-center justify-center text-white text-[18px] font-bold select-none">
                    <?php echo strtoupper(substr($username,0,1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
        </div>
    </header>

    <!-- Alert Banner -->
    <div class="fixed top-[68px] right-0 w-full md:w-[calc(100%-260px)] bg-[#ffdad6] text-[#93000a]
                flex items-center justify-center gap-2 py-[7px] px-4 z-30 text-[12px] font-medium">
        <span class="material-symbols-outlined text-[16px]">warning</span>
        <span>Makluman Terkini: Sila kemaskini rekod kehadiran pelajar bagi sesi petang.</span>
        <a href="senarai_kehadiran.php" class="font-bold underline ml-2 hover:opacity-80">Kemaskini Sekarang</a>
    </div>

    <!-- Content -->
    <div class="pt-[110px] px-6 pb-8 max-w-[1440px] mx-auto">

        <!-- Hero Bento Row -->
        <div class="grid grid-cols-12 gap-5 mb-5">

            <!-- Welcome Card -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-xl p-7 relative overflow-hidden shadow-sm border border-[#c7c5d4]/20 bento-card">
                <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div class="max-w-sm">
                        <h2 class="text-[30px] font-bold text-[#333093] leading-tight mb-3">Ringkasan Sistem</h2>
                        <p class="text-[14px] text-[#464552] leading-relaxed mb-5">
                            Semua sistem berfungsi dengan baik hari ini. Anda mempunyai
                            <span class="text-[#ba1a1a] font-bold">3 makluman</span> yang memerlukan perhatian segera.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a href="laporan_akademik.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#333093] text-white rounded-lg text-[13px] font-semibold hover:bg-[#2a2680] transition">
                                <span class="material-symbols-outlined text-[18px]">analytics</span>Laporan Penuh
                            </a>
                            <a href="senarai_kehadiran.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#d8e3fb] text-[#111c2d] rounded-lg text-[13px] font-semibold hover:bg-[#bcc7de] transition">
                                <span class="material-symbols-outlined text-[18px]">download</span>Muat Turun Data
                            </a>
                        </div>
                    </div>
                    <div class="hidden sm:block flex-shrink-0">
                        <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuDv13EuB0WoNC--UTKTWhD2VmyKcd4g1MJZhrIxqd8q7TzBOIMEP_7S1AnqrF65lBiRe71MeyWNb0TTRT-sIjJ4O2NWUZ0oqk36tFXfjJv5Jqu69HYbHX9h7HpepoK33P6RDJkEV177DCUsGZu661txPPihsCyJ6uZlW9E4vfJe3NsqCGVj5uuyym4_OY7NiWHcWNMRnPWlAgwNU43JIgZrC6vU-JI9sw2lZva15uPQhfutaI77HMqzJ0tHwijRTcPBjo_oiccmldGP"
                             alt="Pelajar sekolah"
                             class="w-48 h-48 object-cover rounded-2xl shadow-lg border-4 border-white transform rotate-2"
                             onerror="this.style.display='none'"/>
                    </div>
                </div>
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-[#e2dfff]/20 rounded-full blur-3xl pointer-events-none"></div>
            </div>

            <!-- Right: Weather + Quick Actions -->
            <div class="col-span-12 lg:col-span-4 grid grid-cols-2 gap-5">
                <!-- Weather -->
                <div class="col-span-2 bg-[#d5e0f8] rounded-xl p-5 flex flex-col justify-between bento-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-[#586377] font-bold">Waktu &amp; Cuaca</p>
                            <h3 class="text-[15px] font-semibold text-[#191c1e] mt-1"><?php echo date('d M Y'); ?></h3>
                        </div>
                        <span class="material-symbols-outlined text-[36px] text-[#333093]">cloud_done</span>
                    </div>
                    <div class="mt-3 flex items-end gap-2">
                        <span class="text-[34px] font-bold text-[#191c1e] leading-none">31°c</span>
                        <span class="text-[12px] text-[#586377] mb-1">Kuala Lumpur, MY</span>
                    </div>
                </div>
                <!-- Quick Actions -->
                <div class="col-span-2 bg-[#0f3e78] text-white rounded-xl p-5 bento-card">
                    <h3 class="text-[15px] font-semibold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">bolt</span>Pantas
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="senarai_pelajar.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">person_add</span>
                            <span class="text-[11px] font-medium">Daftar Pelajar</span>
                        </a>
                        <a href="admin_invoices.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">receipt_long</span>
                            <span class="text-[11px] font-medium">Resit Yuran</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
            <div class="kpi-card bento-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg bg-[#e2dfff]/40 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#333093] text-[22px]">groups</span>
                    </div>
                    <span class="flex items-center gap-1 text-green-600 text-[11px] font-bold">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>+2.4%
                    </span>
                </div>
                <p class="text-[12px] text-[#777583] font-medium mb-1">Jumlah Pelajar</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo number_format($kpi_students); ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg bg-[#d5e0f8]/60 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#545f73] text-[22px]">event_available</span>
                    </div>
                    <span class="text-[11px] text-[#777583] font-bold">Semasa</span>
                </div>
                <p class="text-[12px] text-[#777583] font-medium mb-1">Kehadiran Hari Ini</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $kpi_attendance; ?>%</h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg bg-[#ffdad6] flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#ba1a1a] text-[22px]">account_balance_wallet</span>
                    </div>
                    <span class="text-[11px] text-[#ba1a1a] font-bold">Sila Semak</span>
                </div>
                <p class="text-[12px] text-[#777583] font-medium mb-1">Yuran Tertunggak</p>
                <h4 class="text-[30px] font-bold text-[#191c1e] leading-none">RM <?php echo number_format($kpi_fees,0); ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-10 h-10 rounded-lg bg-[#d6e3ff] flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#0f3e78] text-[22px]">badge</span>
                    </div>
                    <span class="text-[11px] text-[#777583] font-bold">Aktif</span>
                </div>
                <p class="text-[12px] text-[#777583] font-medium mb-1">Jumlah Staf</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $kpi_staff; ?></h4>
            </div>
        </div>

        <!-- Chart + Activity Row -->
        <div class="grid grid-cols-12 gap-5">
            <!-- Bar Chart -->
            <div class="col-span-12 lg:col-span-7 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h3 class="text-[16px] font-semibold">Trend Kehadiran Bulanan</h3>
                        <p class="text-[12px] text-[#777583] mt-0.5">Purata kehadiran mengikut bulan (Sesi <?php echo date('Y'); ?>)</p>
                    </div>
                    <a href="senarai_kehadiran.php" class="text-[#333093] font-semibold text-[13px] hover:underline">Lihat Detail</a>
                </div>
                <div class="h-56 flex items-end gap-3 px-2 pt-8">
                    <?php foreach ($bar_heights as $i => $h): ?>
                    <div class="bar-col <?php echo ($i===$peak_idx)?'active-bar':''; ?>" style="height:<?php echo $h; ?>%">
                        <div class="bar-tip"><?php echo $attendance_monthly[$i]; ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex justify-between mt-3 px-2">
                    <?php foreach ($month_labels as $lbl): ?>
                    <span class="text-[11px] text-[#777583] flex-1 text-center"><?php echo $lbl; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-span-12 lg:col-span-5 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20 flex flex-col">
                <h3 class="text-[16px] font-semibold mb-5">Aktiviti Terkini</h3>
                <div class="flex-1 space-y-5">
                    <?php if (!empty($recent_students)): ?>
                        <?php foreach ($recent_students as $s): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#d5e0f8]/70 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#545f73] text-[18px]">person_add</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold">Pendaftaran Pelajar Baharu</p>
                                <p class="text-[11px] text-[#464552]"><?php echo htmlspecialchars($s['full_name']); ?>
                                    <?php if (!empty($s['class_name'])): ?>— <?php echo htmlspecialchars($s['class_name']); ?><?php endif; ?>
                                </p>
                                <span class="text-[10px] text-[#777583] mt-0.5 block"><?php echo time_ago($s['created_at']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#d5e0f8]/70 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#545f73] text-[18px]">person_add</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold">Pendaftaran Pelajar Baharu</p>
                                <p class="text-[11px] text-[#464552]">Farah Alia binti Kamarudin — Tahun 1 Amanah</p>
                                <span class="text-[10px] text-[#777583] mt-0.5 block">Tadi (10:15 AM)</span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#ffdad6] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#ba1a1a] text-[18px]">warning</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold">Amaran Keselamatan</p>
                                <p class="text-[11px] text-[#464552]">Percubaan log masuk gagal dari IP: 192.168.1.55</p>
                                <span class="text-[10px] text-[#777583] mt-0.5 block">2 jam lepas</span>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#d6e3ff] flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#0f3e78] text-[18px]">mail</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold">Emel Makluman Sukan</p>
                                <p class="text-[11px] text-[#464552]">Notis hari sukan dihantar kepada 840 ibu bapa</p>
                                <span class="text-[10px] text-[#777583] mt-0.5 block">5 jam lepas</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="sys_logs.php"
                   class="mt-5 block w-full py-2 text-center border-2 border-[#e0e3e5] rounded-lg
                          text-[13px] font-semibold hover:bg-[#f2f4f6] transition">
                    Lihat Semua Aktiviti
                </a>
            </div>
        </div>
    </div>
</main>

<script>
function toggleSidebar() {
    const s = document.getElementById('sidebar'), o = document.getElementById('sidebar-overlay');
    s.classList.toggle('-translate-x-full');
    o.classList.toggle('hidden');
}
window.addEventListener('resize', () => {
    const s = document.getElementById('sidebar'), o = document.getElementById('sidebar-overlay');
    if (window.innerWidth >= 768) { s.classList.remove('-translate-x-full'); o.classList.add('hidden'); }
    else { s.classList.add('-translate-x-full'); }
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
