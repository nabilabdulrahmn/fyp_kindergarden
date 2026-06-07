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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_msg = "Token keselamatan tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';
        $payment_id = (int)($_POST['payment_id'] ?? 0);
        
        if ($action === 'verify_payment' && $payment_id) {
            // Get payment details
            $stmt_get = $conn->prepare("SELECT invoice_id, amount_paid, parent_id, status FROM payments WHERE id=?");
            $stmt_get->bind_param("i", $payment_id);
            $stmt_get->execute();
            $pay = $stmt_get->get_result()->fetch_assoc();
            
            if ($pay && $pay['status'] === 'Pending') {
                $stmt = $conn->prepare("UPDATE payments SET status='Verified', verified_by=?, verified_at=NOW() WHERE id=?");
                $admin_id = $_SESSION['user_id'];
                $stmt->bind_param("ii", $admin_id, $payment_id);
                if ($stmt->execute()) {
                    applyPaymentToInvoice($conn, $pay['invoice_id'], $pay['amount_paid']);
                    
                    notifyParent($conn, $pay['parent_id'], "Pembayaran Disahkan", "Pembayaran anda sebanyak RM " . number_format($pay['amount_paid'], 2) . " telah disahkan.", 'success', 'parent_payment_history.php');
                    logAction($conn, "Pengesahan bayaran ID: $payment_id", 'Success');
                    $success_msg = "Pembayaran berjaya disahkan.";
                }
            }
        } elseif ($action === 'reject_payment' && $payment_id) {
            $reason = $_POST['reject_reason'] ?? '';
            $stmt_get = $conn->prepare("SELECT parent_id, status FROM payments WHERE id=?");
            $stmt_get->bind_param("i", $payment_id);
            $stmt_get->execute();
            $pay = $stmt_get->get_result()->fetch_assoc();
            
            if ($pay && $pay['status'] === 'Pending') {
                $stmt = $conn->prepare("UPDATE payments SET status='Rejected' WHERE id=?");
                $stmt->bind_param("i", $payment_id);
                if ($stmt->execute()) {
                    notifyParent($conn, $pay['parent_id'], "Pembayaran Ditolak", "Pembayaran anda telah ditolak. Sebab: $reason", 'error', 'parent_payment_history.php');
                    logAction($conn, "Tolak bayaran ID: $payment_id. Sebab: $reason", 'Success');
                    $success_msg = "Pembayaran telah ditolak.";
                }
            }
        } elseif ($action === 'refund_payment' && $payment_id) {
            $refund_amount = (float)$_POST['refund_amount'];
            $reason = $_POST['refund_reason'];
            
            $stmt_get = $conn->prepare("SELECT * FROM payments WHERE id=?");
            $stmt_get->bind_param("i", $payment_id);
            $stmt_get->execute();
            $pay = $stmt_get->get_result()->fetch_assoc();
            
            if ($pay && $pay['status'] === 'Verified' && $pay['refund_status'] !== 'Refunded') {
                // If it's a Sandbox FPX payment, trigger gateway refund
                if (strpos($pay['transaction_ref'], 'SBX-') === 0) {
                    require_once 'includes/payment_gateway.php';
                    $gw = new SandboxPaymentGateway($conn);
                    $gw->initiateRefund($pay['transaction_ref'], $refund_amount, $reason);
                }
                
                $stmt = $conn->prepare("UPDATE payments SET refund_status='Refunded', refunded_amount=? WHERE id=?");
                $stmt->bind_param("di", $refund_amount, $payment_id);
                $stmt->execute();
                
                // Reverse payment on invoice
                // Increase balance_due, decrease paid_amount
                $stmt_inv = $conn->prepare("SELECT total_amount, paid_amount FROM invoices WHERE id=?");
                $stmt_inv->bind_param("i", $pay['invoice_id']);
                $stmt_inv->execute();
                $inv = $stmt_inv->get_result()->fetch_assoc();
                
                $new_paid = max(0, $inv['paid_amount'] - $refund_amount);
                $new_bal = max(0, $inv['total_amount'] - $new_paid);
                $new_status = ($new_bal <= 0) ? 'Paid' : (($new_paid > 0) ? 'Partial' : 'Sent'); // Simplified status reversal
                
                $stmt_upd_inv = $conn->prepare("UPDATE invoices SET paid_amount=?, balance_due=?, status=? WHERE id=?");
                $stmt_upd_inv->bind_param("ddsi", $new_paid, $new_bal, $new_status, $pay['invoice_id']);
                $stmt_upd_inv->execute();
                
                notifyParent($conn, $pay['parent_id'], "Bayaran Balik (Refund)", "Bayaran balik sebanyak RM " . number_format($refund_amount, 2) . " telah diproses. Rujukan: $reason", 'info', 'parent_payment_history.php');
                logAction($conn, "Refund bayaran ID: $payment_id, RM $refund_amount", 'Success');
                $success_msg = "Bayaran balik berjaya diproses.";
            }
        }
    }
}

