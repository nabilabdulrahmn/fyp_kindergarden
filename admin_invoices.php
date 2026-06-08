<?php
// admin_invoices.php
// Penjanaan Invois & Bil - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// ═══════════ FORM 1: INDIVIDUAL INVOICE GENERATION ═══════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_invoice'])) {
    $parent_id = (int)$_POST['parent_id'];
    $student_id = (int)$_POST['student_id'];
    
    $item_descriptions = $_POST['item_desc'] ?? [];
    $item_amounts = $_POST['item_amount'] ?? [];
    
    $items = [];
    $total_amount = 0;
    
    for ($i = 0; $i < count($item_descriptions); $i++) {
        $desc = trim($item_descriptions[$i]);
        $amt = (float)($item_amounts[$i] ?? 0);
        
        if (!empty($desc) && $amt > 0) {
            $items[] = [
                'description' => $desc,
                'amount' => $amt
            ];
            $total_amount += $amt;
        }
    }
    
    if (empty($items)) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: Sila masukkan sekurang-kurangnya satu butiran item yuran.</p>
        </div>";
    } else {
        $invoice_number = "INV-" . date('Ymd') . "-" . time();
        $items_json = $conn->real_escape_string(json_encode($items));
        
        $primary_type = $conn->real_escape_string($items[0]['description']);
        if (count($items) > 1) {
            $primary_type .= " & Lain-lain";
        }
        
        $sql = "INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, items_json, status) 
                VALUES ($parent_id, $student_id, '$invoice_number', $total_amount, '$primary_type', '$items_json', 'Pending')";
        
        if ($conn->query($sql)) {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                <span class='material-symbols-outlined'>check_circle</span>
                <div>
                    <p class='font-semibold'>Invois Individu berjaya dijana!</p>
                    <p class='text-xs text-green-700 mt-0.5'>No. Invois: <strong>$invoice_number</strong>.</p>
                </div>
            </div>";
        } else {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
                <span class='material-symbols-outlined'>error</span>
                <p class='font-semibold'>Ralat Sistem: " . $conn->error . "</p>
            </div>";
        }
    }
}

