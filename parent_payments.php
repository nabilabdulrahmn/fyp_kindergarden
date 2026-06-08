<?php
// parent_payments.php
// Pembayaran & Invois - Paparan untuk Ibu Bapa
session_start();
require 'db.php';

// Kawalan akses: Hanya parent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$msg = '';

// Dapatkan parent_id & details
$sql_parent = "SELECT id, full_name FROM parents WHERE user_id = $user_id LIMIT 1";
$res_parent = $conn->query($sql_parent);
if (!$res_parent || $res_parent->num_rows == 0) {
    echo "<script>alert('Profil ibu bapa tidak dijumpai.'); window.location.href='home.php';</script>";
    exit();
}
$parent = $res_parent->fetch_assoc();
$parent_id = (int)$parent['id'];
$parent_name = htmlspecialchars($parent['full_name'] ?: $username);

// Proses bayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_payment'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $transaction_ref = $conn->real_escape_string($_POST['transaction_ref']);
    
    // Sahkan invois milik parent ini
    $verify = $conn->query("SELECT id, amount, status FROM invoices WHERE id = $invoice_id AND parent_id = $parent_id LIMIT 1");
    if ($verify && $verify->num_rows > 0) {
        $inv = $verify->fetch_assoc();
        if ($inv['status'] !== 'Paid') {
            
            // Sahkan tiada bayaran pending lain (halang double submission)
            $check_pending = $conn->query("SELECT id FROM payments WHERE invoice_id = $invoice_id AND status = 'Pending' LIMIT 1");
            if ($check_pending && $check_pending->num_rows > 0) {
                $msg = "
                <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
                    <span class='material-symbols-outlined'>warning</span>
                    <div>
                        <p class='font-semibold'>Pembayaran sedang diproses</p>
                        <p class='text-xs text-amber-700 mt-0.5'>Terdapat bukti pembayaran yang sedang disemak oleh admin untuk invois ini.</p>
                    </div>
                </div>";
            } else {
                // Upload resit
                $receipt = '';
                if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
                    $target_dir = "uploads/receipts/";
                    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
                    $receipt = $target_dir . time() . "_" . basename($_FILES['receipt']['name']);
                    move_uploaded_file($_FILES['receipt']['tmp_name'], $receipt);
                }
                
                $amount = $inv['amount'];
                $sql_pay = "INSERT INTO payments (invoice_id, parent_id, amount_paid, payment_method, transaction_ref, receipt_file, status) 
                            VALUES ($invoice_id, $parent_id, $amount, '$payment_method', '$transaction_ref', '$receipt', 'Pending')";
                if ($conn->query($sql_pay)) {
                    $msg = "
                    <div class='flex items-center gap-3 p-4 mb-4 text-green-800 bg-green-50 border-l-4 border-green-500 rounded-lg'>
                        <span class='material-symbols-outlined'>check_circle</span>
                        <div>
                            <p class='font-semibold'>Bukti pembayaran berjaya dihantar!</p>
                            <p class='text-xs text-green-700 mt-0.5'>Pihak pentadbiran akan membuat semakan dan mengesahkan pembayaran anda sebentar lagi.</p>
                        </div>
                    </div>";
                } else {
                    $msg = "
                    <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
                        <span class='material-symbols-outlined'>error</span>
                        <div>
                            <p class='font-semibold'>Ralat Sistem</p>
                            <p class='text-xs text-red-700 mt-0.5'>Gagal menyimpan rekod pembayaran: " . $conn->error . "</p>
                        </div>
                    </div>";
                }
            }
        } else {
            $msg = "
            <div class='flex items-center gap-3 p-4 mb-4 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-lg'>
                <span class='material-symbols-outlined'>warning</span>
                <p class='font-semibold'>Invois ini sudah dijelaskan sebelum ini.</p>
            </div>";
        }
    } else {
        $msg = "
        <div class='flex items-center gap-3 p-4 mb-4 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-lg'>
            <span class='material-symbols-outlined'>block</span>
            <p class='font-semibold'>Akses dinafikan. Invois tidak sah atau bukan milik anda.</p>
        </div>";
    }
}

// Ambil invois parent ini sahaja
$sql_invoices = "SELECT i.*, s.full_name AS student_name 
                 FROM invoices i 
                 INNER JOIN students s ON i.student_id = s.id 
                 WHERE i.parent_id = $parent_id 
                 ORDER BY i.created_at DESC";
$invoices = $conn->query($sql_invoices);

