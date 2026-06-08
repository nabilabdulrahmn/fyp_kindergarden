<?php
// admin_fees.php
// Pemantauan Yuran & Struktur Pembayaran - Admin
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// 1. Tambah Struktur Yuran Baru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fee'])) {
    $module = $conn->real_escape_string($_POST['module']);
    $fee_name = $conn->real_escape_string($_POST['fee_name']);
    $amount = (float)$_POST['amount'];
    $frequency = $conn->real_escape_string($_POST['frequency']);
    
    $sql = "INSERT INTO fee_structures (module, fee_name, amount, frequency) VALUES ('$module', '$fee_name', $amount, '$frequency')";
    if ($conn->query($sql)) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
            <span class='material-symbols-outlined'>check_circle</span>
            <p class='font-semibold'>Struktur Yuran Baru berjaya ditambah!</p>
        </div>";
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: " . $conn->error . "</p>
        </div>";
    }
}

// 2. Luluskan/Verify Pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    
    // Dapatkan maklumat payment
    $pay_query = $conn->query("SELECT invoice_id, receipt_file FROM payments WHERE id = $payment_id LIMIT 1");
    if ($pay_query && $pay_query->num_rows > 0) {
        $p_data = $pay_query->fetch_assoc();
        $invoice_id = (int)$p_data['invoice_id'];
        $receipt_file = $conn->real_escape_string($p_data['receipt_file']);
        
        $conn->begin_transaction();
        try {
            // Update status payment
            $conn->query("UPDATE payments SET status = 'Verified', verified_by = $user_id, verified_at = NOW() WHERE id = $payment_id");
            
            // Update status invoice
            $conn->query("UPDATE invoices SET status = 'Paid', receipt_file = '$receipt_file' WHERE id = $invoice_id");
            
            // Batalkan bayaran pending lain untuk invois yang sama (jika ada double submission)
            $conn->query("UPDATE payments SET status = 'Rejected', rejection_reason = 'Sistem: Pembayaran lain telah disahkan', verified_by = $user_id, verified_at = NOW() WHERE invoice_id = $invoice_id AND status = 'Pending'");
            
            $conn->commit();
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                <span class='material-symbols-outlined'>verified</span>
                <div>
                    <p class='font-semibold'>Pembayaran berjaya disahkan!</p>
                    <p class='text-xs text-green-700 mt-0.5'>Invois telah dikemaskini kepada status 'Paid' dan resit rasmi telah dijanakan untuk ibu bapa.</p>
                </div>
            </div>";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
                <span class='material-symbols-outlined'>error</span>
                <p class='font-semibold'>Ralat pengesahan: " . $e->getMessage() . "</p>
            </div>";
        }
    }
}

// 3. Tolak Pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_payment'])) {
    $payment_id = (int)$_POST['payment_id'];
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    // Dapatkan maklumat invoice_id untuk payment ini
    $pay_query = $conn->query("SELECT invoice_id FROM payments WHERE id = $payment_id LIMIT 1");
    $invoice_id = 0;
    if ($pay_query && $pay_query->num_rows > 0) {
        $invoice_id = (int)$pay_query->fetch_assoc()['invoice_id'];
    }
    
    // Tolak semua bayaran pending untuk invois yang sama (menghalang status stuck)
    $sql_reject = "UPDATE payments SET status = 'Rejected', rejection_reason = '$rejection_reason', verified_by = $user_id, verified_at = NOW() WHERE invoice_id = $invoice_id AND status = 'Pending'";
    if ($conn->query($sql_reject)) {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
            <span class='material-symbols-outlined'>cancel</span>
            <p class='font-semibold'>Pembayaran telah ditolak. Ibu bapa akan dimaklumkan untuk menghantar bukti semula.</p>
        </div>";
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>error</span>
            <p class='font-semibold'>Ralat: " . $conn->error . "</p>
        </div>";
    }
}

