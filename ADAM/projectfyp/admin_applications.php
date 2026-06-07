<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/csrf_helper.php';
require_once 'includes/log_helper.php';
require_once 'includes/notification_helper.php';
require_once 'includes/admin_layout.php';

sahkan_peranan('admin');

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_msg = "Token keselamatan tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';
        $app_id = (int)($_POST['app_id'] ?? 0);
        
        // Get app details
        $stmt = $conn->prepare("SELECT parent_id, child_name FROM applications WHERE id=?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $app = $stmt->get_result()->fetch_assoc();
        
        if ($app) {
            if ($action === 'waitlist') {
                $stmt_upd = $conn->prepare("UPDATE applications SET status='Waitlisted', waitlist_position=(SELECT COALESCE(MAX(waitlist_position),0)+1 FROM (SELECT waitlist_position FROM applications WHERE status='Waitlisted') AS tmp) WHERE id=?");
                $stmt_upd->bind_param("i", $app_id);
                if ($stmt_upd->execute()) {
                    $success_msg = "Permohonan dimasukkan ke senarai tunggu.";
                    logAction($conn, "Kemaskini status permohonan ID: $app_id ke Waitlisted", 'Success');
                }
            } elseif ($action === 'offer') {
                $stmt_upd = $conn->prepare("UPDATE applications SET status='Offer Sent', enrollment_offer_expiry=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id=?");
                $stmt_upd->bind_param("i", $app_id);
                if ($stmt_upd->execute()) {
                    notifyParent($conn, $app['parent_id'], "Tawaran Pendaftaran", "Tahniah! Permohonan pendaftaran untuk {$app['child_name']} telah diluluskan. Sila sahkan tawaran dalam masa 7 hari.", 'success', 'parent_invoices.php');
                    $success_msg = "Tawaran dihantar kepada ibu bapa.";
                    logAction($conn, "Hantar tawaran untuk permohonan ID: $app_id", 'Success');
                }
            } elseif ($action === 'reject') {
                $reason = $_POST['reject_reason'] ?? 'Tidak memenuhi syarat';
                $stmt_upd = $conn->prepare("UPDATE applications SET status='Rejected', rejection_reason=? WHERE id=?");
                $stmt_upd->bind_param("si", $reason, $app_id);
                if ($stmt_upd->execute()) {
                    notifyParent($conn, $app['parent_id'], "Status Permohonan", "Dukacita dimaklumkan permohonan untuk {$app['child_name']} tidak berjaya. Sebab: $reason", 'error', 'parent_home.php');
                    $success_msg = "Permohonan telah ditolak.";
                    logAction($conn, "Tolak permohonan ID: $app_id", 'Success');
                }
            } elseif ($action === 'confirm') {
                // Confirm enrollment -> create student -> enroll in class
                $stmt_full = $conn->prepare("SELECT * FROM applications WHERE id=?");
                $stmt_full->bind_param("i", $app_id);
                $stmt_full->execute();
                $full_app = $stmt_full->get_result()->fetch_assoc();
                
                $conn->begin_transaction();
                try {
                    // Create student
                    $stmt_stu = $conn->prepare("INSERT INTO students (parent_id, full_name, mykid_number, module, health_record, allergies, status) VALUES (?, ?, ?, ?, ?, ?, 'Active')");
                    $stmt_stu->bind_param("isssss", $full_app['parent_id'], $full_app['child_name'], $full_app['child_mykid'], $full_app['module'], $full_app['health_record'], $full_app['allergies']);
                    $stmt_stu->execute();
                    $student_id = $conn->insert_id;
                    
                    // Assign to class (logic skipped for simplicity, admin can assign later, or if class requested was tracked, do it here)
                    // Update app
                    $stmt_upd = $conn->prepare("UPDATE applications SET status='Enrolled' WHERE id=?");
                    $stmt_upd->bind_param("i", $app_id);
                    $stmt_upd->execute();
                    
                    $conn->commit();
                    notifyParent($conn, $full_app['parent_id'], "Pendaftaran Disahkan", "Pendaftaran {$full_app['child_name']} telah disahkan.", 'success', 'profil_anak.php');
                    logAction($conn, "Pendaftaran disahkan untuk permohonan ID: $app_id", 'Success');
                    $success_msg = "Pendaftaran disahkan dan rekod pelajar telah dicipta.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_msg = "Ralat mengesahkan pendaftaran.";
                }
            } elseif ($action === 'extend_offer') {
                $stmt_upd = $conn->prepare("UPDATE applications SET enrollment_offer_expiry=DATE_ADD(enrollment_offer_expiry, INTERVAL 7 DAY) WHERE id=?");
                $stmt_upd->bind_param("i", $app_id);
                if ($stmt_upd->execute()) {
                    $success_msg = "Tarikh tawaran dilanjutkan 7 hari.";
                    logAction($conn, "Lanjut tarikh tawaran permohonan ID: $app_id", 'Success');
                }
            } elseif ($action === 'cancel_offer') {
                $stmt_upd = $conn->prepare("UPDATE applications SET status='Waitlisted', enrollment_offer_expiry=NULL WHERE id=?");
                $stmt_upd->bind_param("i", $app_id);
                if ($stmt_upd->execute()) {
                    $success_msg = "Tawaran dibatalkan dan dikembalikan ke senarai tunggu.";
                    logAction($conn, "Batal tawaran permohonan ID: $app_id", 'Success');
                }
            } elseif ($action === 'promote_waitlist') {
                // Promote logic (simplified, just swap waitlist position with the one above)
                $stmt_upd = $conn->prepare("UPDATE applications SET waitlist_position=GREATEST(1, waitlist_position-1) WHERE id=?");
                $stmt_upd->bind_param("i", $app_id);
                $stmt_upd->execute();
                $success_msg = "Kedudukan dinaikkan.";
            }
        }
    }
}