// Kira statistik & list
$total_pending = 0;
$total_paid = 0;
$total_overdue = 0;
$invoice_list = array();
if ($invoices) {
    while ($row = $invoices->fetch_assoc()) {
        $invoice_list[] = $row;
        if ($row['status'] == 'Pending') $total_pending += $row['amount'];
        if ($row['status'] == 'Paid') $total_paid += $row['amount'];
        if ($row['status'] == 'Overdue') $total_overdue += $row['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Pembayaran & Invois — Portal Ibu Bapa</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <style>
        .material-symbols-outlined { font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24; }
        body { min-height:100dvh; font-family:'Inter',sans-serif; }

        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
    </style>
</head>
<body class="bg-[#f7f9fb] text-[#191c1e] overflow-x-hidden">

<?php include 'sidebar_parent.php'; ?>

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
                <h1 class="text-[18px] font-semibold text-[#191c1e]">💳 Pembayaran &amp; Invois</h1>
                <p class="text-[11px] text-[#777583]">Urus invois dan buat pembayaran bagi anak-anak anda</p>
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="hidden sm:flex flex-col items-end">
                <span class="text-[13px] font-semibold text-[#191c1e]"><?php echo $parent_name; ?></span>
                <span class="text-[11px] font-bold text-[#3a78c9] bg-[#84b6f4]/20 px-2 py-0.5 rounded-full">Ibu Bapa / Penjaga</span>
            </div>
            <div class="w-10 h-10 rounded-full bg-[#3a78c9] flex items-center justify-center text-white font-bold select-none">
                <?php echo strtoupper(substr($username,0,1)); ?>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="pt-[90px] px-6 pb-8 max-w-[1440px] mx-auto">
        
        <?php echo $msg; ?>

        <!-- Stats Overview Row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-6">
            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">pending_actions</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Belum Bayar (Pending)</p>
                    <h3 class="text-[22px] font-bold text-amber-600">RM <?php echo number_format($total_pending, 2); ?></h3>
                </div>
            </div>
            
            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">priority_high</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Tertunggak (Overdue)</p>
                    <h3 class="text-[22px] font-bold text-red-600">RM <?php echo number_format($total_overdue, 2); ?></h3>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-[#c7c5d4]/20 shadow-sm flex items-center gap-4 hover:shadow-md transition">
                <div class="w-12 h-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                    <span class="material-symbols-outlined text-[28px]">check_circle</span>
                </div>
                <div>
                    <p class="text-[12px] text-gray-500 font-medium">Telah Dibayar (Paid)</p>
                    <h3 class="text-[22px] font-bold text-green-600">RM <?php echo number_format($total_paid, 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Invoices List Container -->
        <div class="bg-white rounded-xl border border-[#c7c5d4]/20 shadow-sm overflow-hidden mb-6">
            <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-bold text-gray-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-gray-500">receipt</span>
                    Senarai Invois Aktif
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100/70 text-gray-600 text-[11px] font-semibold uppercase tracking-wider">
                            <th class="p-4 border-b border-gray-100">No. Invois</th>
                            <th class="p-4 border-b border-gray-100">Anak</th>
                            <th class="p-4 border-b border-gray-100">Butiran Yuran</th>
                            <th class="p-4 border-b border-gray-100">Jumlah Bil</th>
                            <th class="p-4 border-b border-gray-100">Tarikh Dijana</th>
                            <th class="p-4 border-b border-gray-100">Status</th>
                            <th class="p-4 border-b border-gray-100 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-[13px]">
                        <?php if (count($invoice_list) > 0): ?>
                            <?php foreach ($invoice_list as $inv): ?>
                                <?php 
                                    $statusClass = '';
                                    if ($inv['status'] === 'Paid') {
                                        $statusClass = 'bg-green-100 text-green-700';
                                    } elseif ($inv['status'] === 'Overdue') {
                                        $statusClass = 'bg-red-100 text-red-700';
                                    } else {
                                        $statusClass = 'bg-amber-100 text-amber-700';
                                    }
                                ?>
                                <tr class="hover:bg-gray-50/40 transition">
                                    <td class="p-4 font-bold text-gray-800">
                                        <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                    </td>
                                    <td class="p-4 font-medium"><?php echo htmlspecialchars($inv['student_name']); ?></td>
                                    <td class="p-4 text-gray-600"><?php echo htmlspecialchars($inv['type'] ?: 'Yuran Pembelajaran'); ?></td>
                                    <td class="p-4 font-bold text-gray-800">RM <?php echo number_format($inv['amount'], 2); ?></td>
                                    <td class="p-4 text-gray-500"><?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></td>
                                    <td class="p-4">
                                        <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold <?php echo $statusClass; ?>">
                                            <?php echo $inv['status']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 flex justify-center gap-2">
                                        <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" target="_blank"
                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-lg text-xs transition">
                                            <span class="material-symbols-outlined text-[14px]">visibility</span> Lihat Bil
                                        </a>
                                        
                                        <?php if ($inv['status'] !== 'Paid'): ?>
                                            <!-- Check if there's already a pending payment request for this invoice -->
                                            <?php 
                                                $check_sql = "SELECT status, rejection_reason FROM payments WHERE invoice_id = {$inv['id']} AND parent_id = $parent_id ORDER BY id DESC LIMIT 1";
                                                $check_res = $conn->query($check_sql);
                                                $is_pending_verification = false;
                                                $is_rejected = false;
                                                $rejection_reason = '';
                                                if ($check_res && $check_res->num_rows > 0) {
                                                    $p_data = $check_res->fetch_assoc();
                                                    $p_status = $p_data['status'];
                                                    $rejection_reason = $p_data['rejection_reason'];
                                                    if ($p_status === 'Pending') {
                                                        $is_pending_verification = true;
                                                    } elseif ($p_status === 'Rejected') {
                                                        $is_rejected = true;
                                                    }
                                                }
                                            ?>
                                            <?php if ($is_pending_verification): ?>
                                                <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-50 text-amber-700 border border-amber-200 font-bold rounded-lg text-xs select-none">
                                                    <span class="material-symbols-outlined text-[14px] animate-spin">sync</span> Semakan Admin
                                                </span>
                                            <?php else: ?>
                                                <div class="flex flex-col items-center gap-1">
                                                    <button onclick="openPayModal(<?php echo $inv['id']; ?>, <?php echo $inv['amount']; ?>, '<?php echo htmlspecialchars($inv['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($inv['invoice_number'], ENT_QUOTES); ?>')"
                                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg text-xs transition shadow-sm">
                                                        <span class="material-symbols-outlined text-[14px]">credit_card</span> Bayar
                                                    </button>
                                                    <?php if ($is_rejected): ?>
                                                        <span class="text-[10px] text-red-600 font-bold text-center bg-red-50 border border-red-200 px-2 py-0.5 rounded mt-1 max-w-[150px] truncate" title="<?php echo htmlspecialchars($rejection_reason); ?>">
                                                            Ditolak: <?php echo htmlspecialchars($rejection_reason); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-50 text-green-700 border border-green-200 font-bold rounded-lg text-xs select-none">
                                                <span class="material-symbols-outlined text-[14px]">check_circle</span> Lunas
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-400">
                                    <span class="material-symbols-outlined text-[48px] opacity-35 block mb-2">receipt_long</span>
                                    Tiada invois direkodkan buat masa ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- ═══════════ PAYMENT MODAL ═══════════ -->
<div class="modal-overlay" id="payModal">
    <div class="bg-white rounded-xl shadow-xl w-11/12 max-w-lg overflow-hidden transform transition-all duration-300">
        <div class="bg-[#1a1c2e] text-white p-5 flex justify-between items-center">
            <h3 class="font-bold flex items-center gap-2 text-[16px]">
                <span class="material-symbols-outlined">payments</span>
                Hantar Bukti Pembayaran
            </h3>
            <button onclick="closePayModal()" class="text-white/60 hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="invoice_id" id="modal_invoice_id">
            
            <div class="bg-blue-50 p-4 border border-blue-100 rounded-lg text-[12px] text-blue-800 space-y-1">
                <p class="font-bold flex items-center gap-1 mb-1 text-[13px] text-blue-900">
                    <span class="material-symbols-outlined text-[16px]">account_balance</span> Akaun Penerima (Taska Centre)
                </p>
                <div class="flex justify-between">
                    <span>Nama Bank:</span> <strong class="font-semibold select-all">MAYBANK (MBB)</strong>
                </div>
                <div class="flex justify-between">
                    <span>No. Akaun:</span> <strong class="font-semibold select-all text-blue-900">5641-2345-6789</strong>
                </div>
                <div class="flex justify-between">
                    <span>Nama Penerima:</span> <strong class="font-semibold">TASKA CARE CENTRE</strong>
                </div>
            </div>

            <div class="p-3 bg-gray-50 border border-gray-100 rounded-lg text-sm text-gray-700 flex justify-between">
                <span>Invois / Pelajar:</span>
                <span id="modal_info" class="font-semibold text-gray-900"></span>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-600 uppercase">Kaedah Pembayaran</label>
                <select name="payment_method" required class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="Manual Transfer">Pindahan Manual (Bank-In/ATM)</option>
                    <option value="Online">Online Banking (Instant Transfer)</option>
                    <option value="FPX">FPX Portal</option>
                    <option value="Cash">Tunai (Cash di Taska)</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-600 uppercase">No. Rujukan Transaksi</label>
                <input type="text" name="transaction_ref" required placeholder="Cth: Ref 849302 atau Tarikh & Masa"
                       class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-600 uppercase">Muat Naik Resit (PNG, JPG, PDF)</label>
                <input type="file" name="receipt" required accept=".jpg,.jpeg,.png,.pdf"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-gray-300 rounded-lg p-1">
            </div>

            <div class="pt-4 flex gap-3">
                <button type="submit" name="make_payment"
                        class="flex-1 py-2.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg text-sm transition shadow-sm">
                    ✅ Hantar Pembayaran
                </button>
                <button type="button" onclick="closePayModal()"
                        class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-lg text-sm transition">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<script>


function openPayModal(invoiceId, amount, studentName, invoiceNumber) {
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_info').innerHTML = invoiceNumber + ' (' + studentName + ') - RM ' + parseFloat(amount).toFixed(2);
    document.getElementById('payModal').classList.add('active');
}
function closePayModal() {
    document.getElementById('payModal').classList.remove('active');
}
</script>
</body>
</html>