// 4. Tukar Status Aktif Yuran
if (isset($_GET['toggle_active'])) {
    $id = (int)$_GET['toggle_active'];
    $conn->query("UPDATE fee_structures SET is_active = NOT is_active WHERE id = $id");
    header("Location: admin_fees.php");
    exit();
}

// Ambil Struktur Yuran
$sql_fees = "SELECT * FROM fee_structures ORDER BY module ASC, fee_name ASC";
$fees_result = $conn->query($sql_fees);

// Ambil Rekod Pembayaran Pending (Verifications)
$sql_pending_payments = "SELECT p.*, i.invoice_number, i.amount AS invoice_amount, i.type AS invoice_type,
                                pr.full_name AS parent_name, s.full_name AS student_name, s.module AS student_module
                         FROM payments p
                         JOIN invoices i ON p.invoice_id = i.id
                         JOIN parents pr ON p.parent_id = pr.id
                         JOIN students s ON i.student_id = s.id
                         WHERE p.status = 'Pending'
                         ORDER BY p.created_at ASC";
$pending_result = $conn->query($sql_pending_payments);

// ── Baru: Pemantauan Bayaran (Tracking) ──────────────────────────
// 1. Ambil statistik ringkasan yuran
$stat_paid = 0.0;
$stat_paid_q = $conn->query("SELECT SUM(amount) AS total FROM invoices WHERE status = 'Paid'");
if ($stat_paid_q) $stat_paid = (float)$stat_paid_q->fetch_assoc()['total'];

// Unpaid/Overdue: Invoices with status Pending/Overdue and NOT having any pending payment verification
$stat_unpaid = 0.0;
$stat_unpaid_q = $conn->query("SELECT SUM(amount) AS total FROM invoices WHERE status IN ('Pending', 'Overdue') AND id NOT IN (SELECT DISTINCT invoice_id FROM payments WHERE status = 'Pending')");
if ($stat_unpaid_q) $stat_unpaid = (float)$stat_unpaid_q->fetch_assoc()['total'];

// Pecahan berasingan untuk Pie Chart:
// Pending (tanpa bayaran pending)
$stat_pending = 0.0;
$stat_pending_q = $conn->query("SELECT SUM(amount) AS total FROM invoices WHERE status = 'Pending' AND id NOT IN (SELECT DISTINCT invoice_id FROM payments WHERE status = 'Pending')");
if ($stat_pending_q) $stat_pending = (float)$stat_pending_q->fetch_assoc()['total'];

// Overdue (tanpa bayaran pending)
$stat_overdue = 0.0;
$stat_overdue_q = $conn->query("SELECT SUM(amount) AS total FROM invoices WHERE status = 'Overdue' AND id NOT IN (SELECT DISTINCT invoice_id FROM payments WHERE status = 'Pending')");
if ($stat_overdue_q) $stat_overdue = (float)$stat_overdue_q->fetch_assoc()['total'];

// Awaiting verification: Invoices with status Pending/Overdue that DO have a pending payment verification
$stat_pending_verify = 0.0;
$stat_pending_verify_q = $conn->query("SELECT SUM(amount) AS total FROM invoices WHERE id IN (SELECT DISTINCT invoice_id FROM payments WHERE status = 'Pending')");
if ($stat_pending_verify_q) $stat_pending_verify = (float)$stat_pending_verify_q->fetch_assoc()['total'];

// 2. Ambil Semua Invois untuk Tracking
$sql_tracking = "SELECT i.*, 
                        s.full_name AS student_name, s.module AS student_module,
                        p.full_name AS parent_name,
                        c.class_name,
                        (SELECT COUNT(*) FROM payments pay WHERE pay.invoice_id = i.id AND pay.status = 'Pending') AS has_pending_payment,
                        (SELECT pay.id FROM payments pay WHERE pay.invoice_id = i.id AND pay.status = 'Pending' LIMIT 1) AS pending_payment_id
                 FROM invoices i
                 JOIN students s ON i.student_id = s.id
                 JOIN parents p ON i.parent_id = p.id
                 LEFT JOIN student_classes sc ON s.id = sc.student_id
                 LEFT JOIN classes c ON sc.class_id = c.id
                 ORDER BY i.created_at DESC";
