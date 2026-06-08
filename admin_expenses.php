<?php
// admin_expenses.php
// Pelaporan Perbelanjaan & Kewangan - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// 1. Tambah Perbelanjaan Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $category = $conn->real_escape_string($_POST['category']);
    $description = $conn->real_escape_string($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expense_date = $conn->real_escape_string($_POST['expense_date']);
    
    $sql = "INSERT INTO expenses (category, description, amount, expense_date, recorded_by) 
            VALUES ('$category', '$description', $amount, '$expense_date', $user_id)";
    if ($conn->query($sql)) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
            <span class='material-symbols-outlined'>check_circle</span>
            <p class='font-semibold'>Rekod perbelanjaan berjaya ditambah!</p>
        </div>";
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: " . $conn->error . "</p>
        </div>";
    }
}

// 2. Kira Profit & Loss Summary (verified fees, operating expenses, paid payroll)
// (a) Total Revenue (Verified Payments)
$rev_query = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) AS total FROM payments WHERE status = 'Verified'");
$total_revenue = $rev_query ? (float)$rev_query->fetch_assoc()['total'] : 0.0;

// (b) Total Operating Expenses
$exp_query = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses");
$total_operating_expenses = $exp_query ? (float)$exp_query->fetch_assoc()['total'] : 0.0;

// (c) Total Paid Salaries (Payroll)
$pay_query = $conn->query("SELECT COALESCE(SUM(net_salary), 0) AS total FROM payroll WHERE payment_status = 'Paid'");
$total_payroll_paid = $pay_query ? (float)$pay_query->fetch_assoc()['total'] : 0.0;

// (d) Net profit
$net_income = $total_revenue - $total_operating_expenses - $total_payroll_paid;

// Ambil Rekod Perbelanjaan untuk Sejarah
$sql_expenses = "SELECT e.*, u.username FROM expenses e LEFT JOIN users u ON e.recorded_by = u.id ORDER BY e.expense_date DESC";
$result = $conn->query($sql_expenses);

// Kira Jumlah Perbelanjaan Sedia Ada
$total_expenses_recorded = 0;

// --- Data untuk Carta Stacked Bar (Chart.js) ---
$categories = [
    'Operasi',
    'Barangan Runcit & Makanan',
    'Alat Tulis & BBM',
    'Penyelenggaraan',
    'Lain-lain'
];

// 1. Data Harian (15 hari terakhir dengan perbelanjaan)
$sql_dates = "SELECT DISTINCT expense_date FROM expenses ORDER BY expense_date DESC LIMIT 15";
$res_dates = $conn->query($sql_dates);
$daily_labels = [];
if ($res_dates) {
    while ($row = $res_dates->fetch_assoc()) {
        $daily_labels[] = $row['expense_date'];
    }
}
$daily_labels = array_reverse($daily_labels);

$daily_display_labels = [];
$daily_datasets = [];
if (!empty($daily_labels)) {
    $dates_placeholder = implode("','", array_map([$conn, 'real_escape_string'], $daily_labels));
    $sql_daily_cat = "SELECT expense_date, category, SUM(amount) as total 
                      FROM expenses 
                      WHERE expense_date IN ('$dates_placeholder') 
                      GROUP BY expense_date, category";
    $res_daily_cat = $conn->query($sql_daily_cat);
    
    $daily_map = [];
    if ($res_daily_cat) {
        while ($row = $res_daily_cat->fetch_assoc()) {
            $daily_map[$row['expense_date']][$row['category']] = (float)$row['total'];
        }
    }
    
    foreach ($daily_labels as $d) {
        $daily_display_labels[] = date('d/m', strtotime($d));
    }
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($daily_labels as $d) {
            $data_array[] = $daily_map[$d][$cat] ?? 0.0;
        }
        $daily_datasets[$cat] = $data_array;
    }
} else {
    $daily_datasets = array_fill_keys($categories, []);
}