// KPI
$kpi_collected = $conn->query("SELECT SUM(amount_paid) as s FROM payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND status='Verified'")->fetch_assoc()['s'] ?? 0;
$kpi_pending = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='Pending'")->fetch_assoc()['c'];
$kpi_refunds = $conn->query("SELECT COUNT(*) as c FROM payments WHERE refund_status='Refunded'")->fetch_assoc()['c'];

// Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$method_filter = $_GET['method'] ?? '';

$sql = "SELECT p.*, i.invoice_number, s.full_name as student_name 
        FROM payments p 
        LEFT JOIN invoices i ON p.invoice_id = i.id 
        LEFT JOIN students s ON i.student_id = s.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (p.receipt_number LIKE ? OR i.invoice_number LIKE ? OR s.full_name LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp;
    $types .= "sss";
}
if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($method_filter) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method_filter;
    $types .= "s";
}

$sql .= " ORDER BY p.created_at DESC";

// Pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$count_sql = str_replace("SELECT p.*, i.invoice_number, s.full_name as student_name", "SELECT COUNT(*) as total", $sql);
$stmt_count = $conn->prepare($count_sql);
if($types) $stmt_count->bind_param($types, ...$params);
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

$sql .= " LIMIT ?, ?";
$params[] = $offset; $params[] = $limit;
$types .= "ii";

