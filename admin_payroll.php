<?php
// admin_payroll.php
// Sumber Manusia & Penggajian - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// 1. Proses Pembayaran Gaji Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payroll'])) {
    $staff_id = (int)$_POST['staff_id'];
    $month_input = (int)$_POST['month'];
    $year_input = (int)$_POST['year'];
    $month_str = sprintf("%04d-%02d", $year_input, $month_input);
    
    $basic_salary = (float)$_POST['basic_salary'];
    
    // Ambil itemized allowances dari POST
    $allow_names = $_POST['allow_name'] ?? [];
    $allow_amounts = $_POST['allow_amount'] ?? [];
    $allowances = [];
    $total_allowance = 0;
    
    for ($i = 0; $i < count($allow_names); $i++) {
        $n = trim($allow_names[$i]);
        $a = (float)($allow_amounts[$i] ?? 0);
        if (!empty($n) && $a > 0) {
            $allowances[] = ['name' => $n, 'amount' => $a];
            $total_allowance += $a;
        }
    }
    
    // Ambil itemized deductions dari POST
    $deduct_names = $_POST['deduct_name'] ?? [];
    $deduct_amounts = $_POST['deduct_amount'] ?? [];
    $deductions = [];
    $total_deductions = 0;
    
    for ($i = 0; $i < count($deduct_names); $i++) {
        $n = trim($deduct_names[$i]);
        $a = (float)($deduct_amounts[$i] ?? 0);
        if (!empty($n) && $a > 0) {
            $deductions[] = ['name' => $n, 'amount' => $a];
            $total_deductions += $a;
        }
    }
    
    $net_salary = $basic_salary + $total_allowance - $total_deductions;
    
    $allowance_details = $conn->real_escape_string(json_encode($allowances));
    $deduction_details = $conn->real_escape_string(json_encode($deductions));
    
    // Check if payroll already exists for this month and staff
    $check_payroll = $conn->query("SELECT id FROM payroll WHERE staff_id = $staff_id AND month = '$month_str' LIMIT 1");
    if ($check_payroll && $check_payroll->num_rows > 0) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
            <span class='material-symbols-outlined'>warning</span>
            <p class='font-semibold'>Ralat: Rekod slip gaji staf ini bagi bulan $month_str sudah pun diproses sebelum ini.</p>
        </div>";
    } else {
        $sql = "INSERT INTO payroll (staff_id, month, basic_salary, allowances, deductions, net_salary, allowance_details, deduction_details, payment_status) 
                VALUES ($staff_id, '$month_str', $basic_salary, $total_allowance, $total_deductions, $net_salary, '$allowance_details', '$deduction_details', 'Pending')";
        
        if ($conn->query($sql)) {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                <span class='material-symbols-outlined'>check_circle</span>
                <div>
                    <p class='font-semibold'>Slip gaji berjaya diproses dan direkodkan!</p>
                    <p class='text-xs text-green-700 mt-0.5'>Gaji Bersih: <strong>RM " . number_format($net_salary, 2) . "</strong>. Anda boleh mencetak slip gaji dari senarai sejarah.</p>
                </div>
            </div>";
        } else {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
                <span class='material-symbols-outlined'>error</span>
                <p class='font-semibold'>Ralat: " . $conn->error . "</p>
            </div>";
        }
    }
}

// 2. Tandakan Gaji Sebagai Dibayar (Mark Paid)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_paid'])) {
    $payroll_id = (int)$_POST['payroll_id'];
    $sql_update = "UPDATE payroll SET payment_status = 'Paid', paid_at = NOW() WHERE id = $payroll_id";
    if ($conn->query($sql_update)) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
            <span class='material-symbols-outlined'>done_all</span>
            <p class='font-semibold'>Status penggajian berjaya dikemaskini kepada 'Telah Dibayar'.</p>
        </div>";
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: " . $conn->error . "</p>
        </div>";
    }
}

// Ambil staf aktif untuk dropdown
$active_staff = $conn->query("SELECT id, full_name, position FROM staff WHERE status = 'Active' ORDER BY full_name");