// ═══════════ FORM 2: BULK MONTHLY INVOICE GENERATION ═══════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_monthly_invoice'])) {
    $bulk_module = $conn->real_escape_string($_POST['bulk_module']);
    $bulk_class_id = $_POST['bulk_class_id'] ? (int)$_POST['bulk_class_id'] : 0;
    $bulk_fee_id = (int)$_POST['bulk_fee_id'];
    $bulk_month = (int)$_POST['bulk_month'];
    $bulk_year = (int)$_POST['bulk_year'];
    
    $month_str = sprintf("%04d-%02d", $bulk_year, $bulk_month);
    $month_name_formatted = date('F Y', mktime(0, 0, 0, $bulk_month, 1, $bulk_year));
    
    // Ambil maklumat template yuran
    $fee_query = $conn->query("SELECT fee_name, amount FROM fee_structures WHERE id = $bulk_fee_id LIMIT 1");
    if ($fee_query && $fee_query->num_rows > 0) {
        $fee_row = $fee_query->fetch_assoc();
        $fee_name_raw = $fee_row['fee_name'];
        $fee_amount = (float)$fee_row['amount'];
        $fee_name = $fee_name_raw . " (" . $month_name_formatted . ")";
        
        // Pilih pelajar aktif mengikut modul (dan kelas jika ada)
        $where_sc = "";
        if ($bulk_class_id > 0) {
            $where_sc = " AND sc.class_id = $bulk_class_id ";
        }
        
        $sql_students = "SELECT s.id AS student_id, s.parent_id, s.full_name AS student_name
                         FROM students s
                         LEFT JOIN student_classes sc ON s.id = sc.student_id
                         WHERE s.status = 'Active' AND s.module = '$bulk_module' $where_sc
                         GROUP BY s.id";
                         
        $res_students = $conn->query($sql_students);
        
        if ($res_students && $res_students->num_rows > 0) {
            $success_count = 0;
            $skipped_count = 0;
            
            while ($stu = $res_students->fetch_assoc()) {
                $stu_id = (int)$stu['student_id'];
                $parent_id = (int)$stu['parent_id'];
                
                // Semak duplikasi bagi pelajar dan bulan bil ini
                $type_search = $conn->real_escape_string($fee_name_raw);
                $dup_sql = "SELECT id FROM invoices 
                            WHERE student_id = $stu_id 
                              AND type LIKE '%$type_search%' 
                              AND DATE_FORMAT(created_at, '%Y-%m') = '$month_str' LIMIT 1";
                $dup_check = $conn->query($dup_sql);
                
                if ($dup_check && $dup_check->num_rows > 0) {
                    $skipped_count++;
                    continue; // Skip jika sudah dijana
                }
                
                // Bina invois baru
                $invoice_number = "INV-BULK-" . $stu_id . "-" . date('ymd') . time();
                $items = [[
                    'description' => $fee_name,
                    'amount' => $fee_amount
                ]];
                $items_json = $conn->real_escape_string(json_encode($items));
                
                $sql_ins = "INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, items_json, status)
                            VALUES ($parent_id, $stu_id, '$invoice_number', $fee_amount, '$fee_name', '$items_json', 'Pending')";
                if ($conn->query($sql_ins)) {
                    $success_count++;
                }
            }
            
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                <span class='material-symbols-outlined'>done_all</span>
                <div>
                    <p class='font-semibold'>Penjanaan Pukal Bulanan Selesai!</p>
                    <p class='text-xs text-green-700 mt-0.5'>Hasil: <strong>$success_count</strong> berjaya dijana, <strong>$skipped_count</strong> dilangkau (rekod duplikasi).</p>
                </div>
            </div>";
        } else {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
                <span class='material-symbols-outlined'>warning</span>
                <p class='font-semibold'>Tiada pelajar aktif ditemui dalam modul/kelas berkenaan.</p>
            </div>";
        }
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: Templat yuran bulanan tidak sah.</p>
        </div>";
    }
}

