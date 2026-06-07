<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/csrf_helper.php';
require_once 'includes/log_helper.php';
require_once 'includes/notification_helper.php';
require_once 'includes/invoice_helper.php';
require_once 'includes/admin_layout.php';

sahkan_peranan('admin');

$success_msg = '';
$error_msg = '';

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$invoice_id) {
    header("Location: admin_invoices.php");
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_msg = "Token keselamatan tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_invoice') {
            $stmt = $conn->prepare("UPDATE invoices SET status='Sent' WHERE id=?");
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute()) {
                $success_msg = "Invois berjaya dihantar kepada Ibu Bapa.";
                
                // Get parent ID
                $stmt2 = $conn->prepare("SELECT parent_id, invoice_number FROM invoices WHERE id=?");
                $stmt2->bind_param("i", $invoice_id);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                if ($row = $res2->fetch_assoc()) {
                    notifyParent($conn, $row['parent_id'], "Invois Baru Dikeluarkan", "Satu invois baru ({$row['invoice_number']}) telah dikeluarkan untuk anak anda. Sila semak portal untuk maklumat lanjut.", 'info', "parent_invoices.php");
                }
                logAction($conn, "Hantar invois ID: $invoice_id", 'Success');
            }
        } elseif ($action === 'void_invoice') {
            $reason = $_POST['void_reason'] ?? 'Tiada alasan';
            $stmt = $conn->prepare("UPDATE invoices SET status='Void' WHERE id=?");
            $stmt->bind_param("i", $invoice_id);
            if ($stmt->execute()) {
                $success_msg = "Invois berjaya dibatalkan.";
                logAction($conn, "Batal invois ID: $invoice_id. Sebab: $reason", 'Success');
            }
        } elseif ($action === 'manual_payment') {
            $amount = (float)$_POST['amount'];
            $method = $_POST['payment_method'];
            $ref = $_POST['reference'];
            $date = $_POST['payment_date'];
            $notes = $_POST['notes'];
            
            // Get parent id
            $stmt_p = $conn->prepare("SELECT parent_id FROM invoices WHERE id=?");
            $stmt_p->bind_param("i", $invoice_id);
            $stmt_p->execute();
            $p_id = $stmt_p->get_result()->fetch_assoc()['parent_id'];
            
            $receipt_num = generateReceiptNumber($conn);
            
            $stmt = $conn->prepare("INSERT INTO payments (invoice_id, parent_id, amount_paid, payment_method, payment_reference, payment_date, verified_by, verified_at, status, receipt_number, gateway) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'Verified', ?, 'Manual')");
            $admin_id = $_SESSION['user_id'];
            $stmt->bind_param("iidsssiss", $invoice_id, $p_id, $amount, $method, $ref, $date, $admin_id, $receipt_num);
            
            if ($stmt->execute()) {
                $apply = applyPaymentToInvoice($conn, $invoice_id, $amount);
                $success_msg = "Pembayaran manual berjaya direkod. Resit: $receipt_num. Baki terkini: RM " . number_format($apply['balance'], 2);
                
                notifyParent($conn, $p_id, "Pembayaran Diterima", "Pembayaran sebanyak RM " . number_format($amount, 2) . " telah diterima. No Resit: $receipt_num", 'success', 'parent_payment_history.php');
                logAction($conn, "Rekod bayaran manual $receipt_num untuk invois ID: $invoice_id", 'Success');
            } else {
                $error_msg = "Ralat merekod pembayaran.";
            }
        }
    }
}

// Query Invoice details
$sql = "SELECT i.*, s.full_name as student_name, s.mykid_number, s.module, c.class_name, 
        p.full_name as parent_name, p.phone_number, p.address, u.email 
        FROM invoices i 
        JOIN students s ON i.student_id = s.id 
        LEFT JOIN student_classes sc ON s.id = sc.student_id
        LEFT JOIN classes c ON sc.class_id = c.id
        JOIN parents p ON i.parent_id = p.id 
        JOIN users u ON p.user_id = u.id
        WHERE i.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $invoice_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("Location: admin_invoices.php");
    exit;
}
$invoice = $res->fetch_assoc();

// Query Line Items
$stmt_li = $conn->prepare("SELECT * FROM invoice_line_items WHERE invoice_id = ?");
$stmt_li->bind_param("i", $invoice_id);
$stmt_li->execute();
$line_items = $stmt_li->get_result();

// Query Payments
$stmt_pay = $conn->prepare("SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC");
$stmt_pay->bind_param("i", $invoice_id);
$stmt_pay->execute();
$payments = $stmt_pay->get_result();

function statusBadge($status) {
    $map = [
        'Draft' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-600">Draf</span>',
        'Sent' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">Dihantar</span>',
        'Paid' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-emerald-100 text-emerald-700">Telah Bayar</span>',
        'Partial' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">Separa</span>',
        'Overdue' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-700">Tertunggak</span>',
        'Void' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-400 line-through">Batal</span>',
        'Refunded' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-purple-100 text-purple-700">Dipulangkan</span>',
        'Pending' => '<span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-amber-100 text-amber-700">Menunggu</span>'
    ];
    return $map[$status] ?? $status;
}