// Ambil sejarah gaji
$sql_payroll = "SELECT p.*, s.full_name, s.position 
                FROM payroll p
                JOIN staff s ON p.staff_id = s.id
                ORDER BY p.month DESC, s.full_name ASC";
$result = $conn->query($sql_payroll);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>HR &amp; Penggajian (Payroll) — Panel Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
            <a href="admin_expenses.php"    class="nav-link sub-link">📊 <span>Penyata Pendapatan</span></a>
        </div>

        <!-- ── Operasi & Sumber ── -->
        <button onclick="toggleAcc('acc-operasi')"
                class="accordion-btn nav-link w-full text-left justify-between open" aria-expanded="true">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-[20px]">settings_accessibility</span>
                <span>Operasi &amp; Sumber</span>
            </div>
            <span class="material-symbols-outlined text-[17px] chevron transition-transform duration-200 rotate-90"
                  id="chevron-acc-operasi">chevron_right</span>
        </button>
        <div id="acc-operasi" class="pl-3 space-y-0.5">
            <a href="admin_staff.php"      class="nav-link sub-link">👥 <span>Direktori Staf</span></a>
            <a href="admin_payroll.php"    class="nav-link sub-link active">💼 <span>Penggajian (Payroll)</span></a>
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
                <h1 class="text-[18px] font-semibold text-[#191c1e]">💼 Penggajian &amp; Sumber Manusia (Payroll)</h1>
                <p class="text-[11px] text-[#777583]">Urus slip gaji kakitangan, allowances, deductions dan penggajian bulanan</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]">System Controller</span>
                <span class="text-[11px] font-bold text-[#009688] bg-teal-50 px-2 py-0.5 rounded-full">Administrator</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#333093] flex items-center justify-center text-white font-bold select-none">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto">
        
        <?php echo $msg; ?>

        <!-- Quick actions -->
        <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm mb-6 flex justify-between items-center">
            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-teal-600">badge</span>
                Pengurusan Gaji Staf
            </h3>
            <button onclick="openPayrollModal()"
                    class="px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px]">add</span> Proses Gaji Staf
            </button>
        </div>

        <!-- Payroll History -->
        <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-gray-500">history</span>
                    Sejarah Penyata Gaji Sesi Semasa
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-100">Bulan Gaji</th>
                            <th class="p-4 border-b border-gray-100">Nama Kakitangan</th>
                            <th class="p-4 border-b border-gray-100">Jawatan</th>
                            <th class="p-4 border-b border-gray-100">Gaji Pokok</th>
                            <th class="p-4 border-b border-gray-100">Elaun (+) / Potongan (-)</th>
                            <th class="p-4 border-b border-gray-100">Gaji Bersih</th>
                            <th class="p-4 border-b border-gray-100">Status</th>
                            <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[13px]">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/30 transition">
                                    <td class="p-4 font-bold text-gray-800">
                                        <?php echo date('F Y', strtotime($row['month']."-01")); ?>
                                    </td>
                                    <td class="p-4 font-semibold text-gray-700"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                    <td class="p-4 text-gray-500"><?php echo htmlspecialchars($row['position']); ?></td>
                                    <td class="p-4 font-medium text-gray-800">RM <?php echo number_format($row['basic_salary'], 2); ?></td>
                                    <td class="p-4 text-xs">
                                        <span class="text-green-600 font-semibold">+ RM <?php echo number_format($row['allowances'], 2); ?></span><br>
                                        <span class="text-red-500 font-semibold">- RM <?php echo number_format($row['deductions'], 2); ?></span>
                                    </td>
                                    <td class="p-4 font-bold text-teal-700">RM <?php echo number_format($row['net_salary'], 2); ?></td>
                                    <td class="p-4">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $row['payment_status'] === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                                            <?php echo $row['payment_status'] === 'Paid' ? 'Dibayar' : 'Tertunggak'; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <div class="flex justify-center gap-2">
                                            <a href="view_payslip.php?id=<?php echo $row['id']; ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-xs transition">
                                                <span class="material-symbols-outlined text-[14px]">print</span> Cetak Slip
                                            </a>
                                            
                                            <?php if ($row['payment_status'] !== 'Paid'): ?>
                                                <form method="POST" onsubmit="return confirm('Tandakan payroll ini sebagai telah dibayar?');">
                                                    <input type="hidden" name="payroll_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="mark_paid"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                                        Tanda Dibayar
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">badge_card</span>
                                    Tiada rekod slip gaji penggajian bulan semasa.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ═══════════ PROCESS PAYROLL MODAL ═══════════ -->
