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
        
        if ($action === 'mark_overdue') {
            $count = markOverdueInvoices($conn);
            $success_msg = "$count invois telah dikemaskini sebagai Tertunggak.";
            logAction($conn, "Tandakan invois tertunggak secara manual: $count", 'Success');
        } elseif ($action === 'send_reminder') {
            if (!empty($_POST['selected_invoices'])) {
                $ids = $_POST['selected_invoices'];
                $count = 0;
                foreach($ids as $id) {
                    $id = (int)$id;
                    $stmt = $conn->prepare("SELECT parent_id, invoice_number FROM invoices WHERE id=?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if($row = $res->fetch_assoc()) {
                        notifyParent($conn, $row['parent_id'], "Peringatan Invois Tertunggak", "Invois {$row['invoice_number']} anda sedang tertunggak. Sila buat pembayaran segera.", 'warning', 'parent_invoices.php');
                        $count++;
                    }
                }
                $success_msg = "Peringatan dihantar kepada $count ibu bapa.";
                logAction($conn, "Hantar peringatan pukal untuk $count invois", 'Success');
            }
        }
    }
}

// Search & Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$month_filter = $_GET['month'] ?? '';

// KPI Queries
$kpi_total = $conn->query("SELECT COUNT(*) as c FROM invoices WHERE MONTH(issued_date) = MONTH(CURDATE()) AND YEAR(issued_date) = YEAR(CURDATE())")->fetch_assoc()['c'];
$kpi_unpaid = $conn->query("SELECT SUM(balance_due) as s FROM invoices WHERE status IN ('Sent','Partial','Overdue')")->fetch_assoc()['s'] ?? 0;
$kpi_collected = $conn->query("SELECT SUM(paid_amount) as s FROM invoices WHERE MONTH(issued_date) = MONTH(CURDATE()) AND YEAR(issued_date) = YEAR(CURDATE())")->fetch_assoc()['s'] ?? 0;
$kpi_overdue = $conn->query("SELECT COUNT(*) as c FROM invoices WHERE status='Overdue'")->fetch_assoc()['c'];

