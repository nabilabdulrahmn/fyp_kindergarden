<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/parent_layout.php';

sahkan_peranan('parent');
$parent_id = dapatkan_parent_id($conn);

// Tab Filter
$tab = $_GET['tab'] ?? 'semua';

// Fetch Invoices
$sql = "SELECT i.*, s.full_name as student_name, s.module 
        FROM invoices i 
        JOIN students s ON i.student_id = s.id 
        WHERE i.parent_id = ?";

if ($tab === 'belum_bayar') {
    $sql .= " AND i.status IN ('Sent', 'Partial')";
} elseif ($tab === 'tertunggak') {
    $sql .= " AND i.status = 'Overdue'";
} elseif ($tab === 'telah_bayar') {
    $sql .= " AND i.status = 'Paid'";
}
$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$invoices = $stmt->get_result();

// Summary Stats
$stmt_sum = $conn->prepare("SELECT SUM(balance_due) as s FROM invoices WHERE parent_id=? AND status IN ('Sent','Partial','Overdue')");
$stmt_sum->bind_param("i", $parent_id);
$stmt_sum->execute();
$total_due = $stmt_sum->get_result()->fetch_assoc()['s'] ?? 0;

$stmt_next = $conn->prepare("SELECT MIN(due_date) as d FROM invoices WHERE parent_id=? AND status IN ('Sent','Partial')");
$stmt_next->bind_param("i", $parent_id);
$stmt_next->execute();
$next_due = $stmt_next->get_result()->fetch_assoc()['d'];

function statusColor($status) {
    $map = [
        'Sent' => 'border-l-blue-500',
        'Partial' => 'border-l-amber-500',
        'Overdue' => 'border-l-red-500',
        'Paid' => 'border-l-emerald-500',
        'Refunded' => 'border-l-purple-500',
        'Void' => 'border-l-gray-300'
    ];
    return $map[$status] ?? 'border-l-gray-300';
}
function statusBadge($status) {
    $map = [
        'Sent' => '<span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded text-[10px] font-bold">BELUM BAYAR</span>',
        'Partial' => '<span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded text-[10px] font-bold">SEPARA</span>',
        'Overdue' => '<span class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-[10px] font-bold">TERTUNGGAK</span>',
        'Paid' => '<span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-bold">TELAH BAYAR</span>',
        'Refunded' => '<span class="bg-purple-100 text-purple-700 px-2 py-0.5 rounded text-[10px] font-bold">DIPULANGKAN</span>',
        'Void' => '<span class="bg-gray-100 text-gray-500 px-2 py-0.5 rounded text-[10px] font-bold">BATAL</span>'
    ];
    return $map[$status] ?? $status;
}

renderParentHeader('Invois Saya');
?>

<!-- Summary Cards (Mobile First) -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">account_balance_wallet</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Jumlah Belum Bayar</p>
            <p class="text-2xl font-bold <?= $total_due > 0 ? 'text-red-600' : 'text-emerald-600' ?>">RM <?= number_format($total_due, 2) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined">event</span>
        </div>
        <div>
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Tarikh Akhir Seterusnya</p>
            <p class="text-lg font-bold text-gray-800"><?= $next_due ? date('d M Y', strtotime($next_due)) : 'Tiada Invois' ?></p>
        </div>
    </div>
</div>

<?php if ($total_due > 0): ?>
<div class="mb-6">
    <a href="parent_payment.php" class="w-full sm:w-auto bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-3 rounded-xl font-bold transition-colors shadow-sm flex items-center justify-center sm:inline-flex gap-2 text-sm">
        <span class="material-symbols-outlined text-[20px]">payments</span> Bayar Semua
    </a>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex overflow-x-auto border-b border-gray-200 mb-6 pb-px hide-scrollbar">
    <a href="?tab=semua" class="<?= $tab=='semua' ? 'border-b-2 border-[#333093] text-[#333093] font-bold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 whitespace-nowrap transition-colors text-sm">Semua</a>
    <a href="?tab=belum_bayar" class="<?= $tab=='belum_bayar' ? 'border-b-2 border-[#333093] text-[#333093] font-bold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 whitespace-nowrap transition-colors text-sm">Belum Bayar</a>
    <a href="?tab=tertunggak" class="<?= $tab=='tertunggak' ? 'border-b-2 border-[#333093] text-[#333093] font-bold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 whitespace-nowrap transition-colors text-sm">Tertunggak</a>
    <a href="?tab=telah_bayar" class="<?= $tab=='telah_bayar' ? 'border-b-2 border-[#333093] text-[#333093] font-bold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 whitespace-nowrap transition-colors text-sm">Telah Bayar</a>