$tracking_result = $conn->query($sql_tracking);
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Struktur Yuran &amp; Pengesahan — Panel Admin</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:100dvh; font-family:'Inter',sans-serif; }

        
        .tab-btn.active { border-bottom-color: #5452b5; color: #5452b5; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<?php include 'sidebar_admin.php'; ?>

<!-- ═══════════ MAIN CONTENT ═══════════ -->
<main class="md:ml-[260px] min-h-screen main-content-shifted">
    
    <!-- Top Bar -->
    <header class="fixed top-0 right-0 w-full md:w-[calc(100%-260px)] bg-white border-b border-[#e0e3e5]
                   flex items-center justify-between px-6 h-[68px] z-40 shadow-sm">
        <div class="flex items-center gap-4">
            <button class="md:hidden p-2 rounded-lg hover:bg-gray-100" onclick="toggleSidebar()">
                <span class="material-symbols-outlined text-[#464552]">menu</span>
            </button>
            <div>
                <h1 class="text-[18px] font-semibold text-[#191c1e]">💰 Pemantauan Yuran &amp; Pengesahan</h1>
                <p class="text-[11px] text-[#777583]">Urus struktur yuran pelajar dan sahkan bukti bayaran ibu bapa</p>
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

        <!-- Tabs Navigation -->
        <div class="flex border-b border-gray-200 mb-6 bg-white rounded-t-xl px-4 pt-2 shadow-sm border border-b-0 border-[#c7c5d4]/10">
            <button onclick="switchTab('tab-verification')" id="btn-tab-verification"
                    class="tab-btn px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent text-gray-500 hover:text-gray-800 transition flex items-center gap-2 active">
                <span class="material-symbols-outlined text-[18px]">verified_user</span>
                Pengesahan Bayaran (<?php echo $pending_result->num_rows; ?>)
            </button>
            <button onclick="switchTab('tab-tracking')" id="btn-tab-tracking"
                    class="tab-btn px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent text-gray-500 hover:text-gray-800 transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">analytics</span>
                Pemantauan Status Bayaran
            </button>
            <button onclick="switchTab('tab-structures')" id="btn-tab-structures"
                    class="tab-btn px-5 py-3 text-xs font-bold uppercase tracking-wider border-b-2 border-transparent text-gray-500 hover:text-gray-800 transition flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">payments</span>
                Struktur Yuran Sekolah
            </button>
        </div>

        <!-- ═══════════ TAB 1: PAYMENT VERIFICATION ═══════════ -->
        <div id="tab-verification" class="tab-content">
            <div class="bg-white rounded-b-xl border border-[#c7c5d4]/20 border-t-0 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-bold text-gray-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-gray-500">pending_actions</span>
                        Senarai Bayaran Menunggu Pengesahan
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                                <th class="p-4 border-b border-gray-100">Ibu Bapa</th>
                                <th class="p-4 border-b border-gray-100">Pelajar</th>
                                <th class="p-4 border-b border-gray-100">No. Invois &amp; Butiran</th>
                                <th class="p-4 border-b border-gray-100">Jumlah</th>
                                <th class="p-4 border-b border-gray-100">Kaedah &amp; Ref</th>
                                <th class="p-4 border-b border-gray-100">Bukti Resit</th>
                                <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-[13px]">
                            <?php if ($pending_result && $pending_result->num_rows > 0): ?>
                                <?php while ($row = $pending_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50/30 transition">
                                        <td class="p-4 font-semibold text-gray-800"><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                        <td class="p-4">
                                            <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                            <span class="text-[11px] text-gray-500 bg-gray-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($row['student_module']); ?></span>
                                        </td>
                                        <td class="p-4">
                                            <strong class="text-blue-700"><?php echo htmlspecialchars($row['invoice_number']); ?></strong><br>
                                            <span class="text-[11px] text-gray-500"><?php echo htmlspecialchars($row['invoice_type']); ?></span>
                                        </td>
                                        <td class="p-4 font-bold text-gray-900">RM <?php echo number_format($row['amount_paid'], 2); ?></td>
                                        <td class="p-4">
                                            <strong><?php echo htmlspecialchars($row['payment_method']); ?></strong><br>
                                            <span class="text-xs font-mono text-gray-500"><?php echo htmlspecialchars($row['transaction_ref'] ?: '-'); ?></span>
                                        </td>
                                        <td class="p-4">
                                            <?php if (!empty($row['receipt_file']) && file_exists($row['receipt_file'])): ?>
                                                <button onclick="viewReceipt('<?php echo htmlspecialchars($row['receipt_file']); ?>')"
                                                        class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-bold transition hover:underline">
                                                    <span class="material-symbols-outlined text-[16px]">image</span> Resit.jpg
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">Tiada fail</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="flex justify-center gap-2">
                                                <!-- Form Lulus -->
                                                <form method="POST" onsubmit="return confirm('Sahkan pembayaran ini?');">
                                                    <input type="hidden" name="payment_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" name="approve_payment"
                                                            class="px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                                        Lulus
                                                    </button>
                                                </form>
                                                <!-- Button Tolak -->
                                                <button onclick="openRejectModal(<?php echo $row['id']; ?>)"
                                                        class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                                    Tolak
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-400">
                                        <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">check_circle_outline</span>
                                        Semua bukti bayaran telah disahkan. Tiada tugasan tertunda!
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ═══════════ TAB 3: PAYMENT TRACKING ═══════════ -->
        <div id="tab-tracking" class="tab-content hidden">
            
            <!-- Grid for Stats & Pie Chart -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Left: Stats Cards (Col-span-2) -->
                <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[11px] text-gray-500 font-bold uppercase tracking-wider">Jumlah Telah Dikutip</span>
                            <span class="material-symbols-outlined text-green-600">check_circle</span>
                        </div>
                        <div>
                            <h3 class="text-[20px] font-bold text-green-600">RM <?php echo number_format($stat_paid, 2); ?></h3>
                            <p class="text-[10px] text-gray-400 mt-1">Invois bertanda 'Paid' / Lunas</p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[11px] text-gray-500 font-bold uppercase tracking-wider">Menunggu Pengesahan</span>
                            <span class="material-symbols-outlined text-blue-600">hourglass_empty</span>
                        </div>
                        <div>
                            <h3 class="text-[20px] font-bold text-blue-600">RM <?php echo number_format($stat_pending_verify, 2); ?></h3>
                            <p class="text-[10px] text-gray-400 mt-1">Pembayaran menunggu semakan admin</p>
                        </div>
                    </div>

                    <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-[11px] text-gray-500 font-bold uppercase tracking-wider">Tunggakan / Belum Bayar</span>
                            <span class="material-symbols-outlined text-red-500">warning</span>
                        </div>
                        <div>
                            <h3 class="text-[20px] font-bold text-red-500">RM <?php echo number_format($stat_unpaid, 2); ?></h3>
                            <p class="text-[10px] text-gray-400 mt-1">Invois 'Pending' atau 'Overdue'</p>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Pie Chart Card (Col-span-1) -->
                <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[11px] text-gray-500 font-bold uppercase tracking-wider">Status Kutipan Keseluruhan</span>
                        <span class="material-symbols-outlined text-purple-600">pie_chart</span>
                    </div>
                    <div class="relative w-full h-[140px] flex items-center justify-center">
                        <canvas id="feeStatusPieChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Invoices Payment Tracking Table -->
            <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
                
                <!-- Table Header with Filters -->
                <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
                    <div>
                        <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-500">monitoring</span>
                            Status Pembayaran Invois Pelajar
                        </h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Jejak status kutipan yuran dan muat turun resit/invois</p>
                    </div>
                    
                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto">
                        <!-- Search Name -->
                        <div class="relative w-full sm:w-48">
                            <span class="material-symbols-outlined absolute left-2.5 top-1.5 text-[18px] text-gray-400">search</span>
                            <input type="text" id="search_tracking_student" onkeyup="filterTrackingTable()" placeholder="Cari pelajar / waris..."
                                   class="w-full pl-9 pr-3 py-1 rounded-lg border-gray-300 text-xs focus:border-[#5452b5] focus:ring-[#5452b5]">
                        </div>
                        
                        <!-- Filter Status -->
                        <select id="filter_tracking_status" onchange="filterTrackingTable()" 
                                class="rounded-lg border-gray-300 text-xs py-1 px-2.5 focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Semua Status --</option>
                            <option value="Paid">Lunas (Paid)</option>
                            <option value="Verification">Menunggu Pengesahan</option>
                            <option value="Pending">Belum Bayar (Pending)</option>
                            <option value="Overdue">Tunggakan (Overdue)</option>
                        </select>
                        
                        <!-- Filter Module -->
                        <select id="filter_tracking_module" onchange="filterTrackingTable()" 
                                class="rounded-lg border-gray-300 text-xs py-1 px-2.5 focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Semua Modul --</option>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                                <th class="p-4 border-b border-gray-100">Pelajar &amp; Kelas</th>
                                <th class="p-4 border-b border-gray-100">Nama Waris</th>
                                <th class="p-4 border-b border-gray-100">No. Invois / Butiran</th>
                                <th class="p-4 border-b border-gray-100">Jumlah</th>
                                <th class="p-4 border-b border-gray-100">Tarikh Bil</th>
                                <th class="p-4 border-b border-gray-100">Status</th>
                                <th class="p-4 border-b border-gray-100 text-center">Dokumen / Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-[13px]">
                            <?php if ($tracking_result && $tracking_result->num_rows > 0): ?>
                                <?php while ($row = $tracking_result->fetch_assoc()): 
                                    $disp_status = $row['status'];
                                    if ($row['has_pending_payment'] > 0) {
                                        $disp_status = 'Verification';
                                    }
                                    
                                    // Set status styling details
                                    $badge_class = '';
                                    $badge_text = '';
                                    if ($disp_status === 'Paid') {
                                        $badge_class = 'bg-green-100 text-green-700';
                                        $badge_text = 'Lunas / Paid';
                                    } elseif ($disp_status === 'Verification') {
                                        $badge_class = 'bg-blue-100 text-blue-700 animate-pulse';
                                        $badge_text = 'Pengesahan Bukti';
                                    } elseif ($disp_status === 'Overdue') {
                                        $badge_class = 'bg-red-100 text-red-700';
                                        $badge_text = 'Tunggakan';
                                    } else {
                                        $badge_class = 'bg-amber-100 text-amber-700';
                                        $badge_text = 'Belum Bayar';
                                    }
                                ?>
                                    <tr class="hover:bg-gray-50/30 transition tracking-row" 
                                        data-student="<?php echo htmlspecialchars($row['student_name']); ?>"
                                        data-parent="<?php echo htmlspecialchars($row['parent_name']); ?>"
                                        data-status="<?php echo $disp_status; ?>"
                                        data-module="<?php echo htmlspecialchars($row['student_module']); ?>">
                                        <td class="p-4">
                                            <strong><?php echo htmlspecialchars($row['student_name']); ?></strong><br>
                                            <span class="text-[11px] text-gray-500"><?php echo htmlspecialchars($row['class_name'] ?: 'Tiada Kelas'); ?> (<?php echo htmlspecialchars($row['student_module']); ?>)</span>
                                        </td>
                                        <td class="p-4 text-gray-700"><?php echo htmlspecialchars($row['parent_name']); ?></td>
                                        <td class="p-4">
                                            <strong class="text-blue-700"><?php echo htmlspecialchars($row['invoice_number']); ?></strong><br>
                                            <span class="text-[11px] text-gray-500"><?php echo htmlspecialchars($row['type']); ?></span>
                                        </td>
                                        <td class="p-4 font-bold text-gray-900">RM <?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="p-4 text-gray-600"><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                                        <td class="p-4">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $badge_class; ?>">
                                                <?php echo $badge_text; ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <div class="flex justify-center items-center gap-2">
                                                <?php if ($disp_status === 'Paid'): ?>
                                                    <!-- Print receipt -->
                                                    <a href="view_receipt.php?id=<?php echo $row['id']; ?>" target="_blank"
                                                       class="px-2.5 py-1.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px]">receipt_long</span> Resit
                                                    </a>
                                                <?php elseif ($disp_status === 'Verification'): ?>
                                                    <!-- Link to Verification or pop-up verification directly -->
                                                    <?php
                                                    // Fetch details of the pending payment record for this invoice
                                                    $p_id = $row['pending_payment_id'];
                                                    $p_file_query = $conn->query("SELECT receipt_file FROM payments WHERE id = $p_id LIMIT 1");
                                                    $p_file = ($p_file_query && $p_file_query->num_rows > 0) ? $p_file_query->fetch_assoc()['receipt_file'] : '';
                                                    ?>
                                                    <button type="button" onclick="viewReceipt('<?php echo htmlspecialchars($p_file); ?>')"
                                                            class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px]">image</span> Bukti
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Sahkan pembayaran ini?');" class="inline">
                                                        <input type="hidden" name="payment_id" value="<?php echo $p_id; ?>">
                                                        <button type="submit" name="approve_payment"
                                                                class="px-2.5 py-1.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                                            Lulus
                                                        </button>
                                                    </form>
                                                    <button type="button" onclick="openRejectModal(<?php echo $p_id; ?>)"
                                                            class="px-2 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition shadow-sm">
                                                        Tolak
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Print invoice -->
                                                    <a href="view_invoice.php?id=<?php echo $row['id']; ?>" target="_blank"
                                                       class="px-2.5 py-1.5 bg-gray-600 hover:bg-gray-700 text-white font-bold rounded-lg text-xs transition shadow-sm flex items-center gap-1">
                                                        <span class="material-symbols-outlined text-[14px]">description</span> Invois
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-400">
                                        Tiada invois yang direkodkan dalam sistem.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- ═══════════ TAB 2: FEE STRUCTURES ═══════════ -->
        <div id="tab-structures" class="tab-content hidden">
            <!-- Add New Fee Form -->
            <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm p-6 mb-6">
                <h3 class="font-bold text-gray-800 text-sm mb-4 flex items-center gap-2" style="color:#5452b5;">
                    <span class="material-symbols-outlined">add_box</span>
                    Tambah Templat Struktur Yuran Baharu
                </h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Modul Program</label>
                        <select name="module" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                    </div>
                    <div class="space-y-1 md:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Nama Struktur Yuran</label>
                        <input type="text" name="fee_name" required placeholder="Cth: Yuran Pendaftaran 2026 atau Yuran Bulanan"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Kekerapan Bil</label>
                        <select name="frequency" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="Monthly">Setiap Bulan</option>
                            <option value="Yearly">Setahun Sekali</option>
                            <option value="One-Time">Sekali Sahaja (Pendaftaran)</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold text-gray-600 uppercase">Jumlah Yuran (RM)</label>
                        <input type="number" step="0.01" name="amount" required placeholder="Cth: 350.00"
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-[#5452b5] focus:ring-[#5452b5]">
                    </div>
                    <div class="md:col-span-4 pt-2 flex justify-end">
                        <button type="submit" name="add_fee"
                                class="px-5 py-2.5 bg-[#5452b5] hover:bg-[#3f3d93] text-white font-bold rounded-lg text-xs transition shadow-sm">
                            + Tambah Struktur Yuran
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Fees Table -->
            <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-gray-500">list_alt</span>
                        Senarai Struktur Yuran Sedia Ada
                    </h3>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <select id="filter_fee_module" onchange="filterFeeStructuresTable()" class="rounded-lg border-gray-300 text-xs py-1 px-2.5 focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Semua Modul --</option>
                            <option value="Taska">Taska</option>
                            <option value="Tadika">Tadika</option>
                            <option value="KAFA Care">KAFA Care</option>
                        </select>
                        <select id="filter_fee_frequency" onchange="filterFeeStructuresTable()" class="rounded-lg border-gray-300 text-xs py-1 px-2.5 focus:border-[#5452b5] focus:ring-[#5452b5]">
                            <option value="">-- Semua Kekerapan --</option>
                            <option value="Monthly">Bulanan</option>
                            <option value="Yearly">Tahunan</option>
                            <option value="One-Time">Sekali (One-Time)</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                                <th class="p-4 border-b border-gray-100">Modul</th>
                                <th class="p-4 border-b border-gray-100">Nama Yuran</th>
                                <th class="p-4 border-b border-gray-100">Kekerapan Bil</th>
                                <th class="p-4 border-b border-gray-100">Jumlah (RM)</th>
                                <th class="p-4 border-b border-gray-100">Status</th>
                                <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-[13px]">
                            <?php if ($fees_result && $fees_result->num_rows > 0): ?>
                                <?php while ($row = $fees_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50/30 transition fee-structure-row" data-module="<?php echo htmlspecialchars($row['module']); ?>" data-frequency="<?php echo htmlspecialchars($row['frequency']); ?>">
                                        <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($row['module']); ?></td>
                                        <td class="p-4 font-medium text-gray-700"><?php echo htmlspecialchars($row['fee_name']); ?></td>
                                        <td class="p-4 text-gray-600"><?php echo htmlspecialchars($row['frequency']); ?></td>
                                        <td class="p-4 font-bold text-red-600">RM <?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="p-4">
                                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $row['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                                <?php echo $row['is_active'] ? 'Aktif' : 'Nyahaktif'; ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-center">
                                            <a href="?toggle_active=<?php echo $row['id']; ?>"
                                               class="inline-flex items-center gap-1 px-3 py-1.5 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold rounded-lg text-xs transition">
                                                <span class="material-symbols-outlined text-[14px]">sync</span>
                                                <?php echo $row['is_active'] ? 'Nyahaktifkan' : 'Aktifkan'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-gray-400">
                                        Tiada struktur yuran dikonfigurasikan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- ═══════════ REJECT MODAL ═══════════ -->
<div class="modal-overlay" id="rejectModal">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-md overflow-hidden transform transition-all duration-300">
        <div class="bg-red-800 text-white p-5 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2 text-[15px]">
                <span class="material-symbols-outlined">block</span>
                Tolak Pembayaran Pelajar
            </h3>
            <button onclick="closeRejectModal()" class="text-white/60 hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="payment_id" id="reject_payment_id">
            <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-600 uppercase">Sebab Penolakan (Rejection Reason)</label>
                <textarea name="rejection_reason" required rows="3" placeholder="Sila nyatakan sebab, contoh: Jumlah bayaran kurang / Resit kabur..."
                          class="w-full rounded-lg border-gray-300 text-sm focus:border-red-500 focus:ring-red-500"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" name="reject_payment"
                        class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-sm transition">
                    Tolak Bayaran
                </button>
                <button type="button" onclick="closeRejectModal()"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════ RECEIPT VIEWER MODAL ═══════════ -->
<div class="modal-overlay" id="viewerModal">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-2xl overflow-hidden transform transition-all duration-300">
        <div class="bg-[#1a1c2e] text-white p-4 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2 text-[14px]">
                <span class="material-symbols-outlined">visibility</span>
                Bukti Pembayaran Ibu Bapa
            </h3>
            <button onclick="closeViewerModal()" class="text-white/60 hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 flex justify-center items-center bg-gray-100 overflow-auto max-h-[500px]">
            <img src="" id="receipt_viewer_img" alt="Bukti Resit" class="max-w-full h-auto rounded border shadow-sm">
        </div>
    </div>
</div>

<script>


// Switch tabs logic
let feeChart = null;

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabId).classList.remove('hidden');
    document.getElementById('btn-' + tabId).classList.add('active');
    
    // Resize atau init chart jika aktifkan tab tracking
    if (tabId === 'tab-tracking') {
        if (!feeChart) {
            initFeeChart();
        } else {
            feeChart.resize();
        }
    }
}

function initFeeChart() {
    const canvas = document.getElementById('feeStatusPieChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    feeChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Lunas / Paid', 'Semakan Admin', 'Belum Bayar', 'Tunggakan / Overdue'],
            datasets: [{
                data: [
                    <?php echo $stat_paid; ?>,
                    <?php echo $stat_pending_verify; ?>,
                    <?php echo $stat_pending; ?>,
                    <?php echo $stat_overdue; ?>
                ],
                backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444'],
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 8,
                        padding: 6,
                        font: { size: 9, family: "'Inter', sans-serif", weight: 'bold' },
                        color: '#64748B'
                    }
                },
                tooltip: {
                    backgroundColor: '#1E293B',
                    titleFont: { size: 10, family: "'Inter', sans-serif" },
                    bodyFont: { size: 9, family: "'Inter', sans-serif" },
                    padding: 6,
                    cornerRadius: 4,
                    callbacks: {
                        label: function(context) {
                            let val = context.parsed;
                            return ' ' + context.label + ': RM ' + new Intl.NumberFormat('ms-MY', { minimumFractionDigits: 2 }).format(val);
                        }
                    }
                }
            }
        }
    });
}