renderAdminHeader('Butiran Invois — ' . $invoice['invoice_number']);
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6 flex justify-between items-center">
    <div><span class="material-symbols-outlined align-middle mr-2">check_circle</span><?= htmlspecialchars($success_msg) ?></div>
    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-900"><span class="material-symbols-outlined">close</span></button>
</div>
<?php endif; ?>

<!-- 1. HEADER SECTION -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <div class="flex items-center gap-3 mb-2">
            <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($invoice['invoice_number']) ?></h1>
            <?= statusBadge($invoice['status']) ?>
        </div>
        <div class="text-sm text-gray-500 flex items-center gap-4">
            <span>Dicipta: <?= date('d/m/Y', strtotime($invoice['issued_date'] ?? $invoice['created_at'])) ?></span>
            <span>Tarikh Akhir: <strong class="<?= (strtotime($invoice['due_date']) < time() && !in_array($invoice['status'], ['Paid','Void'])) ? 'text-red-500' : 'text-gray-800' ?>"><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></strong></span>
        </div>
    </div>
    
    <div class="flex flex-wrap items-center gap-2">
        <a href="#" onclick="window.print()" class="border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-4 py-2 rounded-lg font-medium transition-all text-sm flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">print</span> Cetak
        </a>
        
        <?php if (in_array($invoice['status'], ['Draft', 'Sent', 'Partial', 'Overdue'])): ?>
            <button onclick="document.getElementById('paymentModal').classList.remove('hidden')" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium transition-all text-sm flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[18px]">payments</span> Rekod Bayaran
            </button>
        <?php endif; ?>
        
        <?php if ($invoice['status'] === 'Draft'): ?>
            <form method="POST" class="inline" onsubmit="return confirm('Hantar invois ini kepada Ibu Bapa? Mereka akan menerima notifikasi.')">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="send_invoice">
                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition-all text-sm flex items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">send</span> Hantar Invois
                </button>
            </form>
        <?php endif; ?>
        
        <?php if (in_array($invoice['status'], ['Draft', 'Sent', 'Overdue'])): ?>
            <button onclick="document.getElementById('voidModal').classList.remove('hidden')" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-all text-sm flex items-center gap-1 shadow-sm">
                <span class="material-symbols-outlined text-[18px]">cancel</span> Batal
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- 2. BILL-TO SECTION -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">Maklumat Pelajar</h3>
        <div class="space-y-2 text-sm">
            <p><span class="text-gray-500 w-24 inline-block">Nama:</span> <strong class="text-gray-800"><?= htmlspecialchars($invoice['student_name']) ?></strong></p>
            <p><span class="text-gray-500 w-24 inline-block">MyKid:</span> <span class="text-gray-800"><?= htmlspecialchars($invoice['mykid_number']) ?></span></p>
            <p><span class="text-gray-500 w-24 inline-block">Modul:</span> <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600"><?= htmlspecialchars($invoice['module']) ?></span></p>
            <p><span class="text-gray-500 w-24 inline-block">Kelas:</span> <span class="text-gray-800"><?= htmlspecialchars($invoice['class_name'] ?? 'Tiada') ?></span></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">Ditagih Kepada</h3>
        <div class="space-y-2 text-sm">
            <p><span class="text-gray-500 w-24 inline-block">Nama:</span> <strong class="text-gray-800"><?= htmlspecialchars($invoice['parent_name']) ?></strong></p>
            <p><span class="text-gray-500 w-24 inline-block">No. Telefon:</span> <span class="text-gray-800"><?= htmlspecialchars($invoice['phone_number']) ?></span></p>
            <p><span class="text-gray-500 w-24 inline-block">E-mel:</span> <span class="text-gray-800"><?= htmlspecialchars($invoice['email']) ?></span></p>
            <p><span class="text-gray-500 w-24 inline-block align-top">Alamat:</span> <span class="text-gray-800 inline-block w-48"><?= nl2br(htmlspecialchars($invoice['address'])) ?></span></p>
        </div>
    </div>
</div>