// ═══════════ FORM 3: CLASS-WIDE ONE-TIME INVOICE GENERATION ═══════════
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['class_one_time_invoice'])) {
    $class_id = (int)$_POST['class_id'];
    $one_time_fee_id = $_POST['one_time_fee_id'] ? (int)$_POST['one_time_fee_id'] : 0;
    
    // Dapatkan nama & jumlah yuran (sama ada templat atau kustom)
    $fee_name_raw = '';
    $fee_amount = 0.0;
    
    if ($one_time_fee_id > 0) {
        $ot_fee_query = $conn->query("SELECT fee_name, amount FROM fee_structures WHERE id = $one_time_fee_id LIMIT 1");
        if ($ot_fee_query && $ot_fee_query->num_rows > 0) {
            $ot_row = $ot_fee_query->fetch_assoc();
            $fee_name_raw = $ot_row['fee_name'];
            $fee_amount = (float)$ot_row['amount'];
        }
    } else {
        $fee_name_raw = trim($_POST['custom_fee_name']);
        $fee_amount = (float)$_POST['one_time_amount'];
    }
    
    if (empty($fee_name_raw) || $fee_amount <= 0) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: Sila masukkan nama yuran dan jumlah yang sah.</p>
        </div>";
    } else {
        $fee_name = $conn->real_escape_string($fee_name_raw);
        
        // Ambil semua pelajar berdaftar dalam kelas ini
        $sql_class_students = "SELECT s.id AS student_id, s.parent_id, c.class_name
                               FROM students s
                               JOIN student_classes sc ON s.id = sc.student_id
                               JOIN classes c ON sc.class_id = c.id
                               WHERE s.status = 'Active' AND sc.class_id = $class_id";
                               
        $res_class_students = $conn->query($sql_class_students);
        
        if ($res_class_students && $res_class_students->num_rows > 0) {
            $success_count = 0;
            $skipped_count = 0;
            $class_name = '';
            
            while ($stu = $res_class_students->fetch_assoc()) {
                $stu_id = (int)$stu['student_id'];
                $parent_id = (int)$stu['parent_id'];
                $class_name = $stu['class_name'];
                
                // Semak duplikasi bagi yuran ini (elak caj berganda satu kali)
                $dup_check = $conn->query("SELECT id FROM invoices WHERE student_id = $stu_id AND type = '$fee_name' LIMIT 1");
                if ($dup_check && $dup_check->num_rows > 0) {
                    $skipped_count++;
                    continue;
                }
                
                // Bina invois kelas satu kali
                $invoice_number = "INV-CLASS-" . $class_id . "-" . $stu_id . "-" . date('ymd') . time();
                $items = [[
                    'description' => $fee_name,
                    'amount' => $fee_amount
                ]];
                $items_json = $conn->real_escape_string(json_encode($items));
                
                $sql_ins = "INSERT INTO invoices (parent_id, student_id, invoice_number, amount, type, items_json, status)
                            VALUES ($parent_id, $stu_id, '$invoice_number', $fee_amount, '$fee_name', '$items_json', 'Pending')";
                if ($conn->query($sql_ins)) {
                    $success_count++;
                }
            }
            
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                <span class='material-symbols-outlined'>check_circle</span>
                <div>
                    <p class='font-semibold'>Invois Satu Kali Mengikut Kelas Selesai!</p>
                    <p class='text-xs text-green-700 mt-0.5'>Kelas: <strong>$class_name</strong>. Caj: <strong>$success_count</strong> berjaya dijana, <strong>$skipped_count</strong> dilangkau (rekod duplikasi).</p>
                </div>
            </div>";
        } else {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
                <span class='material-symbols-outlined'>warning</span>
                <p class='font-semibold'>Tiada pelajar aktif ditemui dalam kelas berkenaan.</p>
            </div>";
        }
    }
}

// Ambil data untuk dropdown (Ibu bapa, Pelajar, Struktur Yuran, Kelas)
$parents = $conn->query("SELECT id, full_name AS parent_name FROM parents ORDER BY full_name");
$students = $conn->query("SELECT id, full_name, module FROM students ORDER BY full_name");
$fees = $conn->query("SELECT id, module, fee_name, amount FROM fee_structures WHERE is_active = 1 ORDER BY module ASC, fee_name ASC");
$classes_list = $conn->query("SELECT id, class_name, module FROM classes ORDER BY module ASC, class_name ASC");

// Ambil senarai template fee dalam format JSON untuk kegunaan JS append (Individual)
$fee_templates = [];
if ($fees) {
    mysqli_data_seek($fees, 0); // reset pointer
    while($f = $fees->fetch_assoc()) {
        $fee_templates[] = $f;
    }
}

// Filter Carian Invois
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where_clauses = [];

