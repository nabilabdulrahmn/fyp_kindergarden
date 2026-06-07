<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/parent_layout.php';

sahkan_peranan('parent');
$parent_id = dapatkan_parent_id($conn);

// Fetch Payments
$sql = "SELECT p.*, i.invoice_number, s.full_name as student_name 
        FROM payments p 
        LEFT JOIN invoices i ON p.invoice_id = i.id 
        LEFT JOIN students s ON i.student_id = s.id 
        WHERE p.parent_id = ?
        ORDER BY p.created_at DESC";

// Filter status
$status_filter = $_GET['status'] ?? '';
if ($status_filter) {
    $sql = "SELECT p.*, i.invoice_number, s.full_name as student_name 
            FROM payments p 
            LEFT JOIN invoices i ON p.invoice_id = i.id 
            LEFT JOIN students s ON i.student_id = s.id 
            WHERE p.parent_id = ? AND p.status = ? 
            ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $parent_id, $status_filter);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $parent_id);
}

$stmt->execute();
$payments = $stmt->get_result();

function statusBadge($status, $refund = null) {
    if ($refund === 'Refunded') {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700">DIPULANGKAN</span>';
    }
    
    $map = [
        'Pending' => '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">MENUNGGU PENGESAHAN</span>',
        'Verified' => '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">BERJAYA</span>',
        'Rejected' => '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">DITOLAK</span>'
    ];
    return $map[$status] ?? $status;
}

renderParentHeader('Sejarah Pembayaran');
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex gap-2">
        <a href="?status=" class="<?= !$status_filter ? 'bg-[#333093] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' ?> px-4 py-2 rounded-full text-sm font-semibold transition-colors shadow-sm">Semua</a>
        <a href="?status=Verified" class="<?= $status_filter=='Verified' ? 'bg-[#333093] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' ?> px-4 py-2 rounded-full text-sm font-semibold transition-colors shadow-sm">Berjaya</a>
        <a href="?status=Pending" class="<?= $status_filter=='Pending' ? 'bg-[#333093] text-white' : 'bg-white text-gray-600 border border-gray-300 hover:bg-gray-50' ?> px-4 py-2 rounded-full text-sm font-semibold transition-colors shadow-sm">Menunggu</a>
    </div>
    
    <!-- PDF Export (dummy action for now) -->
    <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg font-bold transition-colors text-sm flex items-center gap-2 shadow-sm">
        <span class="material-symbols-outlined text-[18px]">download</span> Muat Turun Penyata
    </button>
</div>

<div class="space-y-4">
    <?php if ($payments->num_rows > 0): ?>
        <?php while ($pay = $payments->fetch_assoc()): ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden hover:shadow-md transition-shadow">
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                
                <div class="flex items-start gap-4">
                    <!-- Icon based on method -->
                    <div class="w-12 h-12 rounded-full shrink-0 flex items-center justify-center <?= $pay['payment_method'] == 'FPX' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' ?>">
                        <span class="material-symbols-outlined"><?= $pay['payment_method'] == 'FPX' ? 'account_balance' : 'payments' ?></span>
                    </div>
                    
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-gray-800">
                                <?= $pay['invoice_number'] ? 'Bayaran Invois ' . htmlspecialchars($pay['invoice_number']) : 'Bayaran Pendaftaran' ?>
                            </h3>
                            <?= statusBadge($pay['status'], $pay['refund_status']) ?>
                        </div>
                        <p class="text-xs text-gray-500 mb-2"><?= htmlspecialchars($pay['student_name'] ?: 'Pelbagai') ?></p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 font-medium">
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">calendar_today</span> <?= date('d M Y, h:i A', strtotime($pay['payment_date'])) ?></span>
                            <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">tag</span> Ref: <?= htmlspecialchars($pay['payment_reference'] ?: $pay['transaction_ref'] ?: '-') ?></span>
                            <?php if($pay['receipt_number']): ?>
                                <span class="flex items-center gap-1 text-emerald-600"><span class="material-symbols-outlined text-[14px]">receipt</span> Resit: <?= htmlspecialchars($pay['receipt_number']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-left sm:text-right flex flex-row sm:flex-col justify-between sm:justify-center items-center sm:items-end border-t sm:border-t-0 pt-3 sm:pt-0 border-gray-100">
                    <p class="text-xl font-black text-[#333093] mb-0 sm:mb-2">RM <?= number_format($pay['amount_paid'], 2) ?></p>
                    
                    <?php if ($pay['status'] == 'Verified' && $pay['receipt_number']): ?>
                        <button onclick="alert('Papar butiran resit PDF akan dimuat turun.')" class="text-xs font-bold text-[#333093] hover:underline flex items-center gap-1 bg-[#f0f4ff] px-3 py-1.5 rounded-lg border border-[#333093]/20">
                            <span class="material-symbols-outlined text-[14px]">receipt_long</span> Lihat Resit
                        </button>
                    <?php endif; ?>
                </div>
                
            </div>
            
            <?php if ($pay['status'] == 'Rejected'): ?>
            <div class="bg-red-50 border-t border-red-100 p-3 text-xs text-red-700 flex items-start gap-2">
                <span class="material-symbols-outlined text-[16px] shrink-0">info</span>
                <p><strong>Sebab Ditolak:</strong> Pembayaran ini telah ditolak oleh admin. Sila buat pembayaran semula atau hubungi pihak pengurusan jika anda rasa ini satu kesilapan.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($pay['refund_status'] == 'Refunded'): ?>
            <div class="bg-purple-50 border-t border-purple-100 p-3 text-xs text-purple-700 flex items-start gap-2">
                <span class="material-symbols-outlined text-[16px] shrink-0">currency_exchange</span>
                <p><strong>Bayaran Balik (Refund):</strong> RM <?= number_format($pay['refunded_amount'], 2) ?> telah dipulangkan untuk transaksi ini.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-dashed border-gray-300 p-10 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-4">
                <span class="material-symbols-outlined text-[32px]">history</span>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-1">Tiada Rekod</h3>
            <p class="text-gray-500 text-sm">Anda belum membuat sebarang pembayaran lagi.</p>
        </div>
    <?php endif; ?>
</div>

<?php renderParentFooter(); ?>
