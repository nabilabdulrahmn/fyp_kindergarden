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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_msg = "Token keselamatan tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_fee' || $action === 'edit_fee') {
            $id = $_POST['fee_id'] ?? null;
            $module = $_POST['module'];
            $fee_name = $_POST['fee_name'];
            $amount = $_POST['amount'];
            $frequency = $_POST['frequency'];
            $sibling_discount_pct = $_POST['sibling_discount_pct'] ?? 0;
            $applicable_to = isset($_POST['applicable_to']) ? implode(',', $_POST['applicable_to']) : 'all';
            $valid_from = !empty($_POST['valid_from']) ? $_POST['valid_from'] : null;
            $valid_until = !empty($_POST['valid_until']) ? $_POST['valid_until'] : null;

            if ($action === 'add_fee') {
                $stmt = $conn->prepare("INSERT INTO fee_structures (module, fee_name, amount, frequency, sibling_discount_pct, applicable_to, valid_from, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdssdss", $module, $fee_name, $amount, $frequency, $sibling_discount_pct, $applicable_to, $valid_from, $valid_until);
                if ($stmt->execute()) {
                    $success_msg = "Struktur yuran berjaya ditambah.";
                    logAction($conn, "Tambah struktur yuran: $fee_name", 'Success');
                } else {
                    $error_msg = "Ralat menambah yuran.";
                }
            } else {
                $stmt = $conn->prepare("UPDATE fee_structures SET module=?, fee_name=?, amount=?, frequency=?, sibling_discount_pct=?, applicable_to=?, valid_from=?, valid_until=? WHERE id=?");
                $stmt->bind_param("ssdssdssi", $module, $fee_name, $amount, $frequency, $sibling_discount_pct, $applicable_to, $valid_from, $valid_until, $id);
                if ($stmt->execute()) {
                    $success_msg = "Struktur yuran berjaya dikemaskini.";
                    logAction($conn, "Kemaskini struktur yuran ID: $id", 'Success');
                } else {
                    $error_msg = "Ralat mengemaskini yuran.";
                }
            }
        } elseif ($action === 'toggle_fee') {
            $id = $_POST['fee_id'];
            $current_status = $_POST['current_status'];
            $new_status = $current_status == 1 ? 0 : 1;
            
            $stmt = $conn->prepare("UPDATE fee_structures SET is_active=? WHERE id=?");
            $stmt->bind_param("ii", $new_status, $id);
            if ($stmt->execute()) {
                $status_text = $new_status ? 'diaktifkan' : 'dinyahaktifkan';
                $success_msg = "Yuran berjaya $status_text.";
                logAction($conn, "Tukar status yuran ID: $id ke $new_status", 'Success');
            }
        } elseif ($action === 'clone_fee') {
            $id = $_POST['fee_id'];
            $stmt = $conn->prepare("INSERT INTO fee_structures (module, fee_name, amount, frequency, sibling_discount_pct, applicable_to, valid_from, valid_until) SELECT module, CONCAT(fee_name, ' (Salinan)'), amount, frequency, sibling_discount_pct, applicable_to, valid_from, valid_until FROM fee_structures WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success_msg = "Struktur yuran berjaya diklon.";
                logAction($conn, "Klon struktur yuran ID: $id", 'Success');
            }
        } elseif ($action === 'update_sibling_rules') {
            $child2 = $_POST['child_2_pct'] ?? 0;
            $child3 = $_POST['child_3_pct'] ?? 0;
            
            // Assuming IDs 1 and 2 exist from seed
            $stmt1 = $conn->prepare("UPDATE sibling_discount_rules SET discount_pct=? WHERE child_order=2");
            $stmt1->bind_param("d", $child2);
            $stmt1->execute();
            
            $stmt2 = $conn->prepare("UPDATE sibling_discount_rules SET discount_pct=? WHERE child_order=3");
            $stmt2->bind_param("d", $child3);
            $stmt2->execute();
            
            $success_msg = "Peraturan diskaun adik-beradik berjaya disimpan.";
            logAction($conn, "Kemaskini diskaun adik-beradik", 'Success');
            
        } elseif ($action === 'update_late_rules') {
            $grace = $_POST['grace_period'] ?? 7;
            $type = $_POST['late_fee_type'] ?? 'Fixed';
            $amount = $_POST['late_fee_amount'] ?? 10;
            $active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE late_payment_rules SET grace_period_days=?, late_fee_type=?, late_fee_amount=?, is_active=? WHERE id=1");
            $stmt->bind_param("isdi", $grace, $type, $amount, $active);
            if($stmt->execute()) {
                $success_msg = "Polisi lewat bayar berjaya disimpan.";
                logAction($conn, "Kemaskini polisi lewat bayar", 'Success');
            }
        } elseif ($action === 'bulk_invoice') {
            $month = $_POST['inv_month'];
            $year = $_POST['inv_year'];
            $due_date = $_POST['inv_due_date'];
            $class_filter = !empty($_POST['inv_class']) ? $_POST['inv_class'] : null;
            
            $res = createMonthlyInvoices($conn, $month, $year, $class_filter, $due_date);
            $success_msg = "Janaan Pukal Selesai: {$res['created']} invois dijana, {$res['skipped']} dilangkau.";
            logAction($conn, "Jana invois pukal $month/$year", 'Success');
        }
    }
}