// 2. Data Bulanan (12 bulan terakhir)
$sql_months = "SELECT DISTINCT DATE_FORMAT(expense_date, '%Y-%m') as ym FROM expenses ORDER BY ym DESC LIMIT 12";
$res_months = $conn->query($sql_months);
$monthly_labels = [];
if ($res_months) {
    while ($row = $res_months->fetch_assoc()) {
        $monthly_labels[] = $row['ym'];
    }
}
$monthly_labels = array_reverse($monthly_labels);

$monthly_display_labels = [];
$monthly_datasets = [];
if (!empty($monthly_labels)) {
    $ym_placeholders = implode("','", array_map([$conn, 'real_escape_string'], $monthly_labels));
    $sql_monthly_cat = "SELECT DATE_FORMAT(expense_date, '%Y-%m') as ym, category, SUM(amount) as total 
                        FROM expenses 
                        WHERE DATE_FORMAT(expense_date, '%Y-%m') IN ('$ym_placeholders') 
                        GROUP BY ym, category";
    $res_monthly_cat = $conn->query($sql_monthly_cat);
    
    $monthly_map = [];
    if ($res_monthly_cat) {
        while ($row = $res_monthly_cat->fetch_assoc()) {
            $monthly_map[$row['ym']][$row['category']] = (float)$row['total'];
        }
    }
    
    foreach ($monthly_labels as $ym) {
        $monthly_display_labels[] = date('M Y', strtotime($ym . '-01'));
    }
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($monthly_labels as $ym) {
            $data_array[] = $monthly_map[$ym][$cat] ?? 0.0;
        }
        $monthly_datasets[$cat] = $data_array;
    }
} else {
    $monthly_datasets = array_fill_keys($categories, []);
}

// 3. Data Tahunan (5 tahun terakhir)
$sql_years = "SELECT DISTINCT DATE_FORMAT(expense_date, '%Y') as yr FROM expenses ORDER BY yr DESC LIMIT 5";
$res_years = $conn->query($sql_years);
$yearly_labels = [];
if ($res_years) {
    while ($row = $res_years->fetch_assoc()) {
        $yearly_labels[] = $row['yr'];
    }
}
$yearly_labels = array_reverse($yearly_labels);

$yearly_display_labels = [];
$yearly_datasets = [];
if (!empty($yearly_labels)) {
    $yr_placeholders = implode("','", array_map([$conn, 'real_escape_string'], $yearly_labels));
    $sql_yearly_cat = "SELECT DATE_FORMAT(expense_date, '%Y') as yr, category, SUM(amount) as total 
                       FROM expenses 
                       WHERE DATE_FORMAT(expense_date, '%Y') IN ('$yr_placeholders') 
                       GROUP BY yr, category";
    $res_yearly_cat = $conn->query($sql_yearly_cat);
    
    $yearly_map = [];
    if ($res_yearly_cat) {
        while ($row = $res_yearly_cat->fetch_assoc()) {
            $yearly_map[$row['yr']][$row['category']] = (float)$row['total'];
        }
    }
    
    $yearly_display_labels = $yearly_labels;
    
    foreach ($categories as $cat) {
        $data_array = [];
        foreach ($yearly_labels as $yr) {
            $data_array[] = $yearly_map[$yr][$cat] ?? 0.0;
        }
        $yearly_datasets[$cat] = $data_array;
    }
} else {
    $yearly_datasets = array_fill_keys($categories, []);
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Laporan Kewangan &amp; Perbelanjaan — Panel Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:100dvh; font-family:'Inter',sans-serif; }
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
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">
<?php include 'sidebar_admin.php'; ?>
<main class="main-content-shifted" style="padding: 20px;">