if (!empty($search)) {
    $search_esc = $conn->real_escape_string($search);
    $where_clauses[] = "(i.invoice_number LIKE '%$search_esc%' OR p.full_name LIKE '%$search_esc%' OR s.full_name LIKE '%$search_esc%')";
}
if (!empty($status_filter)) {
    $status_esc = $conn->real_escape_string($status_filter);
    $where_clauses[] = "i.status = '$status_esc'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Ambil rekod invois sedia ada
$sql_invoices = "SELECT i.*, p.full_name AS parent_name, s.full_name AS student_name, s.module AS student_module
                 FROM invoices i
                 JOIN parents p ON i.parent_id = p.id
                 JOIN students s ON i.student_id = s.id
                 $where_sql
                 ORDER BY i.created_at DESC";
$invoices_result = $conn->query($sql_invoices);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Penjanaan Invois — Panel Admin</title>
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
        
        .modal-tab-btn.active { border-bottom-color: #5452b5; color: #5452b5; }
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
            <a href="admin_invoices.php"    class="nav-link sub-link active">🧾 <span>Penjanaan Invois</span></a>
            <a href="admin_expenses.php"    class="nav-link sub-link">📊 <span>Penyata Pendapatan</span></a>
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
                <h1 class="text-[18px] font-semibold text-[#191c1e]">🧾 Penjanaan &amp; Pengurusan Invois</h1>
                <p class="text-[11px] text-[#777583]">Jana invois terperinci dan pantau status yuran tertunggak</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]">System Controller</span>
                <span class="text-[11px] font-bold text-[#333093] bg-[#e2dfff]/60 px-2 py-0.5 rounded-full">Administrator</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#333093] flex items-center justify-center text-white font-bold select-none">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto">
        
        <?php echo $msg; ?>

        <!-- Search & Filter Controls -->
        <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm mb-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <form method="GET" class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari No. Invois, Penjaga, Pelajar..."
                       class="rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5] w-full md:w-72">
                <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5] w-full md:w-40">
                    <option value="">-- Semua Status --</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo $status_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Overdue" <?php echo $status_filter === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-xs transition">
                    Cari Rekod
                </button>
                <?php if(!empty($search) || !empty($status_filter)): ?>
                    <a href="admin_invoices.php" class="px-3 py-2 text-xs text-red-500 font-medium hover:underline flex items-center justify-center">Batal</a>
                <?php endif; ?>
            </form>
            
            <button onclick="openInvoiceModal()"
                    class="w-full md:w-auto px-5 py-2.5 bg-[#5452b5] hover:bg-[#3f3d93] text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[16px]">add</span> Jana Invois Baru
            </button>
        </div>

        <!-- Invoices List -->
        <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-gray-500">receipt_long</span>
                    Penyata Rekod Invois Berdaftar
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-100">No. Invois</th>
                            <th class="p-4 border-b border-gray-100">Ibu Bapa / Penjaga</th>
                            <th class="p-4 border-b border-gray-100">Pelajar (Modul)</th>
                            <th class="p-4 border-b border-gray-100">Ringkasan Yuran</th>
                            <th class="p-4 border-b border-gray-100">Jumlah Bil</th>
                            <th class="p-4 border-b border-gray-100">Tarikh Dijana</th>
                            <th class="p-4 border-b border-gray-100">Status</th>
                            <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[13px]">
                        <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                            <?php while ($row = $invoices_result->fetch_assoc()): ?>
                                <?php 
                                    $statusClass = '';
                                    if ($row['status'] === 'Paid') {
                                        $statusClass = 'bg-green-100 text-green-700';
                                    } elseif ($row['status'] === 'Overdue') {
                                        $statusClass = 'bg-red-100 text-red-700';
                                    } else {
                                        $statusClass = 'bg-amber-100 text-amber-700';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50/30 transition">
                                    <td class="p-4 font-bold text-[#5452b5]">
                                        <?php echo htmlspecialchars($row['invoice_number']); ?>
                                    </td>
                                    <td class="p-4 font-semibold"><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                    <td class="p-4">
                                        <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                        <span class="text-[10px] text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded"><?php echo htmlspecialchars($row['student_module']); ?></span>
                                    </td>
                                    <td class="p-4 text-gray-600 truncate max-w-xs" title="<?php echo htmlspecialchars($row['type']); ?>">
                                        <?php echo htmlspecialchars($row['type']); ?>
                                    </td>
                                    <td class="p-4 font-bold text-gray-800">RM <?php echo number_format($row['amount'], 2); ?></td>
                                    <td class="p-4 text-gray-500"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                    <td class="p-4">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $statusClass; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="view_invoice.php?id=<?php echo $row['id']; ?>" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-xs transition">
                                            <span class="material-symbols-outlined text-[14px]">visibility</span> Paparan Cetakan
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">receipt</span>
                                    Tiada invois dijumpai.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ═══════════ GENERATE INVOICE MODAL (COMBINED WORKFLOWS) ═══════════ -->
<div class="modal-overlay" id="invoiceModal">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-2xl overflow-hidden transform transition-all duration-300">
        <div class="bg-[#1a1c2e] text-white p-5 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2 text-[16px]">
                <span class="material-symbols-outlined">receipt</span>
                Penjanaan Invois Baru
            </h3>
            <button onclick="closeInvoiceModal()" class="text-white/60 hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Modal Tabs -->
        <div class="flex border-b border-gray-200 bg-gray-50/50">
            <button type="button" onclick="setModalTab('modal-tab-individual')" id="btn-modal-ind"
                    class="modal-tab-btn flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-[#5452b5] text-[#5452b5] transition active">
                Individu
            </button>
            <button type="button" onclick="setModalTab('modal-tab-bulk-monthly')" id="btn-modal-bulk"
                    class="modal-tab-btn flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent text-gray-500 hover:text-gray-800 transition">
                Pukal Bulanan
            </button>
            <button type="button" onclick="setModalTab('modal-tab-class-onetime')" id="btn-modal-class"
                    class="modal-tab-btn flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent text-gray-500 hover:text-gray-800 transition">
                Satu Kali (Kelas)
            </button>
        </div>

        <!-- TAB 1: INDIVIDUAL INVOICE -->
        <div id="modal-tab-individual" class="modal-tab-content p-6 space-y-4">
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Ibu Bapa / Penjaga</label>
                        <select name="parent_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Sila Pilih --</option>
                            <?php 
                            mysqli_data_seek($parents, 0);
                            while($p = $parents->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['parent_name']}</option>"; 
                            ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pelajar</label>
                        <select name="student_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Sila Pilih --</option>
                            <?php 
                            mysqli_data_seek($students, 0);
                            while($s = $students->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['full_name']} ({$s['module']})</option>"; 
                            ?>
                        </select>
                    </div>
                </div>

                <div class="divider border-t border-gray-100 my-4"></div>

                <!-- Itemized Lines -->
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Perincian Item Bil</label>
                        <div class="flex items-center gap-2">
                            <select id="quick_fee_selector" class="rounded-lg border-gray-300 text-xs py-1 focus:border-[#5452b5] focus:ring-[#5452b5]">
                                <option value="">-- Templat Yuran --</option>
                                <?php foreach ($fee_templates as $tpl): ?>
                                    <option value="<?php echo $tpl['amount']; ?>" data-name="[<?php echo $tpl['module']; ?>] <?php echo htmlspecialchars($tpl['fee_name']); ?>">
                                        [<?php echo $tpl['module']; ?>] <?php echo htmlspecialchars($tpl['fee_name']); ?> - RM <?php echo $tpl['amount']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" onclick="addTemplateLine()" class="px-2 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-xs font-bold">+ Tambah</button>
                        </div>
                    </div>
                    
                    <div id="invoice_items_container" class="space-y-2.5">
                        <div class="flex gap-3 items-center item-row">
                            <input type="text" name="item_desc[]" required placeholder="Cth: Yuran Bulanan Jun"
                                   class="flex-1 rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <div class="w-32 flex items-center bg-gray-100 border border-gray-300 rounded-lg px-2">
                                <span class="text-xs text-gray-500 font-bold mr-1">RM</span>
                                <input type="number" step="0.01" name="item_amount[]" required placeholder="0.00" oninput="calculateTotal()"
                                       class="w-full border-0 bg-transparent p-1 text-sm focus:ring-0 text-right font-semibold">
                            </div>
                            <button type="button" onclick="removeLineRow(this)" class="text-red-500 hover:text-red-700 transition">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </div>
                    </div>

                    <button type="button" onclick="addEmptyLine()" class="text-xs text-[#5452b5] hover:text-[#3f3d93] font-bold flex items-center gap-1 mt-2">
                        <span class="material-symbols-outlined text-[16px]">add_circle</span> Tambah Baris Yuran
                    </button>
                </div>

                <div class="divider border-t border-gray-100 my-4"></div>

                <div class="p-4 bg-gray-50 rounded-lg flex justify-between items-center border border-gray-100">
                    <span class="font-bold text-gray-700">Jumlah Keseluruhan:</span>
                    <strong class="text-xl text-red-600" id="grand_total_display">RM 0.00</strong>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="submit" name="generate_invoice" class="flex-1 py-2.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm transition shadow-sm">
                        ✅ Jana Invois Sekarang
                    </button>
                    <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">Batal</button>
                </div>
            </form>
        </div>

        <!-- TAB 2: BULK MONTHLY INVOICE -->
        <div id="modal-tab-bulk-monthly" class="modal-tab-content hidden p-6 space-y-4">
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Modul (Hadkan Yuran)</label>
                        <select name="bulk_module" id="bulk_module_select" onchange="filterBulkFeesAndClasses()" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Sila Pilih Modul --</option>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Kelas (Pilihan)</label>
                        <select name="bulk_class_id" id="bulk_class_select"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Semua Kelas di Bawah Modul --</option>
                            <?php 
                            mysqli_data_seek($classes_list, 0);
                            while($c = $classes_list->fetch_assoc()):
                            ?>
                                <option value="<?php echo $c['id']; ?>" data-module="<?php echo $c['module']; ?>">
                                    <?php echo htmlspecialchars($c['class_name']); ?> (<?php echo htmlspecialchars($c['module']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1 md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Yuran Bulanan (Template)</label>
                        <select name="bulk_fee_id" id="bulk_fee_select" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Sila Pilih Modul Dahulu --</option>
                            <?php 
                            $monthly_fees = $conn->query("SELECT id, module, fee_name, amount FROM fee_structures WHERE is_active = 1 AND frequency = 'Monthly' ORDER BY module ASC, fee_name ASC");
                            while($mf = $monthly_fees->fetch_assoc()):
                            ?>
                                <option value="<?php echo $mf['id']; ?>" data-module="<?php echo $mf['module']; ?>">
                                    [<?php echo $mf['module']; ?>] <?php echo htmlspecialchars($mf['fee_name']); ?> - RM <?php echo $mf['amount']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-gray-600 uppercase">Bulan</label>
                            <select name="bulk_month" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                                <?php for($m=1;$m<=12;$m++) echo "<option value='$m' ".($m==date('n')?'selected':'').">".date('M', mktime(0,0,0,$m,1))."</option>"; ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-xs font-bold text-gray-600 uppercase">Tahun</label>
                            <input type="number" name="bulk_year" value="<?php echo date('Y'); ?>" required
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                        </div>
                    </div>
                </div>

                <div class="bg-amber-50 p-4 border border-amber-200 rounded-lg text-xs text-amber-800 space-y-1">
                    <p class="font-bold flex items-center gap-1 mb-1">
                        <span class="material-symbols-outlined text-[16px]">info</span> Nota Penjanaan Pukal
                    </p>
                    <p>1. Penjanaan ini akan menghasilkan invois bulanan bagi <strong>semua pelajar aktif</strong> dalam modul/kelas yang dipilih.</p>
                    <p>2. Sistem secara automatik menyemak duplikasi bagi menghalang pelajar dicaj yuran yang sama dua kali pada bulan yang sama.</p>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="submit" name="bulk_monthly_invoice" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-sm transition shadow-sm">
                        ⚡ Jana Invois Pukal Bulanan
                    </button>
                    <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">Batal</button>
                </div>
            </form>
        </div>

        <!-- TAB 3: CLASS ONE-TIME INVOICE -->
        <div id="modal-tab-class-onetime" class="modal-tab-content hidden p-6 space-y-4">
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Kelas Sasaran</label>
                        <select name="class_id" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Sila Pilih Kelas --</option>
                            <?php 
                            mysqli_data_seek($classes_list, 0);
                            while($c = $classes_list->fetch_assoc()):
                            ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['class_name']); ?> (<?php echo htmlspecialchars($c['module']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Pilih Templat Satu Kali (Jika Ada)</label>
                        <select name="one_time_fee_id" id="ot_fee_select" onchange="populateOTAmount()"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Kustom (Isi Butiran di Bawah) --</option>
                            <?php 
                            $ot_fees = $conn->query("SELECT id, module, fee_name, amount FROM fee_structures WHERE is_active = 1 AND frequency = 'One-Time' ORDER BY module ASC, fee_name ASC");
                            while($of = $ot_fees->fetch_assoc()):
                            ?>
                                <option value="<?php echo $of['id']; ?>" data-name="<?php echo htmlspecialchars($of['fee_name']); ?>" data-amount="<?php echo $of['amount']; ?>">
                                    [<?php echo $of['module']; ?>] <?php echo htmlspecialchars($of['fee_name']); ?> - RM <?php echo $of['amount']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="space-y-1 md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Nama Yuran Satu Kali</label>
                        <input type="text" name="custom_fee_name" id="ot_custom_name" required placeholder="Cth: Bayaran Uniform Sukan / Yuran Buku"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Jumlah Yuran (RM)</label>
                        <div class="flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2">
                            <span class="text-xs text-gray-500 font-bold mr-1">RM</span>
                            <input type="number" step="0.01" name="one_time_amount" id="ot_custom_amount" required placeholder="0.00"
                                   class="w-full border-0 bg-transparent p-1.5 text-sm focus:ring-0 text-right font-semibold">
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 p-4 border border-blue-100 rounded-lg text-xs text-blue-800 space-y-1">
                    <p class="font-bold flex items-center gap-1 mb-1">
                        <span class="material-symbols-outlined text-[16px]">info</span> Penjanaan Satu Kali Kelas
                    </p>
                    <p>1. Caj akan dikenakan kepada <strong>seluruh pelajar</strong> yang berdaftar di dalam kelas terpilih.</p>
                    <p>2. Sesuai untuk yuran one-time seperti pembelian uniform, kem, atau buku rujukan kelas.</p>
                </div>

                <div class="pt-4 flex gap-3">
                    <button type="submit" name="class_one_time_invoice" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-sm transition shadow-sm">
                        🚀 Jana Invois Satu Kali Kelas
                    </button>
                    <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">Batal</button>
                </div>
            </form>
        </div>
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

// Modal management
function openInvoiceModal() {
    document.getElementById('invoiceModal').classList.add('active');
    setModalTab('modal-tab-individual'); // default tab
}
function closeInvoiceModal() {
    document.getElementById('invoiceModal').classList.remove('active');
}

// Modal tab controls
function setModalTab(tabId) {
    // Hide all contents
    document.querySelectorAll('.modal-tab-content').forEach(el => el.classList.add('hidden'));
    // Deactivate all buttons
    document.querySelectorAll('.modal-tab-btn').forEach(btn => {
        btn.classList.remove('text-[#5452b5]', 'border-[#5452b5]', 'active');
        btn.classList.add('text-gray-500', 'border-transparent');
    });
    
    // Show current content
    document.getElementById(tabId).classList.remove('hidden');
    
    // Activate current button
    let btnId = 'btn-modal-ind';
    if (tabId === 'modal-tab-bulk-monthly') btnId = 'btn-modal-bulk';
    if (tabId === 'modal-tab-class-onetime') btnId = 'btn-modal-class';
    
    const activeBtn = document.getElementById(btnId);
    activeBtn.classList.remove('text-gray-500', 'border-transparent');
    activeBtn.classList.add('text-[#5452b5]', 'border-[#5452b5]', 'active');
}

// Dynamically filter options based on Module choice in bulk tab
function filterBulkFeesAndClasses() {
    const selectedModule = document.getElementById('bulk_module_select').value;
    
    // Filter yuran bulanan
    const feeSelect = document.getElementById('bulk_fee_select');
    for (let i = 0; i < feeSelect.options.length; i++) {
        const opt = feeSelect.options[i];
        if (opt.value === "") continue;
        const optModule = opt.getAttribute('data-module');
        if (optModule === selectedModule) {
            opt.style.display = '';
            opt.disabled = false;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    }
    feeSelect.value = ""; // reset selection
    
    // Filter kelas
    const classSelect = document.getElementById('bulk_class_select');
    for (let i = 0; i < classSelect.options.length; i++) {
        const opt = classSelect.options[i];
        if (opt.value === "") continue;
        const optModule = opt.getAttribute('data-module');
        if (!selectedModule || optModule === selectedModule) {
            opt.style.display = '';
            opt.disabled = false;
        } else {
            opt.style.display = 'none';
            opt.disabled = true;
        }
    }
    classSelect.value = ""; // reset selection
}

// Populate One-Time values from template selection
function populateOTAmount() {
    const selector = document.getElementById('ot_fee_select');
    const nameInput = document.getElementById('ot_custom_name');
    const amtInput = document.getElementById('ot_custom_amount');
    
    if (selector.value) {
        const option = selector.options[selector.selectedIndex];
        nameInput.value = option.getAttribute('data-name');
        amtInput.value = option.getAttribute('data-amount');
    } else {
        nameInput.value = '';
        amtInput.value = '';
    }
}

// Individual Invoice item rows management
function addEmptyLine(description = '', amount = '') {
    const container = document.getElementById('invoice_items_container');
    const row = document.createElement('div');
    row.className = 'flex gap-3 items-center item-row';
    row.innerHTML = `
        <input type="text" name="item_desc[]" value="${description}" required placeholder="Cth: Buku Aktiviti / Uniform"
               class="flex-1 rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
        <div class="w-32 flex items-center bg-gray-100 border border-gray-300 rounded-lg px-2">
            <span class="text-xs text-gray-500 font-bold mr-1">RM</span>
            <input type="number" step="0.01" name="item_amount[]" value="${amount}" required placeholder="0.00" oninput="calculateTotal()"
                   class="w-full border-0 bg-transparent p-1 text-sm focus:ring-0 text-right font-semibold">
        </div>
        <button type="button" onclick="removeLineRow(this)" class="text-red-500 hover:text-red-700 transition">
            <span class="material-symbols-outlined">delete</span>
        </button>
    `;
    container.appendChild(row);
    calculateTotal();
}

function removeLineRow(buttonEl) {
    const container = document.getElementById('invoice_items_container');
    if (container.querySelectorAll('.item-row').length > 1) {
        buttonEl.closest('.item-row').remove();
        calculateTotal();
    } else {
        alert("Sila masukkan sekurang-kurangnya satu butiran item invois.");
    }
}

function addTemplateLine() {
    const selector = document.getElementById('quick_fee_selector');
    if (!selector.value) return;
    
    const option = selector.options[selector.selectedIndex];
    const name = option.getAttribute('data-name');
    const amount = option.value;
    
    const firstRowDescInput = document.querySelector('#invoice_items_container .item-row:first-child input[name="item_desc[]"]');
    const firstRowAmountInput = document.querySelector('#invoice_items_container .item-row:first-child input[name="item_amount[]"]');
    
    if (firstRowDescInput && firstRowDescInput.value === '' && firstRowAmountInput && firstRowAmountInput.value === '') {
        firstRowDescInput.value = name;
        firstRowAmountInput.value = amount;
        calculateTotal();
    } else {
        addEmptyLine(name, amount);
    }
    
    selector.value = ''; // reset selector
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('#invoice_items_container .item-row').forEach(row => {
        const amtInput = row.querySelector('input[name="item_amount[]"]');
        if (amtInput && amtInput.value) {
            total += parseFloat(amtInput.value);
        }
    });
    document.getElementById('grand_total_display').innerHTML = 'RM ' + total.toFixed(2);
}
</script>

</main>
</body>
</html>