</div>

<!-- Invoice List -->
<div class="space-y-4">
    <?php if ($invoices->num_rows > 0): ?>
        <?php while ($inv = $invoices->fetch_assoc()): ?>
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden border-l-4 <?= statusColor($inv['status']) ?> hover:shadow-md transition-shadow">
            <div class="p-4 sm:p-5">
                <div class="flex justify-between items-start mb-3">
                    <div class="text-xs font-mono text-gray-500"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                    <?= statusBadge($inv['status']) ?>
                </div>
                
                <h3 class="font-bold text-gray-800 text-base mb-1 truncate"><?= htmlspecialchars($inv['student_name']) ?></h3>
                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($inv['module']) ?> &bull; <?= $inv['period_month'] ? date('M Y', strtotime($inv['period_month'].'-01')) : 'Yuran Semasa' ?></p>
                
                <div class="grid grid-cols-2 gap-4 text-sm bg-gray-50 p-3 rounded-lg mb-4">
                    <div>
                        <p class="text-gray-500 text-xs mb-0.5">Dikeluarkan</p>
                        <p class="font-semibold text-gray-800"><?= date('d/m/Y', strtotime($inv['issued_date'] ?? $inv['created_at'])) ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs mb-0.5">Tarikh Akhir</p>
                        <p class="font-semibold <?= (strtotime($inv['due_date']) < time() && $inv['status']!='Paid') ? 'text-red-600' : 'text-gray-800' ?>"><?= date('d/m/Y', strtotime($inv['due_date'])) ?></p>
                    </div>
                </div>
                
                <div class="flex justify-between items-end border-t border-gray-100 pt-4">
                    <div class="space-y-1 text-sm">
                        <p class="text-gray-500 flex justify-between w-32"><span>Jumlah:</span> <span class="font-medium text-gray-800">RM <?= number_format($inv['total_amount'], 2) ?></span></p>
                        <p class="text-emerald-600 flex justify-between w-32"><span>Dibayar:</span> <span class="font-medium">RM <?= number_format($inv['paid_amount'], 2) ?></span></p>
                        <p class="font-bold flex justify-between w-32 pt-1 border-t border-gray-100 mt-1"><span>Baki:</span> <span class="<?= $inv['balance_due']>0 ? 'text-red-600' : 'text-emerald-600' ?>">RM <?= number_format($inv['balance_due'], 2) ?></span></p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-2">
                        <!-- Action to view detailed PDF or modal (simplified to a button for now) -->
                        <button onclick="alert('Papar butiran terperinci PDF akan dimuat turun.')" class="px-4 py-2 text-sm font-semibold text-[#333093] bg-[#f0f4ff] hover:bg-[#e0e7ff] rounded-lg transition-colors border border-[#333093]/20">
                            Lihat Invois
                        </button>
                        
                        <?php if (in_array($inv['status'], ['Sent', 'Partial', 'Overdue'])): ?>
                            <a href="parent_payment.php?invoice_id=<?= $inv['id'] ?>" class="px-4 py-2 text-sm font-semibold text-white bg-[#333093] hover:bg-[#5452b5] rounded-lg transition-colors text-center shadow-sm">
                                Bayar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white rounded-xl shadow-sm border border-dashed border-gray-300 p-10 flex flex-col items-center justify-center text-center">
            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-4">
                <span class="material-symbols-outlined text-[32px]">task</span>
            </div>
            <h3 class="text-lg font-bold text-gray-800 mb-1">Tiada Invois</h3>
            <p class="text-gray-500 text-sm">Anda tidak mempunyai invois dalam kategori ini.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<?php renderParentFooter(); ?>