<!-- 3. LINE ITEMS -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
        <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">list_alt</span> Butiran Caj
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-6 py-3 font-medium">Keterangan</th>
                    <th class="px-6 py-3 font-medium text-center">Kuantiti</th>
                    <th class="px-6 py-3 font-medium text-right">Harga Seunit (RM)</th>
                    <th class="px-6 py-3 font-medium text-right">Diskaun (%)</th>
                    <th class="px-6 py-3 font-medium text-right">Jumlah (RM)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php while ($item = $line_items->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50/50">
                    <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($item['description']) ?></td>
                    <td class="px-6 py-4 text-center"><?= $item['quantity'] ?></td>
                    <td class="px-6 py-4 text-right"><?= number_format($item['unit_price'], 2) ?></td>
                    <td class="px-6 py-4 text-right"><?= number_format($item['discount_pct'], 2) ?>%</td>
                    <td class="px-6 py-4 text-right font-medium text-gray-800"><?= number_format($item['line_total'], 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <div class="bg-[#f7f9fb] border-t border-gray-200 p-6 flex flex-col items-end space-y-2 text-sm">
        <div class="w-64 flex justify-between text-gray-600">
            <span>Subjumlah:</span>
            <span>RM <?= number_format($invoice['subtotal'], 2) ?></span>
        </div>
        <div class="w-64 flex justify-between text-gray-600">
            <span>Diskaun:</span>
            <span>RM <?= number_format($invoice['discount_amount'], 2) ?></span>
        </div>
        <div class="w-64 flex justify-between font-bold text-lg text-gray-800 pt-2 border-t border-gray-200">
            <span>JUMLAH BESAR:</span>
            <span>RM <?= number_format($invoice['total_amount'], 2) ?></span>
        </div>
        <div class="w-64 flex justify-between text-emerald-600 font-medium">
            <span>Telah Dibayar:</span>
            <span>RM <?= number_format($invoice['paid_amount'], 2) ?></span>
        </div>
        <div class="w-64 flex justify-between font-bold text-lg text-red-600 pt-2 border-t border-gray-200">
            <span>BAKI:</span>
            <span>RM <?= number_format($invoice['balance_due'], 2) ?></span>
        </div>
    </div>
</div>

<!-- 4. PAYMENT HISTORY -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
        <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">history</span> Sejarah Pembayaran
        </h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-6 py-3 font-medium">No. Resit</th>
                    <th class="px-6 py-3 font-medium">Tarikh</th>
                    <th class="px-6 py-3 font-medium text-right">Jumlah (RM)</th>
                    <th class="px-6 py-3 font-medium">Kaedah</th>
                    <th class="px-6 py-3 font-medium">Rujukan</th>
                    <th class="px-6 py-3 font-medium text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($payments->num_rows > 0): ?>
                    <?php while ($pay = $payments->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($pay['receipt_number']) ?></td>
                        <td class="px-6 py-4"><?= date('d/m/Y H:i', strtotime($pay['payment_date'])) ?></td>
                        <td class="px-6 py-4 text-right font-medium text-gray-800"><?= number_format($pay['amount_paid'], 2) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($pay['payment_method']) ?></td>
                        <td class="px-6 py-4 text-xs"><?= htmlspecialchars($pay['payment_reference'] ?? '-') ?></td>
                        <td class="px-6 py-4 text-center">
                            <?php if ($pay['status'] == 'Verified'): ?>
                                <span class="inline-flex px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-xs font-medium">Sah</span>
                            <?php elseif ($pay['status'] == 'Pending'): ?>
                                <span class="inline-flex px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs font-medium">Menunggu</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs font-medium">Ditolak</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Tiada rekod pembayaran untuk invois ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manual Payment Modal -->
<div id="paymentModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('paymentModal').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#f7f9fb]">
            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><span class="material-symbols-outlined text-[#333093]">payments</span> Rekod Bayaran Manual</h3>
            <button onclick="document.getElementById('paymentModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="manual_payment">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Bayaran (RM)</label>
                    <input type="number" step="0.01" max="<?= $invoice['balance_due'] ?>" name="amount" value="<?= $invoice['balance_due'] ?>" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kaedah Pembayaran</label>
                    <select name="payment_method" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                        <option value="Cash">Tunai</option>
                        <option value="Manual Transfer">Pindahan Manual Bank</option>
                        <option value="Cheque">Cek</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Rujukan (Pilihan)</label>
                    <input type="text" name="reference" placeholder="Cth: No. Resit / No. Cek" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tarikh Bayaran</label>
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                    <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('paymentModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Batal</button>
                <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm">Simpan Bayaran</button>
            </div>
        </form>
    </div>
</div>

<!-- Void Modal -->
<div id="voidModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('voidModal').classList.add('hidden')"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-bold text-red-800 flex items-center gap-2"><span class="material-symbols-outlined text-red-600">cancel</span> Batal Invois</h3>
            <button onclick="document.getElementById('voidModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="void_invoice">
            <p class="text-sm text-gray-600 mb-4">Adakah anda pasti mahu membatalkan invois ini? Tindakan ini tidak boleh diubah.</p>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sebab Pembatalan</label>
                <textarea name="void_reason" required rows="3" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 sm:text-sm" placeholder="Sila nyatakan sebab..."></textarea>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="document.getElementById('voidModal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Tutup</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm">Sahkan Pembatalan</button>
            </div>
        </form>
    </div>
</div>

<?php renderAdminFooter(); ?>