// Client-side filtering of tracking table
function filterTrackingTable() {
    const searchVal = document.getElementById('search_tracking_student').value.toLowerCase();
    const statusFilter = document.getElementById('filter_tracking_status').value;
    const moduleFilter = document.getElementById('filter_tracking_module').value;
    
    document.querySelectorAll('.tracking-row').forEach(row => {
        const studentName = row.getAttribute('data-student').toLowerCase();
        const parentName = row.getAttribute('data-parent').toLowerCase();
        const rowStatus = row.getAttribute('data-status');
        const rowModule = row.getAttribute('data-module');
        
        const matchSearch = !searchVal || studentName.includes(searchVal) || parentName.includes(searchVal);
        const matchStatus = !statusFilter || rowStatus === statusFilter;
        const matchModule = !moduleFilter || rowModule === moduleFilter;
        
        if (matchSearch && matchStatus && matchModule) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Receipt Viewer
function viewReceipt(filePath) {
    document.getElementById('receipt_viewer_img').src = filePath;
    document.getElementById('viewerModal').classList.add('active');
}
function closeViewerModal() {
    document.getElementById('viewerModal').classList.remove('active');
}

// Reject Modal
function openRejectModal(paymentId) {
    document.getElementById('reject_payment_id').value = paymentId;
    document.getElementById('rejectModal').classList.add('active');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
}

// Client-side filtering of fee structures
function filterFeeStructuresTable() {
    const moduleFilter = document.getElementById('filter_fee_module').value;
    const freqFilter = document.getElementById('filter_fee_frequency').value;
    
    document.querySelectorAll('.fee-structure-row').forEach(row => {
        const rowModule = row.getAttribute('data-module');
        const rowFreq = row.getAttribute('data-frequency');
        
        const matchModule = !moduleFilter || rowModule === moduleFilter;
        const matchFreq = !freqFilter || rowFreq === freqFilter;
        
        if (matchModule && matchFreq) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
</body>
</html>