// Main Query
$sql = "SELECT i.*, s.full_name as student_name, p.full_name as parent_name 
        FROM invoices i 
        JOIN students s ON i.student_id = s.id 
        JOIN parents p ON i.parent_id = p.id 
        WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $sql .= " AND (i.invoice_number LIKE ? OR s.full_name LIKE ? OR p.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param; $params[] = $search_param; $params[] = $search_param;
    $types .= "sss";
}
if ($status_filter) {
    $sql .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if ($month_filter) {
    $sql .= " AND i.period_month = ?";
    $params[] = $month_filter;
    $types .= "s";
}

$sql .= " ORDER BY i.created_at DESC";

// Pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$count_sql = str_replace("SELECT i.*, s.full_name as student_name, p.full_name as parent_name", "SELECT COUNT(*) as total", $sql);
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
$invoices = $stmt->get_result();

function statusBadge($status) {
    $map = [
        'Draft' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Draf</span>',
        'Sent' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Dihantar</span>',
        'Paid' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Telah Bayar</span>',
        'Partial' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Separa</span>',
        'Overdue' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Tertunggak</span>',
        'Void' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-400 line-through">Batal</span>',
        'Refunded' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Dipulangkan</span>',
        'Pending' => '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Menunggu</span>'
    ];
    return $map[$status] ?? $status;
}

renderAdminHeader('Pengurusan Invois');
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6">
    <span class="material-symbols-outlined align-middle mr-2">check_circle</span>
    <?= htmlspecialchars($success_msg) ?>
</div>
<?php endif; ?>

<!-- KPI Row -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-5 border border-[#c7c5d4]/20 flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
            <span class="material-symbols-outlined">receipt_long</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Invois Bulan Ini</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($kpi_total) ?></p>
        </div>
    </div>
    <div class="bg-white rounded-xl p-5 border border-[#c7c5d4]/20 flex items-center gap-4 shadow-sm">
        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
            <span class="material-symbols-outlined">account_balance_wallet</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Jumlah Belum Bayar</p>
            <p class="text-2xl font-bold text-gray-800">RM <?= number_format($kpi_unpaid, 2) ?></p>
        </div>
    </div>
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
        <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center">
            <span class="material-symbols-outlined">warning</span>
        </div>
        <div>
            <p class="text-sm text-gray-500 font-medium">Invois Tertunggak</p>
            <p class="text-2xl font-bold text-gray-800"><?= number_format($kpi_overdue) ?></p>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="flex border-b border-gray-200 mb-6">
    <a href="admin_invoices.php" class="<?= !$status_filter || $status_filter != 'Overdue' ? 'border-b-2 border-[#333093] text-[#333093] font-semibold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 cursor-pointer transition-colors">
        Semua Invois
    </a>
    <a href="admin_invoices.php?status=Overdue" class="<?= $status_filter == 'Overdue' ? 'border-b-2 border-[#333093] text-[#333093] font-semibold' : 'text-gray-500 hover:text-gray-700' ?> px-4 py-2 cursor-pointer transition-colors flex items-center gap-2">
        Tertunggak
        <?php if($kpi_overdue > 0): ?>
            <span class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full"><?= $kpi_overdue ?></span>
        <?php endif; ?>
    </a>
</div>

<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden mb-6">
    <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50 flex flex-col md:flex-row justify-between items-center gap-4">
        <form method="GET" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari No. Invois/Pelajar..." class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20 w-full md:w-64">
            
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
                <option value="">Semua Status</option>
                <option value="Draft" <?= $status_filter=='Draft'?'selected':'' ?>>Draf</option>
                <option value="Sent" <?= $status_filter=='Sent'?'selected':'' ?>>Dihantar</option>
                <option value="Partial" <?= $status_filter=='Partial'?'selected':'' ?>>Separa</option>
                <option value="Paid" <?= $status_filter=='Paid'?'selected':'' ?>>Telah Bayar</option>
                <option value="Overdue" <?= $status_filter=='Overdue'?'selected':'' ?>>Tertunggak</option>
            </select>

            <select name="month" class="rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
                <option value="">Semua Bulan</option>
                <?php
                for($i=1; $i<=12; $i++) {
                    $m = date('Y-m', mktime(0,0,0,$i,1,date('Y')));
                    $sel = ($month_filter == $m) ? 'selected' : '';
                    echo "<option value='$m' $sel>".date('M Y', strtotime($m."-01"))."</option>";
                }
                ?>
            </select>
            
            <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors">Tapis</button>
            <?php if($search || $status_filter || $month_filter): ?>
                <a href="admin_invoices.php" class="text-gray-500 hover:text-gray-700 text-sm">Reset</a>
            <?php endif; ?>
        </form>

        <div class="flex items-center gap-2">
            <form method="POST" class="inline" onsubmit="return confirm('Tandakan invois yang melepasi tarikh akhir sebagai tertunggak?')">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="mark_overdue">
                <button type="submit" class="border border-red-500 text-red-500 hover:bg-red-50 px-3 py-2 rounded-lg font-medium text-sm transition-colors">
                    Semak Tertunggak
                </button>
            </form>
        </div>
    </div>

    <form method="POST" id="bulkForm">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="send_reminder">
        
        <?php if ($status_filter == 'Overdue'): ?>
        <div class="bg-red-50/50 p-3 border-b border-red-100 flex items-center justify-between">
            <div class="text-sm text-red-700">Tindakan Pukal:</div>
            <button type="submit" onclick="return confirm('Hantar peringatan kepada semua invois yang dipilih?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded text-sm font-medium transition-colors">
                Hantar Peringatan E-mel/Notifikasi
            </button>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                    <tr>
                        <?php if ($status_filter == 'Overdue'): ?>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" onchange="toggleAll(this)" class="rounded text-[#333093] focus:ring-[#333093]">
                        </th>
                        <?php endif; ?>
                        <th class="px-4 py-3 font-medium">No. Invois</th>
                        <th class="px-4 py-3 font-medium">Pelajar & Penjaga</th>
                        <th class="px-4 py-3 font-medium text-center">Tempoh</th>
                        <th class="px-4 py-3 font-medium text-right">Jumlah (RM)</th>
                        <th class="px-4 py-3 font-medium text-right">Baki (RM)</th>
                        <th class="px-4 py-3 font-medium text-center">Status</th>
                        <th class="px-4 py-3 font-medium">Tarikh Akhir</th>
                        <?php if ($status_filter == 'Overdue'): ?>
                        <th class="px-4 py-3 font-medium text-center">Lewat</th>
                        <?php endif; ?>
                        <th class="px-4 py-3 font-medium text-right">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($invoices->num_rows > 0): ?>
                        <?php while ($inv = $invoices->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50/50">
                                <?php if ($status_filter == 'Overdue'): ?>
                                <td class="px-4 py-4">
                                    <input type="checkbox" name="selected_invoices[]" value="<?= $inv['id'] ?>" class="inv-checkbox rounded text-[#333093] focus:ring-[#333093]">
                                </td>
                                <?php endif; ?>
                                <td class="px-4 py-4 font-semibold text-gray-800">
                                    <?= htmlspecialchars($inv['invoice_number']) ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-medium text-gray-800"><?= htmlspecialchars($inv['student_name']) ?></div>
                                    <div class="text-xs text-gray-500">Ibu/Bapa: <?= htmlspecialchars($inv['parent_name']) ?></div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?= $inv['period_month'] ? date('M Y', strtotime($inv['period_month'].'-01')) : '-' ?>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <?= number_format($inv['total_amount'], 2) ?>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold <?= $inv['balance_due']>0 ? 'text-red-600' : 'text-emerald-600' ?>">
                                    <?= number_format($inv['balance_due'], 2) ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?= statusBadge($inv['status']) ?>
                                </td>
                                <td class="px-4 py-4 text-gray-500">
                                    <?= date('d/m/Y', strtotime($inv['due_date'])) ?>
                                </td>
                                <?php if ($status_filter == 'Overdue'): ?>
                                <td class="px-4 py-4 text-center">
                                    <?php 
                                    $diff = (strtotime(date('Y-m-d')) - strtotime($inv['due_date'])) / (60 * 60 * 24);
                                    echo "<span class='text-red-600 font-semibold'>" . max(0, floor($diff)) . " hari</span>";
                                    ?>
                                </td>
                                <?php endif; ?>
                                <td class="px-4 py-4 text-right">
                                    <a href="admin_invoice_detail.php?id=<?= $inv['id'] ?>" class="text-[#333093] hover:text-[#5452b5] text-sm font-medium">Lihat &rarr;</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= $status_filter=='Overdue' ? 10 : 8 ?>" class="px-4 py-12 text-center text-gray-500">Tiada invois ditemui.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50">
        <div class="text-sm text-gray-500">
            Halaman <?= $page ?> daripada <?= $total_pages ?>
        </div>
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

<script>
function toggleAll(source) {
    checkboxes = document.querySelectorAll('.inv-checkbox');
    for(var i=0, n=checkboxes.length;i<n;i++) {
        checkboxes[i].checked = source.checked;
    }
}
</script>

<?php renderAdminFooter(); ?>