<!-- ═══════════ SIDEBAR ═══════════ -->
<aside id="sidebar"
    class="fixed top-0 left-0 h-screen w-[260px] bg-[#1a1c2e] flex flex-col py-5 z-50
           transition-transform duration-300 -translate-x-full md:translate-x-0">

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

    <nav class="flex-1 overflow-y-auto px-3 space-y-0.5 pb-4">
        <a href="admin_home.php" class="nav-link">
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
                class="accordion-btn nav-link w-full text-left justify-between open" aria-expanded="true">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">payments</span>
                <span>Kewangan &amp; Pendaftaran</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200 rotate-90"
                  id="chevron-acc-kewangan">chevron_right</span>
        </button>
        <div id="acc-kewangan" class="pl-3 space-y-0.5">
            <a href="senarai_pelajar.php"   class="nav-link sub-link">🎓 <span>Permohonan Baru &amp; Senarai Menunggu</span></a>
            <a href="admin_doc_verify.php"  class="nav-link sub-link">📄 <span>Pengesahan Dokumen</span></a>
            <a href="admin_enrollment.php"  class="nav-link sub-link">📋 <span>Status Pendaftaran</span></a>
            <a href="admin_fees.php"        class="nav-link sub-link">💰 <span>Pemantauan Yuran</span></a>
            <a href="admin_invoices.php"    class="nav-link sub-link">🧾 <span>Penjanaan Invois</span></a>
            <a href="admin_expenses.php"    class="nav-link sub-link active">📊 <span>Penyata Pendapatan</span></a>
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

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<main class="md:ml-[260px] min-h-screen">
    
    <!-- Top Bar -->
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-6 h-[68px] z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[18px] font-semibold text-[#191c1e]">📊 Penyata Pendapatan &amp; Kewangan</h1>
                <p class="text-[11px] text-[#777583]">Analisis komprehensif aliran tunai, perbelanjaan dan penyata untung rugi taska</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]">System Controller</span>
                <span class="text-[11px] font-bold text-[#ba1a1a] bg-red-50 px-2 py-0.5 rounded-full">Administrator</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#333093] flex items-center justify-center text-white font-bold select-none">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto">
        
        <?php echo $msg; ?>

        <!-- Printable Statement Action Card -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm mb-6 animate-fade-in">
            <div>
                <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#333093]">print</span>
                    Cetak Penyata Pendapatan Rasmi (Income Statement)
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Jana fail laporan A4 bagi lembaran untung rugi taska</p>
            </div>
            <form action="view_income_statement.php" method="GET" target="_blank" class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
                <select name="print_month" class="rounded-lg border-gray-300 text-xs py-1.5 focus:border-[#5452b5] focus:ring-[#5452b5]">
                    <option value="">-- Semua Bulan --</option>
                    <?php for($m=1; $m<=12; ++$m) echo "<option value='$m' ".($m == date('n') ? 'selected' : '').">".date('F', mktime(0,0,0,$m,1))."</option>"; ?>
                </select>
                <input type="number" name="print_year" value="<?php echo date('Y'); ?>" class="rounded-lg border-gray-300 text-xs py-1.5 w-20 focus:border-[#5452b5] focus:ring-[#5452b5]">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[15px]">print</span> Cetak Laporan (A4)
                </button>
            </form>
        </div>

        <!-- Financial P&L Bento Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[12px] text-gray-500 font-bold uppercase tracking-wider">Hasil (Fees Revenue)</span>
                    <span class="material-symbols-outlined text-green-600">account_balance_wallet</span>
                </div>
                <h3 class="text-[22px] font-bold text-green-600">RM <?php echo number_format($total_revenue, 2); ?></h3>
                <p class="text-[10px] text-gray-400 mt-1">Yuran sah yang telah dibayar</p>
            </div>

            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[12px] text-gray-500 font-bold uppercase tracking-wider">Kos Operasi (Expenses)</span>
                    <span class="material-symbols-outlined text-red-500">shopping_cart</span>
                </div>
                <h3 class="text-[22px] font-bold text-red-500">RM <?php echo number_format($total_operating_expenses, 2); ?></h3>
                <p class="text-[10px] text-gray-400 mt-1">Barangan, kebersihan &amp; utiliti</p>
            </div>

            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[12px] text-gray-500 font-bold uppercase tracking-wider">Gaji Staf (Staff Salary)</span>
                    <span class="material-symbols-outlined text-blue-500">badge</span>
                </div>
                <h3 class="text-[22px] font-bold text-blue-500">RM <?php echo number_format($total_payroll_paid, 2); ?></h3>
                <p class="text-[10px] text-gray-400 mt-1">Gaji bersih staf yang telah dibayar</p>
            </div>

            <?php 
                $profitColor = $net_income >= 0 ? 'text-emerald-600' : 'text-rose-600';
                $profitBg = $net_income >= 0 ? 'bg-emerald-50' : 'bg-rose-50';
                $profitBorder = $net_income >= 0 ? 'border-emerald-200' : 'border-rose-200';
            ?>
            <div class="p-5 rounded-xl border shadow-sm hover:shadow-md transition <?php echo "$profitBg $profitBorder"; ?>">
                <div class="flex justify-between items-center mb-3">
                    <span class="text-[12px] font-bold uppercase tracking-wider <?php echo $profitColor; ?>">Untung Bersih (Net Profit)</span>
                    <span class="material-symbols-outlined <?php echo $profitColor; ?>">
                        <?php echo $net_income >= 0 ? 'trending_up' : 'trending_down'; ?>
                    </span>
                </div>
                <h3 class="text-[22px] font-bold <?php echo $profitColor; ?>">RM <?php echo number_format($net_income, 2); ?></h3>
                <p class="text-[10px] text-gray-500 mt-1">Hasil bersih selepas tolak semua kos</p>
            </div>
        </div>

        <!-- Form and Chart Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <!-- Record Expense Form -->
            <div class="bg-white p-6 rounded-xl border border-[#c7c5d4]/20 shadow-sm lg:col-span-4">
                <h3 class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2" style="color:#ba1a1a;">
                    <span class="material-symbols-outlined">add_circle</span>
                    Rekod Perbelanjaan Operasi Baru
                </h3>
                <form method="POST" class="space-y-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Kategori</label>
                        <select name="category" required class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                            <option value="Operasi">Operasi (Air, Elektrik, Sewa)</option>
                            <option value="Barangan Runcit & Makanan">Barangan Runcit &amp; Makanan</option>
                            <option value="Alat Tulis & BBM">Alat Tulis &amp; Bahan Mengajar</option>
                            <option value="Penyelenggaraan">Penyelenggaraan &amp; Pembaikan</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Tarikh Perbelanjaan</label>
                        <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Butiran / Penerangan</label>
                        <input type="text" name="description" required placeholder="Cth: Beli bahan mentah dapur"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500">
                    </div>

                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Jumlah Perbelanjaan (RM)</label>
                        <div class="flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2">
                            <span class="text-xs text-gray-500 font-bold mr-1">RM</span>
                            <input type="number" step="0.01" name="amount" required placeholder="0.00"
                                   class="w-full border-0 bg-transparent p-1.5 text-sm focus:ring-0 text-right font-semibold">
                        </div>
                    </div>

                    <button type="submit" name="add_expense"
                            class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                        Simpan Rekod Perbelanjaan
                    </button>
                </form>
            </div>

            <!-- Expenses Chart analysis -->
            <div class="bg-white p-6 rounded-xl border border-[#c7c5d4]/20 shadow-sm lg:col-span-8 flex flex-col justify-between">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm">📈 Analisis Aliran Perbelanjaan Operasi</h3>
                        <p class="text-[11px] text-gray-400">Pecahan kos operasi mengikut kategori perbelanjaan</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="setChartFilter('daily', event)" class="chart-filter-btn px-3 py-1 bg-red-600 text-white text-[10px] font-bold uppercase rounded-full">Harian</button>
                        <button onclick="setChartFilter('monthly', event)" class="chart-filter-btn px-3 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold uppercase rounded-full">Bulanan</button>
                        <button onclick="setChartFilter('yearly', event)" class="chart-filter-btn px-3 py-1 bg-gray-100 text-gray-600 text-[10px] font-bold uppercase rounded-full">Tahunan</button>
                    </div>
                </div>
                
                <div class="relative h-[250px] w-full">
                    <canvas id="expensesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- History breakdown Table -->
        <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-gray-500">list_alt</span>
                    Log Penyata Perbelanjaan Sekolah
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-100">Tarikh Rekod</th>
                            <th class="p-4 border-b border-gray-100">Kategori</th>
                            <th class="p-4 border-b border-gray-100">Penerangan / Butiran</th>
                            <th class="p-4 border-b border-gray-100">Direkod Oleh</th>
                            <th class="p-4 border-b border-gray-100 text-right">Jumlah Perbelanjaan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[13px]">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $total_expenses_recorded += $row['amount'];
                            ?>
                                <tr class="hover:bg-gray-50/30 transition">
                                    <td class="p-4 text-gray-500">
                                        <?php echo date('d/m/Y', strtotime($row['expense_date'])); ?>
                                    </td>
                                    <td class="p-4 font-bold text-gray-700"><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td class="p-4 text-gray-600"><?php echo htmlspecialchars($row['description']); ?></td>
                                    <td class="p-4 text-xs text-gray-400"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td class="p-4 text-right font-bold text-red-600">RM <?php echo number_format($row['amount'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                            <tr class="bg-red-50/20 font-bold">
                                <td colspan="4" class="p-4 text-right text-gray-700">Jumlah Perbelanjaan Operasi Direkod:</td>
                                <td class="p-4 text-right text-red-600 text-[15px]">RM <?php echo number_format($total_expenses_recorded, 2); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">shopping_bag</span>
                                    Tiada rekod perbelanjaan dijumpai.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

// Chart color configurations
const categoriesInfo = {
    'Operasi': { borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.7)' },
    'Barangan Runcit & Makanan': { borderColor: '#f97316', backgroundColor: 'rgba(249, 115, 22, 0.7)' },
    'Alat Tulis & BBM': { borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.7)' },
    'Penyelenggaraan': { borderColor: '#a855f7', backgroundColor: 'rgba(168, 85, 247, 0.7)' },
    'Lain-lain': { borderColor: '#6b7280', backgroundColor: 'rgba(107, 114, 128, 0.7)' }
};

// Data passed from PHP
const rawData = {
    daily: {
        labels: <?php echo json_encode($daily_display_labels); ?>,
        datasets: <?php echo json_encode($daily_datasets); ?>
    },
    monthly: {
        labels: <?php echo json_encode($monthly_display_labels); ?>,
        datasets: <?php echo json_encode($monthly_datasets); ?>
    },
    yearly: {
        labels: <?php echo json_encode($yearly_display_labels); ?>,
        datasets: <?php echo json_encode($yearly_datasets); ?>
    }
};

let expensesChart = null;

function renderChart(filterType) {
    const ctx = document.getElementById('expensesChart').getContext('2d');
    const filterData = rawData[filterType];
    
    if (expensesChart) {
        expensesChart.destroy();
    }
    
    const chartDatasets = Object.keys(filterData.datasets).map(catName => {
        return {
            label: catName,
            data: filterData.datasets[catName],
            backgroundColor: categoriesInfo[catName].backgroundColor,
            borderColor: categoriesInfo[catName].borderColor,
            borderWidth: 1.5,
            borderRadius: 4,
            barPercentage: 0.5
        };
    });
    
    expensesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: filterData.labels,
            datasets: chartDatasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 10,
                        font: { size: 10, family: 'Inter' }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.raw !== null) {
                                label += 'RM ' + context.raw.toLocaleString('ms-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'RM ' + value;
                        }
                    }
                }
            }
        }
    });
}

function setChartFilter(filterType, event) {
    document.querySelectorAll('.chart-filter-btn').forEach(btn => {
        btn.classList.remove('bg-red-600', 'text-white');
        btn.classList.add('bg-gray-100', 'text-gray-600');
    });
    
    if (event && event.currentTarget) {
        event.currentTarget.classList.remove('bg-gray-100', 'text-gray-600');
        event.currentTarget.classList.add('bg-red-600', 'text-white');
    }
    
    renderChart(filterType);
}

document.addEventListener('DOMContentLoaded', () => {
    renderChart('daily');
});
</script>

</main>
</body>
</html>
