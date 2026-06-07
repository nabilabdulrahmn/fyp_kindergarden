<?php
// parent_home.php — Parent-Only Dashboard
// SECURITY: sahkan_peranan('parent') redirects any non-parent back to their own dashboard.
require_once 'auth_guard.php';
sahkan_peranan('parent');
require_once 'db.php';

$username = $_SESSION['username'];
$uid = (int)$_SESSION['user_id'];

// ── Parent & Children Info ────────────────────────────────────
$parent_id   = 0;
$parent_name = $username;
$children    = [];
$pending_fees = 0.0;
$upcoming_events = [];
$recent_attendance = [];

$r = mysqli_query($conn, "SELECT id, full_name FROM parents WHERE user_id=$uid LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) {
    $parent_id   = (int)$row['id'];
    $parent_name = htmlspecialchars($row['full_name'] ?: $username);
}

if ($parent_id) {
    // Children list with today's attendance
    $today = date('Y-m-d');
    $r = mysqli_query($conn, "SELECT s.id, s.full_name, s.module, s.status,
            c.class_name,
            (SELECT a.status FROM attendance a WHERE a.student_id=s.id AND a.date='$today' LIMIT 1) AS today_status
        FROM students s
        LEFT JOIN student_classes sc ON sc.student_id = s.id
        LEFT JOIN classes c ON c.id = sc.class_id
        WHERE s.parent_id = $parent_id AND s.status='Active'");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $children[] = $row;

    // Pending/Overdue fees
    $r = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS t FROM invoices WHERE parent_id=$parent_id AND status IN ('Pending','Overdue')");
    if ($r) $pending_fees = (float)mysqli_fetch_assoc($r)['t'];

    // Upcoming events (next 3)
    $r = mysqli_query($conn, "SELECT title, event_date, location FROM calendar_events
        WHERE event_date >= '$today' ORDER BY event_date ASC LIMIT 3");
    if ($r) while ($row = mysqli_fetch_assoc($r)) $upcoming_events[] = $row;

    // Recent attendance for first child
    if (!empty($children)) {
        $first_child_id = (int)$children[0]['id'];
        $r = mysqli_query($conn, "SELECT date, status FROM attendance
            WHERE student_id=$first_child_id ORDER BY date DESC LIMIT 5");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $recent_attendance[] = $row;
    }
}

$child_count  = count($children);
$present_today = count(array_filter($children, fn($c) => $c['today_status'] === 'Present'));
$absent_today  = count(array_filter($children, fn($c) => $c['today_status'] === 'Absent'));

function time_ago($dt) {
    $diff = (new DateTime())->diff(new DateTime($dt));
    if ($diff->days > 0) return $diff->days . ' hari lepas';
    if ($diff->h > 0)    return $diff->h  . ' jam lepas';
    return 'Baru sahaja';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard Ibu Bapa — SMS</title>
    <meta name="description" content="Panel Ibu Bapa — Sistem Pengurusan Sekolah"/>
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
        <a href="parent_home.php" class="nav-link active">
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
                <h1 class="text-[20px] font-semibold text-[#191c1e]">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</h1>
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
                    <?php echo strtoupper(substr($username,0,1)); ?>
                </div>
                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
            </div>
        </div>
    </header>

    <!-- Alert Banner -->
    <?php if ($pending_fees > 0): ?>
    <div class="fixed top-[68px] right-0 w-full md:w-[calc(100%-260px)] bg-[#ffdad6] text-[#93000a]
                flex items-center justify-center gap-2 py-[7px] px-4 z-30 text-[12px] font-medium">
        <span class="material-symbols-outlined text-[16px]">warning</span>
        <span>Anda mempunyai yuran tertunggak berjumlah <strong>RM <?php echo number_format($pending_fees,2); ?></strong>. Sila jelaskan sebelum tarikh akhir.</span>
        <a href="parent_payments.php" class="font-bold underline ml-2 hover:opacity-80">Bayar Sekarang</a>
    </div>
    <?php else: ?>
    <div class="fixed top-[68px] right-0 w-full md:w-[calc(100%-260px)] bg-[#d5e0f8] text-[#111c2d]
                flex items-center justify-center gap-2 py-[7px] px-4 z-30 text-[12px] font-medium">
        <span class="material-symbols-outlined text-[16px]">check_circle</span>
        <span>Semua yuran anda telah dijelaskan. Terima kasih!</span>
        <a href="parent_payments.php" class="font-bold underline ml-2 hover:opacity-80">Semak Invois</a>
    </div>
    <?php endif; ?>

    <!-- Content -->
    <div class="pt-[110px] px-6 pb-8 max-w-[1440px] mx-auto">

        <!-- Hero Bento -->
        <div class="grid grid-cols-12 gap-5 mb-5">
            <!-- Welcome Card -->
            <div class="col-span-12 lg:col-span-8 bg-white rounded-xl p-7 relative overflow-hidden shadow-sm border border-[#c7c5d4]/20 bento-card">
                <div class="relative z-10 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <div class="max-w-sm">
                        <p class="text-[12px] text-[#3a78c9] font-bold uppercase tracking-wider mb-1">
                            <?php echo $child_count; ?> anak berdaftar
                        </p>
                        <h2 class="text-[28px] font-bold text-[#191c1e] leading-tight mb-2">Ringkasan Keluarga</h2>
                        <p class="text-[14px] text-[#464552] mb-5">
                            <?php if ($child_count > 0): ?>
                                Hari ini: <span class="text-green-600 font-bold"><?php echo $present_today; ?> hadir</span>
                                <?php if ($absent_today > 0): ?>,
                                    <span class="text-red-500 font-bold"><?php echo $absent_today; ?> tidak hadir</span>.
                                <?php else: ?>. Semua anak anda hadir hari ini! ✅
                                <?php endif; ?>
                            <?php else: ?>
                                Sila daftar anak anda untuk mula menggunakan sistem ini.
                            <?php endif; ?>
                            <?php if ($pending_fees > 0): ?>
                                <br>Yuran tertunggak: <span class="text-red-500 font-bold">RM <?php echo number_format($pending_fees,2); ?></span>.
                            <?php endif; ?>
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <a href="profil_anak.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#3a78c9] text-white rounded-lg text-[13px] font-semibold hover:opacity-90 transition">
                                <span class="material-symbols-outlined text-[18px]">child_care</span>Profil Anak
                            </a>
                            <a href="parent_payments.php"
                               class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#84b6f4]/25 text-[#1a3f6f] rounded-lg text-[13px] font-semibold hover:bg-[#84b6f4]/35 transition">
                                <span class="material-symbols-outlined text-[18px]">credit_card</span>Bayar Yuran
                            </a>
                        </div>
                    </div>
                    <div class="hidden sm:flex w-44 h-44 rounded-2xl bg-[#84b6f4]/15 items-center justify-center flex-shrink-0 border-4 border-white shadow-lg transform -rotate-2">
                        <span class="material-symbols-outlined text-[#3a78c9] text-[80px]">family_restroom</span>
                    </div>
                </div>
                <div class="absolute -right-16 -top-16 w-64 h-64 bg-[#84b6f4]/10 rounded-full blur-3xl pointer-events-none"></div>
            </div>

            <!-- Right Cards -->
            <div class="col-span-12 lg:col-span-4 grid grid-cols-2 gap-5">
                <!-- Fee status card -->
                <div class="col-span-2 <?php echo ($pending_fees > 0) ? 'bg-[#ffdad6]' : 'bg-[#d5e0f8]'; ?> rounded-xl p-5 flex flex-col justify-between bento-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest <?php echo ($pending_fees > 0) ? 'text-[#93000a]' : 'text-[#1a3f6f]'; ?> font-bold">Status Yuran</p>
                            <h3 class="text-[15px] font-semibold text-[#191c1e] mt-1">
                                <?php echo ($pending_fees > 0) ? 'Ada Tunggakan' : 'Semua Dijelaskan ✅'; ?>
                            </h3>
                        </div>
                        <span class="material-symbols-outlined text-[36px] <?php echo ($pending_fees > 0) ? 'text-[#ba1a1a]' : 'text-[#3a78c9]'; ?>">
                            <?php echo ($pending_fees > 0) ? 'account_balance_wallet' : 'verified'; ?>
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="text-[28px] font-bold text-[#191c1e]">RM <?php echo number_format($pending_fees,2); ?></span>
                        <p class="text-[11px] <?php echo ($pending_fees > 0) ? 'text-[#93000a]' : 'text-[#1a3f6f]'; ?> mt-1">Jumlah tertunggak</p>
                    </div>
                </div>
                <!-- Quick Actions -->
                <div class="col-span-2 bg-[#1a1c2e] text-white rounded-xl p-5 bento-card">
                    <h3 class="text-[15px] font-semibold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">bolt</span>Tindakan Pantas
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="sejarah_kehadiran.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">calendar_month</span>
                            <span class="text-[11px] font-medium">Kehadiran</span>
                        </a>
                        <a href="parent_inbox.php"
                           class="flex flex-col items-center gap-1.5 p-3 bg-white/10 hover:bg-white/20 rounded-lg transition text-white no-underline">
                            <span class="material-symbols-outlined text-[22px]">inbox</span>
                            <span class="text-[11px] font-medium">Peti Masuk</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-5">
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-[#84b6f4]/25 flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[#3a78c9] text-[22px]">child_care</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Jumlah Anak Berdaftar</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo $child_count; ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-green-600 text-[22px]">event_available</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Hadir Hari Ini</p>
                <h4 class="text-[34px] font-bold text-green-600 leading-none"><?php echo $present_today; ?></h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg <?php echo ($pending_fees > 0) ? 'bg-[#ffdad6]' : 'bg-green-100'; ?> flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined <?php echo ($pending_fees > 0) ? 'text-[#ba1a1a]' : 'text-green-600'; ?> text-[22px]">
                        <?php echo ($pending_fees > 0) ? 'account_balance_wallet' : 'check_circle'; ?>
                    </span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Yuran Tertunggak</p>
                <h4 class="text-[28px] font-bold <?php echo ($pending_fees > 0) ? 'text-[#ba1a1a]' : 'text-green-600'; ?> leading-none">
                    RM <?php echo number_format($pending_fees,0); ?>
                </h4>
            </div>
            <div class="kpi-card bento-card">
                <div class="w-10 h-10 rounded-lg bg-[#d6e3ff] flex items-center justify-center mb-3">
                    <span class="material-symbols-outlined text-[#0f3e78] text-[22px]">event</span>
                </div>
                <p class="text-[12px] text-[#777583] mb-1">Acara Akan Datang</p>
                <h4 class="text-[34px] font-bold text-[#191c1e] leading-none"><?php echo count($upcoming_events); ?></h4>
            </div>
        </div>

        <!-- Bottom Row: Children Cards + Upcoming Events -->
        <div class="grid grid-cols-12 gap-5">
            <!-- Children List -->
            <div class="col-span-12 lg:col-span-7 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <h3 class="text-[16px] font-semibold">Maklumat Anak Saya</h3>
                        <p class="text-[12px] text-[#777583] mt-0.5">Status kehadiran dan kelas hari ini</p>
                    </div>
                    <a href="profil_anak.php" class="text-[#3a78c9] font-semibold text-[13px] hover:underline">Lihat Profil</a>
                </div>
                <?php if (!empty($children)): ?>
                <div class="space-y-3">
                    <?php foreach ($children as $child): ?>
                    <?php
                        $statusClass = 'bg-gray-100 text-gray-500';
                        $statusText  = 'Belum Dikemas Kini';
                        if ($child['today_status'] === 'Present') { $statusClass = 'bg-green-100 text-green-700'; $statusText = 'Hadir ✓'; }
                        elseif ($child['today_status'] === 'Absent') { $statusClass = 'bg-red-100 text-red-600'; $statusText = 'Tidak Hadir'; }
                        elseif ($child['today_status'] === 'MC')    { $statusClass = 'bg-yellow-100 text-yellow-700'; $statusText = 'MC'; }
                    ?>
                    <div class="flex items-center gap-4 p-4 bg-[#f7f9fb] rounded-xl border border-[#e0e3e5]/60">
                        <div class="w-11 h-11 rounded-full bg-[#3a78c9] flex-shrink-0 flex items-center justify-center text-white font-bold text-[16px]">
                            <?php echo strtoupper(substr($child['full_name'],0,1)); ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[14px] font-semibold truncate"><?php echo htmlspecialchars($child['full_name']); ?></p>
                            <p class="text-[11px] text-[#777583]">
                                <?php echo htmlspecialchars($child['module']); ?>
                                <?php if (!empty($child['class_name'])): ?> — <?php echo htmlspecialchars($child['class_name']); ?><?php endif; ?>
                            </p>
                        </div>
                        <span class="text-[11px] px-3 py-1 rounded-full font-semibold flex-shrink-0 <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-10 text-[#777583]">
                    <span class="material-symbols-outlined text-[48px] opacity-30 block mb-2">child_care</span>
                    <p class="text-[13px]">Tiada anak berdaftar lagi.</p>
                    <a href="daftar_anak.php" class="text-[#3a78c9] text-[12px] font-semibold mt-2 inline-block hover:underline">Daftar Anak Sekarang</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Events -->
            <div class="col-span-12 lg:col-span-5 bg-white rounded-xl p-6 shadow-sm bento-card border border-[#c7c5d4]/20 flex flex-col">
                <h3 class="text-[16px] font-semibold mb-5">Acara Akan Datang</h3>
                <div class="flex-1 space-y-4">
                    <?php if (!empty($upcoming_events)): ?>
                        <?php foreach ($upcoming_events as $ev): ?>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-9 h-9 rounded-full bg-[#84b6f4]/25 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#3a78c9] text-[18px]">event</span>
                            </div>
                            <div>
                                <p class="text-[13px] font-semibold"><?php echo htmlspecialchars($ev['title']); ?></p>
                                <p class="text-[11px] text-[#464552]"><?php echo date('d M Y', strtotime($ev['event_date'])); ?>
                                    <?php if (!empty($ev['location'])): ?> — <?php echo htmlspecialchars($ev['location']); ?><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-6 text-[#777583]">
                        <span class="material-symbols-outlined text-[48px] opacity-30 block mb-2">event_busy</span>
                        <p class="text-[13px]">Tiada acara akan datang buat masa ini.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <a href="parent_calendar.php"
                   class="mt-5 block w-full py-2 text-center border-2 border-[#e0e3e5] rounded-lg
                          text-[13px] font-semibold hover:bg-[#f2f4f6] transition">
                    Lihat Kalendar Penuh
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