$stmt = $conn->prepare($sql);
if($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$payments = $stmt->get_result();

renderAdminHeader('Pengurusan Pembayaran');
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6 flex justify-between">
    <div><span class="material-symbols-outlined align-middle mr-2">check_circle</span><?= htmlspecialchars($success_msg) ?></div>
    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-900"><span class="material-symbols-outlined">close</span></button>
</div>
<?php endif; ?>

<!-- KPI Row -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl p-5 border border-[#c7c5d4]/20 flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
            <span class="material-symbols-outlined">payments</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Kutipan Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-800">RM <?= number_format($kpi_collected, 2) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-[#c7c5d4]/20 flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
            <span class="material-symbols-outlined">pending_actions</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Menunggu Pengesahan</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($kpi_pending) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-[#c7c5d4]/20 flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center">
            <span class="material-symbols-outlined">currency_exchange</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Bayaran Balik (Refunds)</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($kpi_refunds) ?></p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari Resit/Invois/Pelajar..." class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20 w-full md:w-64">
            
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
                <option value="">Semua Status</option>
                <option value="Pending" <?= $status_filter=='Pending'?'selected':'' ?>>Menunggu</option>
                <option value="Verified" <?= $status_filter=='Verified'?'selected':'' ?>>Disahkan</option>
                <option value="Rejected" <?= $status_filter=='Rejected'?'selected':'' ?>>Ditolak</option>
            </select>

            <select name="method" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
                <option value="">Semua Kaedah</option>
                <option value="FPX" <?= $method_filter=='FPX'?'selected':'' ?>>FPX</option>
                <option value="Cash" <?= $method_filter=='Cash'?'selected':'' ?>>Tunai</option>
                <option value="Manual Transfer" <?= $method_filter=='Manual Transfer'?'selected':'' ?>>Pindahan Manual</option>
            </select>
            
            <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors">Tapis</button>
            <?php if($search || $status_filter || $method_filter): ?>
                <a href="admin_payments.php" class="text-gray-500 hover:text-gray-700 text-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                <tr>
                    <th class="px-4 py-3 font-medium">Tarikh & Resit</th>
                    <th class="px-4 py-3 font-medium">Invois & Pelajar</th>
                    <th class="px-4 py-3 font-medium">Kaedah & Rujukan</th>
                    <th class="px-4 py-3 font-medium text-right">Jumlah (RM)</th>
                    <th class="px-4 py-3 font-medium text-center">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($payments->num_rows > 0): ?>
                    <?php while ($pay = $payments->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50/50 <?= $pay['status']=='Pending' ? 'bg-amber-50/30' : '' ?>">
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-800"><?= date('d/m/Y H:i', strtotime($pay['payment_date'])) ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($pay['receipt_number'] ?: 'Tiada Resit') ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <a href="admin_invoice_detail.php?id=<?= $pay['invoice_id'] ?>" class="text-[#333093] hover:underline font-medium block">
                                    <?= htmlspecialchars($pay['invoice_number'] ?: 'Tiada Invois') ?>
                                </a>
                                <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($pay['student_name'] ?: '-') ?></div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($pay['payment_method']) ?></div>
                                <div class="text-xs text-gray-500 mt-1" title="Gateway Ref / Ref">
                                    <?= htmlspecialchars($pay['gateway'] ?: '') ?> 
                                    <?= htmlspecialchars($pay['transaction_ref'] ?: $pay['payment_reference']) ?>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="font-semibold text-gray-800"><?= number_format($pay['amount_paid'], 2) ?></div>
                                <?php if($pay['refund_status'] == 'Refunded'): ?>
                                    <div class="text-[10px] text-purple-600 font-bold mt-1 uppercase">Refunded</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <?php if ($pay['status'] == 'Verified'): ?>
                                    <span class="inline-flex px-2.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">Disahkan</span>
                                <?php elseif ($pay['status'] == 'Pending'): ?>
                                    <span class="inline-flex px-2.5 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">Menunggu</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2.5 py-0.5 bg-red-100 text-red-700 rounded-full text-xs font-semibold">Ditolak</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-right space-x-2">
                                <?php if ($pay['status'] == 'Pending'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Sahkan pembayaran ini?')">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="verify_payment">
                                        <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                        <button type="submit" class="text-emerald-600 hover:text-emerald-800 font-medium text-sm border border-emerald-200 hover:bg-emerald-50 px-2 py-1 rounded">Sahkan</button>
                                    </form>
                                    <button onclick="openRejectModal(<?= $pay['id'] ?>)" class="text-red-600 hover:text-red-800 font-medium text-sm border border-red-200 hover:bg-red-50 px-2 py-1 rounded">Tolak</button>
                                <?php elseif ($pay['status'] == 'Verified' && $pay['refund_status'] != 'Refunded'): ?>
                                    <button onclick="openRefundModal(<?= $pay['id'] ?>, <?= $pay['amount_paid'] ?>)" class="text-purple-600 hover:text-purple-800 font-medium text-sm border border-purple-200 hover:bg-purple-50 px-2 py-1 rounded">Refund</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="px-4 py-12 text-center text-gray-500">Tiada rekod pembayaran ditemui.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-500">Halaman <?= $page ?> daripada <?= $total_pages ?></div>
        <div class="flex gap-1">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <?php 
                $params = $_GET;
                $params['p'] = $i;
                $url = '?' . http_build_query($params);
                $active = ($i == $page) ? 'bg-[#333093] text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-300';
                ?>
                <a href="<?= $url ?>" class="px-3 py-1 rounded text-sm <?= $active ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-bold text-red-800 flex items-center gap-2"><span class="material-symbols-outlined text-red-600">cancel</span> Tolak Pembayaran</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject_payment">
            <input type="hidden" name="payment_id" id="rejectPaymentId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sebab Penolakan</label>
                <textarea name="reject_reason" required rows="3" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 sm:text-sm" placeholder="Cth: Resit tidak jelas, jumlah tidak tepat..."></textarea>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-sm">Sahkan Penolakan</button>
            </div>
        </form>
    </div>
</div>

<!-- Refund Modal -->
<div id="refundModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRefundModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-purple-50">
            <h3 class="text-lg font-bold text-purple-800 flex items-center gap-2"><span class="material-symbols-outlined text-purple-600">currency_exchange</span> Bayaran Balik</h3>
            <button onclick="closeRefundModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="refund_payment">
            <input type="hidden" name="payment_id" id="refundPaymentId" value="">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Refund (RM)</label>
                    <input type="number" step="0.01" name="refund_amount" id="refundAmount" required class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring focus:ring-purple-200 sm:text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sebab Refund</label>
                    <textarea name="refund_reason" required rows="2" class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring focus:ring-purple-200 sm:text-sm" placeholder="Nyatakan sebab..."></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRefundModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-sm">Sahkan Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(id) {
    document.getElementById('rejectPaymentId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
function openRefundModal(id, maxAmt) {
    document.getElementById('refundPaymentId').value = id;
    document.getElementById('refundAmount').value = maxAmt;
    document.getElementById('refundAmount').max = maxAmt;
    document.getElementById('refundModal').classList.remove('hidden');
}
function closeRefundModal() {
    document.getElementById('refundModal').classList.add('hidden');
}
</script>

<?php renderAdminFooter(); ?>