// Fetch capacities
$class_capacities = [];
$res_cap = $conn->query("
    SELECT c.id, c.class_name, c.module, c.capacity, COUNT(sc.student_id) as enrolled 
    FROM classes c 
    LEFT JOIN student_classes sc ON c.id = sc.class_id 
    LEFT JOIN students s ON sc.student_id = s.id AND s.status='Active'
    GROUP BY c.id
");
while($row = $res_cap->fetch_assoc()) {
    $class_capacities[] = $row;
}

// Fetch applications
$sql = "SELECT a.*, p.full_name as parent_name, p.phone_number, 
        EXISTS(SELECT 1 FROM sibling_links sl WHERE sl.parent_id = a.parent_id) as has_sibling 
        FROM applications a 
        JOIN parents p ON a.parent_id = p.id 
        ORDER BY a.priority_score DESC, a.created_at ASC";
$res_apps = $conn->query($sql);

$apps = [
    'Pending' => [],
    'Under Review' => [],
    'Waitlisted' => [],
    'Offer Sent' => [],
    'Enrolled' => [],
    'Rejected' => []
];

while($row = $res_apps->fetch_assoc()) {
    $apps[$row['status']][] = $row;
}

function renderAppCard($app, $status) {
    global $conn;
    $age = date_diff(date_create($app['child_dob']), date_create('today'))->y;
    $docs_icon = $app['documents_verified'] ? '<span class="text-emerald-500 material-symbols-outlined text-[16px]">check_circle</span>' : '<span class="text-amber-500 material-symbols-outlined text-[16px]">warning</span>';
    
    echo "<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/40 p-4 mb-4 hover:shadow-md transition-shadow">";
    echo "  <div class="flex justify-between items-start mb-2">";
    echo "      <div>";
    echo "          <h4 class="font-bold text-gray-800">" . htmlspecialchars($app['child_name']) . "</h4>";
    echo "          <p class="text-xs text-gray-500">Umur: {$age} tahun &bull; " . htmlspecialchars($app['module']) . "</p>";
    echo "      </div>";
    if($app['has_sibling']) {
        echo "      <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-semibold border border-blue-100"><span class="material-symbols-outlined text-[14px]">group</span>Adik-beradik</span>";
    }
    echo "  </div>";
    
    echo "  <div class="text-xs text-gray-600 space-y-1 mb-3">";
    echo "      <p class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-gray-400">person</span> " . htmlspecialchars($app['parent_name']) . "</p>";
    echo "      <p class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-gray-400">call</span> " . htmlspecialchars($app['phone_number']) . "</p>";
    echo "      <p class="flex items-center gap-1">{$docs_icon} Dokumen " . ($app['documents_verified'] ? "Lengkap" : "Tidak Lengkap") . "</p>";
    echo "      <p class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px] text-gray-400">calendar_today</span> Mohon: " . date('d/m/Y', strtotime($app['created_at'])) . "</p>";
    if($status == 'Waitlisted') {
        echo "  <p class="font-semibold text-amber-600 mt-2">Kedudukan Senarai Tunggu: #" . $app['waitlist_position'] . "</p>";
    } elseif($status == 'Offer Sent') {
        $days = (strtotime($app['enrollment_offer_expiry']) - time()) / (60*60*24);
        $color = $days < 2 ? 'text-red-500' : 'text-blue-500';
        echo "  <p class="font-semibold {$color} mt-2 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">timer</span> Tamat tawaran: " . max(0, floor($days)) . " hari</p>";
    }
    echo "  </div>";
    
    echo "  <div class="flex flex-wrap gap-2 pt-3 border-t border-gray-100 mt-auto">";
    
    if(in_array($status, ['Pending', 'Under Review'])) {
        echo "      <a href="admin_doc_verify.php?app_id={$app['id']}" class="w-full text-center bg-[#f7f9fb] hover:bg-gray-100 text-[#333093] px-3 py-1.5 rounded-lg text-xs font-medium border border-gray-200 transition-colors">Semak Dokumen</a>";
        echo "      <form method="POST" class="w-1/2 flex-1"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="waitlist"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-amber-50 text-amber-700 hover:bg-amber-100 px-2 py-1.5 rounded-lg text-xs font-medium border border-amber-200">Ke Senarai Tunggu</button></form>";
        if($app['documents_verified']) {
            echo "      <form method="POST" class="w-1/2 flex-1"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="offer"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-[#333093] hover:bg-[#5452b5] text-white px-2 py-1.5 rounded-lg text-xs font-medium">Hantar Tawaran</button></form>";
        }
    } elseif($status == 'Waitlisted') {
        echo "      <form method="POST" class="flex-1"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="promote_waitlist"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1.5 rounded-lg text-xs font-medium flex items-center justify-center gap-1"><span class="material-symbols-outlined text-[14px]">arrow_upward</span>Naik</button></form>";
        if($app['documents_verified']) {
            echo "      <form method="POST" class="flex-1"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="offer"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-[#333093] hover:bg-[#5452b5] text-white px-2 py-1.5 rounded-lg text-xs font-medium">Hantar Tawaran</button></form>";
        }
    } elseif($status == 'Offer Sent') {
        echo "      <form method="POST" class="w-full mb-1" onsubmit="return confirm('Sahkan pendaftaran ini?')"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="confirm"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium shadow-sm">Sahkan Pendaftaran</button></form>";
        echo "      <form method="POST" class="flex-1"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="extend_offer"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 px-2 py-1.5 rounded-lg text-[11px] font-medium">+7 Hari</button></form>";
        echo "      <form method="POST" class="flex-1" onsubmit="return confirm('Batal tawaran?')"><input type="hidden" name="csrf_token" value="".generate_csrf_token().""><input type="hidden" name="action" value="cancel_offer"><input type="hidden" name="app_id" value="{$app['id']}"><button type="submit" class="w-full bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 px-2 py-1.5 rounded-lg text-[11px] font-medium">Batal</button></form>";
    } elseif($status == 'Enrolled' || $status == 'Rejected') {
        echo "      <span class="w-full text-center text-gray-400 text-xs py-1">Selesai diproses</span>";
    }
    
    if(in_array($status, ['Pending', 'Under Review', 'Waitlisted'])) {
        echo "      <button onclick="openRejectModal({$app['id']})" class="w-full text-center text-red-500 hover:text-red-700 hover:underline text-xs mt-1">Tolak Permohonan</button>";
    }
    
    echo "  </div>";
    echo "</div>";
}

renderAdminHeader('Permohonan & Pendaftaran');
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6 flex justify-between">
    <div><span class="material-symbols-outlined align-middle mr-2">check_circle</span><?= htmlspecialchars($success_msg) ?></div>
    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-900"><span class="material-symbols-outlined">close</span></button>
</div>
<?php endif; ?>

<!-- Capacity Bar Section -->
<div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-6 mb-6">
    <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2"><span class="material-symbols-outlined text-[#333093]">monitoring</span> Status Kapasiti Kelas</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($class_capacities as $c): 
            $pct = $c['capacity'] > 0 ? ($c['enrolled'] / $c['capacity']) * 100 : 0;
            $color = $pct < 80 ? 'bg-emerald-500' : ($pct < 100 ? 'bg-amber-500' : 'bg-red-500');
        ?>
        <div>
            <div class="flex justify-between items-end mb-1">
                <div>
                    <div class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($c['class_name']) ?></div>
                    <div class="text-[10px] text-gray-500 uppercase"><?= htmlspecialchars($c['module']) ?></div>
                </div>
                <div class="text-xs font-semibold text-gray-700"><?= $c['enrolled'] ?>/<?= $c['capacity'] ?></div>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="<?= $color ?> h-2 rounded-full" style="width: <?= min(100, $pct) ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Tabs -->
<div class="flex overflow-x-auto border-b border-gray-200 mb-6 pb-px hide-scrollbar">
    <button onclick="switchTab('tab-baru')" class="tab-btn px-4 py-2 whitespace-nowrap border-b-2 border-[#333093] text-[#333093] font-semibold transition-colors" data-target="tab-baru">
        Baru & Semakan <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full ml-1"><?= count($apps['Pending']) + count($apps['Under Review']) ?></span>
    </button>
    <button onclick="switchTab('tab-tunggu')" class="tab-btn px-4 py-2 whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" data-target="tab-tunggu">
        Senarai Tunggu <span class="bg-amber-100 text-amber-700 text-xs px-2 py-0.5 rounded-full ml-1"><?= count($apps['Waitlisted']) ?></span>
    </button>
    <button onclick="switchTab('tab-tawaran')" class="tab-btn px-4 py-2 whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" data-target="tab-tawaran">
        Tawaran Dihantar <span class="bg-purple-100 text-purple-700 text-xs px-2 py-0.5 rounded-full ml-1"><?= count($apps['Offer Sent']) ?></span>
    </button>
    <button onclick="switchTab('tab-daftar')" class="tab-btn px-4 py-2 whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" data-target="tab-daftar">
        Didaftarkan <span class="bg-emerald-100 text-emerald-700 text-xs px-2 py-0.5 rounded-full ml-1"><?= count($apps['Enrolled']) ?></span>
    </button>
    <button onclick="switchTab('tab-tolak')" class="tab-btn px-4 py-2 whitespace-nowrap text-gray-500 hover:text-gray-700 transition-colors" data-target="tab-tolak">
        Ditolak <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full ml-1"><?= count($apps['Rejected']) ?></span>
    </button>
</div>

<!-- Tab Content -->
<div id="tab-baru" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php 
    $combined = array_merge($apps['Pending'], $apps['Under Review']);
    if(empty($combined)) echo "<div class='col-span-full text-center py-10 text-gray-500'>Tiada permohonan baru.</div>";
    foreach($combined as $app) renderAppCard($app, $app['status']); 
    ?>
</div>

<div id="tab-tunggu" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php 
    if(empty($apps['Waitlisted'])) echo "<div class='col-span-full text-center py-10 text-gray-500'>Tiada senarai tunggu.</div>";
    foreach($apps['Waitlisted'] as $app) renderAppCard($app, 'Waitlisted'); 
    ?>
</div>

<div id="tab-tawaran" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php 
    if(empty($apps['Offer Sent'])) echo "<div class='col-span-full text-center py-10 text-gray-500'>Tiada tawaran aktif.</div>";
    foreach($apps['Offer Sent'] as $app) renderAppCard($app, 'Offer Sent'); 
    ?>
</div>

<div id="tab-daftar" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php 
    if(empty($apps['Enrolled'])) echo "<div class='col-span-full text-center py-10 text-gray-500'>Tiada pendaftaran baru.</div>";
    foreach($apps['Enrolled'] as $app) renderAppCard($app, 'Enrolled'); 
    ?>
</div>

<div id="tab-tolak" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
    <?php 
    if(empty($apps['Rejected'])) echo "<div class='col-span-full text-center py-10 text-gray-500'>Tiada permohonan ditolak.</div>";
    foreach($apps['Rejected'] as $app) renderAppCard($app, 'Rejected'); 
    ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-bold text-red-800 flex items-center gap-2"><span class="material-symbols-outlined text-red-600">cancel</span> Tolak Permohonan</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="app_id" id="rejectAppId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sebab Penolakan</label>
                <textarea name="reject_reason" required rows="3" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 sm:text-sm" placeholder="Nyatakan sebab penolakan..."></textarea>
                <p class="text-[10px] text-gray-500 mt-1">Sebab ini akan dihantar kepada ibu bapa.</p>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-sm">Sahkan Penolakan</button>
            </div>
        </form>
    </div>
</div>

<style>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.getElementById(tabId).classList.remove('hidden');
    
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.classList.remove('border-b-2', 'border-[#333093]', 'text-[#333093]', 'font-semibold');
        el.classList.add('text-gray-500');
    });
    
    const activeBtn = document.querySelector(`.tab-btn[data-target="${tabId}"]`);
    activeBtn.classList.remove('text-gray-500');
    activeBtn.classList.add('border-b-2', 'border-[#333093]', 'text-[#333093]', 'font-semibold');
}

function openRejectModal(id) {
    document.getElementById('rejectAppId').value = id;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php renderAdminFooter(); ?>