<div class="modal-overlay" id="payrollModal">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-2xl overflow-hidden transform transition-all duration-300">
        <div class="bg-teal-800 text-white p-5 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2 text-[16px]">
                <span class="material-symbols-outlined">payments</span>
                Proses &amp; Jana Slip Gaji
            </h3>
            <button onclick="closePayrollModal()" class="text-white/60 hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form method="POST" class="p-6 space-y-4 max-h-[85vh] overflow-y-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-1 md:col-span-2">
                    <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Kakitangan / Staf</label>
                    <select name="staff_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <option value="">-- Sila Pilih Staf Aktif --</option>
                        <?php 
                        mysqli_data_seek($active_staff, 0);
                        while($s = $active_staff->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['position']})</option>"; 
                        ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-600 uppercase">Gaji Pokok (Basic Pay)</label>
                    <div class="flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2">
                        <span class="text-xs text-gray-500 font-bold mr-1">RM</span>
                        <input type="number" step="0.01" name="basic_salary" id="basic_salary_input" required placeholder="1500.00" oninput="recalcNetSalary()"
                               class="w-full border-0 bg-transparent p-1.5 text-sm focus:ring-0 text-right font-semibold">
                    </div>
                </div>
                
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-600 uppercase">Bulan</label>
                    <select name="month" required class="w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                        <?php for($m=1; $m<=12; ++$m) echo "<option value='$m' ".($m == date('n') ? 'selected' : '').">".date('F', mktime(0, 0, 0, $m, 1))."</option>"; ?>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-600 uppercase">Tahun</label>
                    <input type="number" name="year" value="<?php echo date('Y'); ?>" required
                           class="w-full rounded-lg border-gray-300 text-sm focus:border-teal-500 focus:ring-teal-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                <!-- Allowances dynamic lines -->
                <div class="space-y-3 p-4 bg-green-50/50 border border-green-100 rounded-xl">
                    <label class="block text-xs font-bold text-green-800 uppercase tracking-wider">Elaun Kakitangan (Earnings)</label>
                    <div id="allowances_container" class="space-y-2">
                        <div class="flex gap-2 items-center allow-row">
                            <input type="text" name="allow_name[]" placeholder="Nama Elaun (Cth: OT / Pengangkutan)"
                                   class="flex-1 rounded-lg border-gray-300 text-xs py-1 px-2 focus:border-teal-500 focus:ring-teal-500">
                            <div class="w-24 flex items-center bg-white border border-gray-300 rounded-lg px-2">
                                <input type="number" step="0.01" name="allow_amount[]" placeholder="0.00" oninput="recalcNetSalary()"
                                       class="w-full border-0 bg-transparent p-1 text-xs focus:ring-0 text-right font-semibold">
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addAllowanceLine()"
                            class="text-xs text-green-700 hover:text-green-900 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">add_circle</span> Tambah Elaun
                    </button>
                </div>

                <!-- Deductions dynamic lines -->
                <div class="space-y-3 p-4 bg-red-50/50 border border-red-100 rounded-xl">
                    <label class="block text-xs font-bold text-red-800 uppercase tracking-wider">Potongan Gaji (Deductions)</label>
                    <div id="deductions_container" class="space-y-2">
                        <div class="flex gap-2 items-center deduct-row">
                            <input type="text" name="deduct_name[]" placeholder="Cth: KWSP / Perkeso / EIS"
                                   class="flex-1 rounded-lg border-gray-300 text-xs py-1 px-2 focus:border-teal-500 focus:ring-teal-500">
                            <div class="w-24 flex items-center bg-white border border-gray-300 rounded-lg px-2">
                                <input type="number" step="0.01" name="deduct_amount[]" placeholder="0.00" oninput="recalcNetSalary()"
                                       class="w-full border-0 bg-transparent p-1 text-xs focus:ring-0 text-right font-semibold">
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addDeductionLine()"
                            class="text-xs text-red-700 hover:text-red-900 font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">add_circle</span> Tambah Potongan
                    </button>
                </div>
            </div>

            <!-- Net Salary calculation displays -->
            <div class="p-4 bg-teal-50 border border-teal-100 rounded-lg flex justify-between items-center">
                <span class="font-bold text-teal-800">GAJI BERSIH (NET SALARY):</span>
                <strong class="text-xl text-teal-900" id="net_salary_display">RM 0.00</strong>
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" name="process_payroll"
                        class="flex-1 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-lg text-sm transition shadow-sm">
                    ✅ Simpan &amp; Proses Penyata
                </button>
                <button type="button" onclick="closePayrollModal()"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

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