// Fetch data
$fees = $conn->query("SELECT * FROM fee_structures ORDER BY module, fee_name");
$classes = $conn->query("SELECT id, class_name FROM classes ORDER BY class_name");

$sibling_rules = [2 => 10, 3 => 15]; // defaults
$res_sib = $conn->query("SELECT child_order, discount_pct FROM sibling_discount_rules");
while ($row = $res_sib->fetch_assoc()) {
    $sibling_rules[$row['child_order']] = $row['discount_pct'];
}

$late_rules = ['grace_period_days'=>7, 'late_fee_type'=>'Fixed', 'late_fee_amount'=>10.00, 'is_active'=>1];
$res_late = $conn->query("SELECT * FROM late_payment_rules LIMIT 1");
if ($row = $res_late->fetch_assoc()) {
    $late_rules = $row;
}

renderAdminHeader('Struktur Yuran');
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6 flex justify-between items-center">
    <div>
        <span class="material-symbols-outlined align-middle mr-2">check_circle</span>
        <?= htmlspecialchars($success_msg) ?>
    </div>
    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-900">
        <span class="material-symbols-outlined">close</span>
    </button>
</div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 flex justify-between items-center">
    <div>
        <span class="material-symbols-outlined align-middle mr-2">error</span>
        <?= htmlspecialchars($error_msg) ?>
    </div>
    <button onclick="this.parentElement.style.display='none'" class="text-red-700 hover:text-red-900">
        <span class="material-symbols-outlined">close</span>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- LEFT: Fee Structures Table -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-[#f7f9fb]/50">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#333093]">account_balance_wallet</span>
                    Senarai Struktur Yuran
                </h2>
                <button onclick="openFeeModal()" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium transition-all text-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[20px]">add</span> Yuran Baru
                </button>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-[#f7f9fb] text-xs uppercase text-gray-500 border-b">
                        <tr>
                            <th class="px-6 py-3 font-medium">Nama Yuran</th>
                            <th class="px-6 py-3 font-medium">Modul</th>
                            <th class="px-6 py-3 font-medium">Jenis</th>
                            <th class="px-6 py-3 font-medium text-right">Jumlah (RM)</th>
                            <th class="px-6 py-3 font-medium text-center">Status</th>
                            <th class="px-6 py-3 font-medium text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($fees->num_rows > 0): ?>
                            <?php while ($fee = $fees->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($fee['fee_name']) ?></div>
                                        <?php if($fee['valid_until']): ?>
                                            <div class="text-xs text-gray-400 mt-1">Sah hingga: <?= date('d/m/Y', strtotime($fee['valid_until'])) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            <?= htmlspecialchars($fee['module']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php 
                                        $freq_map = ['Monthly'=>'Bulanan', 'Yearly'=>'Tahunan', 'One-Time'=>'Sekali Sahaja'];
                                        echo $freq_map[$fee['frequency']] ?? $fee['frequency'];
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-gray-800">
                                        <?= number_format($fee['amount'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if($fee['is_active']): ?>
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Aktif</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Nyahaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <button onclick='editFee(<?= json_encode($fee) ?>)' class="text-[#333093] hover:text-[#5452b5] p-1" title="Kemaskini">
                                            <span class="material-symbols-outlined text-[20px]">edit</span>
                                        </button>
                                        <form method="POST" class="inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="toggle_fee">
                                            <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $fee['is_active'] ?>">
                                            <button type="submit" class="<?= $fee['is_active'] ? 'text-amber-500 hover:text-amber-700' : 'text-emerald-500 hover:text-emerald-700' ?> p-1" title="<?= $fee['is_active'] ? 'Nyahaktif' : 'Aktifkan' ?>">
                                                <span class="material-symbols-outlined text-[20px]"><?= $fee['is_active'] ? 'block' : 'check_circle' ?></span>
                                            </button>
                                        </form>
                                        <form method="POST" class="inline">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="clone_fee">
                                            <input type="hidden" name="fee_id" value="<?= $fee['id'] ?>">
                                            <button type="submit" class="text-gray-400 hover:text-gray-600 p-1" title="Klon Yuran">
                                                <span class="material-symbols-outlined text-[20px]">content_copy</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Tiada rekod yuran ditemui.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: Side Panels -->
    <div class="space-y-6">
        
        <!-- Janaan Pukal -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
            <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#333093]">receipt_long</span>
                Janaan Invois Pukal
            </h3>
            <form method="POST" onsubmit="return confirm('Anda pasti mahu menjana invois pukal ini? Proses ini mungkin mengambil masa.')">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="bulk_invoice">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bulan</label>
                            <select name="inv_month" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                                <?php 
                                $curr_m = date('m');
                                for($i=1; $i<=12; $i++) {
                                    $m = str_pad($i, 2, '0', STR_PAD_LEFT);
                                    $sel = ($m == $curr_m) ? 'selected' : '';
                                    echo "<option value='$m' $sel>".date('F', mktime(0,0,0,$i,1))."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
                            <select name="inv_year" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                                <?php 
                                $curr_y = date('Y');
                                for($y=$curr_y-1; $y<=$curr_y+1; $y++) {
                                    $sel = ($y == $curr_y) ? 'selected' : '';
                                    echo "<option value='$y' $sel>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tarikh Akhir (Due Date)</label>
                        <input type="date" name="inv_due_date" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                    <button type="submit" class="w-full bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2.5 rounded-lg font-medium transition-all text-sm shadow-sm flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">play_circle</span>
                        Jana Invois Bulanan
                    </button>
                </div>
            </form>
        </div>

        <!-- Sibling Discounts -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
            <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#333093]">group</span>
                Diskaun Adik-Beradik
            </h3>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_sibling_rules">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Anak Ke-2 (%)</label>
                        <input type="number" step="0.01" name="child_2_pct" value="<?= $sibling_rules[2] ?>" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Anak Ke-3 dan seterusnya (%)</label>
                        <input type="number" step="0.01" name="child_3_pct" value="<?= $sibling_rules[3] ?>" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                    <button type="submit" class="w-full border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-4 py-2 rounded-lg font-medium transition-all text-sm">
                        Simpan Diskaun
                    </button>
                </div>
            </form>
        </div>

        <!-- Late Payment Policy -->
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6">
            <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#333093]">warning</span>
                Polisi Lewat Bayar
            </h3>
            <form method="POST">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_late_rules">
                <div class="space-y-4">
                    <div class="flex items-center gap-2 mb-2">
                        <input type="checkbox" name="is_active" id="late_active" class="rounded text-[#333093] focus:ring-[#333093]" <?= $late_rules['is_active']?'checked':'' ?>>
                        <label for="late_active" class="text-sm text-gray-700 font-medium">Aktifkan Denda Lewat</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tempoh Kelonggaran (Hari)</label>
                        <input type="number" name="grace_period" value="<?= $late_rules['grace_period_days'] ?>" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                        <p class="text-xs text-gray-500 mt-1">Hari selepas tarikh akhir sebelum denda dikenakan.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Denda</label>
                            <select name="late_fee_type" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                                <option value="Fixed" <?= $late_rules['late_fee_type']=='Fixed'?'selected':'' ?>>Tetap (RM)</option>
                                <option value="Percentage" <?= $late_rules['late_fee_type']=='Percentage'?'selected':'' ?>>Peratusan (%)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah</label>
                            <input type="number" step="0.01" name="late_fee_amount" value="<?= $late_rules['late_fee_amount'] ?>" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                        </div>
                    </div>
                    <button type="submit" class="w-full border border-[#333093] text-[#333093] hover:bg-[#333093] hover:text-white px-4 py-2 rounded-lg font-medium transition-all text-sm">
                        Simpan Polisi
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>

<!-- Modal Fee -->
<div id="feeModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeFeeModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-[#f7f9fb]">
            <h3 id="modalTitle" class="text-lg font-bold text-gray-800">Tambah Yuran</h3>
            <button onclick="closeFeeModal()" class="text-gray-400 hover:text-gray-600">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="admin_fees.php" class="p-6 overflow-y-auto max-h-[70vh]">
            <?= csrf_input() ?>
            <input type="hidden" name="action" id="modalAction" value="add_fee">
            <input type="hidden" name="fee_id" id="modalFeeId" value="">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                    <select name="module" id="modalModule" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                        <option value="Taska">Taska</option>
                        <option value="Tadika">Tadika</option>
                        <option value="KAFA Care">KAFA Care</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Yuran</label>
                    <input type="text" name="fee_name" id="modalFeeName" required placeholder="Cth: Yuran Pendaftaran" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah (RM)</label>
                        <input type="number" step="0.01" name="amount" id="modalAmount" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kekerapan</label>
                        <select name="frequency" id="modalFrequency" required class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                            <option value="Monthly">Bulanan</option>
                            <option value="Yearly">Tahunan</option>
                            <option value="One-Time">Sekali Sahaja</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diskaun Tambahan (Pilihan, %)</label>
                    <input type="number" step="0.01" name="sibling_discount_pct" id="modalSiblingDiscount" value="0.00" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    <p class="text-xs text-gray-500 mt-1">Diskaun statik untuk yuran ini.</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sah Dari</label>
                        <input type="date" name="valid_from" id="modalValidFrom" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sah Hingga</label>
                        <input type="date" name="valid_until" id="modalValidUntil" class="w-full rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20 sm:text-sm">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeFeeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">Batal</button>
                <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm shadow-sm">Simpan Yuran</button>
            </div>
        </form>
    </div>
</div>

<script>
function openFeeModal() {
    document.getElementById('modalTitle').textContent = 'Tambah Yuran Baru';
    document.getElementById('modalAction').value = 'add_fee';
    document.getElementById('modalFeeId').value = '';
    document.getElementById('modalModule').value = 'Taska';
    document.getElementById('modalFeeName').value = '';
    document.getElementById('modalAmount').value = '';
    document.getElementById('modalFrequency').value = 'Monthly';
    document.getElementById('modalSiblingDiscount').value = '0.00';
    document.getElementById('modalValidFrom').value = '';
    document.getElementById('modalValidUntil').value = '';
    
    document.getElementById('feeModal').classList.remove('hidden');
}

function editFee(fee) {
    document.getElementById('modalTitle').textContent = 'Kemaskini Yuran';
    document.getElementById('modalAction').value = 'edit_fee';
    document.getElementById('modalFeeId').value = fee.id;
    document.getElementById('modalModule').value = fee.module;
    document.getElementById('modalFeeName').value = fee.fee_name;
    document.getElementById('modalAmount').value = fee.amount;
    document.getElementById('modalFrequency').value = fee.frequency;
    document.getElementById('modalSiblingDiscount').value = fee.sibling_discount_pct || '0.00';
    document.getElementById('modalValidFrom').value = fee.valid_from || '';
    document.getElementById('modalValidUntil').value = fee.valid_until || '';
    
    document.getElementById('feeModal').classList.remove('hidden');
}

function closeFeeModal() {
    document.getElementById('feeModal').classList.add('hidden');
}
</script>

<?php renderAdminFooter(); ?>