// Modal actions
function openPayrollModal() {
    document.getElementById('payrollModal').classList.add('active');
}
function closePayrollModal() {
    document.getElementById('payrollModal').classList.remove('active');
}

// Add dynamic fields
function addAllowanceLine() {
    const container = document.getElementById('allowances_container');
    const row = document.createElement('div');
    row.className = 'flex gap-2 items-center allow-row';
    row.innerHTML = `
        <input type="text" name="allow_name[]" placeholder="OT / Bonus / Pengangkutan"
               class="flex-1 rounded-lg border-gray-300 text-xs py-1 px-2 focus:border-teal-500 focus:ring-teal-500">
        <div class="w-24 flex items-center bg-white border border-gray-300 rounded-lg px-2">
            <input type="number" step="0.01" name="allow_amount[]" placeholder="0.00" oninput="recalcNetSalary()"
                   class="w-full border-0 bg-transparent p-1 text-xs focus:ring-0 text-right font-semibold">
        </div>
        <button type="button" onclick="this.closest('.allow-row').remove(); recalcNetSalary();" class="text-red-500 hover:text-red-700">
            <span class="material-symbols-outlined text-[18px]">delete</span>
        </button>
    `;
    container.appendChild(row);
}

function addDeductionLine() {
    const container = document.getElementById('deductions_container');
    const row = document.createElement('div');
    row.className = 'flex gap-2 items-center deduct-row';
    row.innerHTML = `
        <input type="text" name="deduct_name[]" placeholder="Cth: Cuti Tanpa Gaji"
               class="flex-1 rounded-lg border-gray-300 text-xs py-1 px-2 focus:border-teal-500 focus:ring-teal-500">
        <div class="w-24 flex items-center bg-white border border-gray-300 rounded-lg px-2">
            <input type="number" step="0.01" name="deduct_amount[]" placeholder="0.00" oninput="recalcNetSalary()"
                   class="w-full border-0 bg-transparent p-1 text-xs focus:ring-0 text-right font-semibold">
        </div>
        <button type="button" onclick="this.closest('.deduct-row').remove(); recalcNetSalary();" class="text-red-500 hover:text-red-700">
            <span class="material-symbols-outlined text-[18px]">delete</span>
        </button>
    `;
    container.appendChild(row);
}

function recalcNetSalary() {
    const basicInput = document.getElementById('basic_salary_input');
    let basic = basicInput.value ? parseFloat(basicInput.value) : 0;
    
    let allowances = 0;
    document.querySelectorAll('#allowances_container .allow-row').forEach(row => {
        const amtInput = row.querySelector('input[name="allow_amount[]"]');
        if (amtInput && amtInput.value) {
            allowances += parseFloat(amtInput.value);
        }
    });

    let deductions = 0;
    document.querySelectorAll('#deductions_container .deduct-row').forEach(row => {
        const amtInput = row.querySelector('input[name="deduct_amount[]"]');
        if (amtInput && amtInput.value) {
            deductions += parseFloat(amtInput.value);
        }
    });

    let net = basic + allowances - deductions;
    document.getElementById('net_salary_display').innerHTML = 'RM ' + net.toFixed(2);
}
</script>

</main>
</body>
</html>
