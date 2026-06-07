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

$selected_app_id = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error_msg = "Token keselamatan tidak sah.";
    } else {
        $action = $_POST['action'] ?? '';
        $doc_id = (int)($_POST['doc_id'] ?? 0);
        $app_id = (int)($_POST['app_id'] ?? 0);
        
        $selected_app_id = $app_id; // Keep selected
        
        if ($action === 'verify_doc') {
            $stmt = $conn->prepare("UPDATE application_documents SET status='Verified', verified_by=?, verified_at=NOW() WHERE id=?");
            $admin_id = $_SESSION['user_id'];
            $stmt->bind_param("ii", $admin_id, $doc_id);
            if ($stmt->execute()) {
                $success_msg = "Dokumen disahkan.";
                logAction($conn, "Sahkan dokumen ID: $doc_id untuk permohonan $app_id", 'Success');
                checkAllDocsVerified($conn, $app_id);
            }
        } elseif ($action === 'reject_doc') {
            $reason = $_POST['reject_reason'] ?? '';
            $stmt = $conn->prepare("UPDATE application_documents SET status='Rejected', rejection_reason=? WHERE id=?");
            $stmt->bind_param("si", $reason, $doc_id);
            if ($stmt->execute()) {
                // Get parent_id to notify
                $stmt_p = $conn->prepare("SELECT a.parent_id, a.child_name, d.document_name FROM applications a JOIN application_documents d ON a.id = d.application_id WHERE d.id=?");
                $stmt_p->bind_param("i", $doc_id);
                $stmt_p->execute();
                $res = $stmt_p->get_result()->fetch_assoc();
                
                notifyParent($conn, $res['parent_id'], "Dokumen Ditolak", "Dokumen ({$res['document_name']}) untuk permohonan {$res['child_name']} telah ditolak. Sebab: $reason. Sila muat naik semula.", 'error', 'parent_home.php');
                $success_msg = "Dokumen ditolak. Notifikasi dihantar kepada ibu bapa.";
                logAction($conn, "Tolak dokumen ID: $doc_id", 'Success');
            }
        } elseif ($action === 'proceed_offer') {
            $stmt_upd = $conn->prepare("UPDATE applications SET status='Offer Sent', enrollment_offer_expiry=DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id=?");
            $stmt_upd->bind_param("i", $app_id);
            if ($stmt_upd->execute()) {
                // Get parent info
                $stmt_p = $conn->prepare("SELECT parent_id, child_name FROM applications WHERE id=?");
                $stmt_p->bind_param("i", $app_id);
                $stmt_p->execute();
                $p = $stmt_p->get_result()->fetch_assoc();
                
                notifyParent($conn, $p['parent_id'], "Tawaran Pendaftaran", "Tahniah! Permohonan pendaftaran untuk {$p['child_name']} telah diluluskan. Sila sahkan tawaran dalam masa 7 hari.", 'success', 'parent_invoices.php');
                logAction($conn, "Hantar tawaran pendaftaran ID: $app_id (selepas dokumen)", 'Success');
                header("Location: admin_applications.php");
                exit;
            }
        }
    }
}

function checkAllDocsVerified($conn, $app_id) {
    // Check if there are any non-verified docs for this app (assuming 5 docs required)
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM application_documents WHERE application_id=? AND status != 'Verified'");
    $stmt->bind_param("i", $app_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()['c'] == 0) {
        $stmt_u = $conn->prepare("UPDATE applications SET documents_verified=1 WHERE id=?");
        $stmt_u->bind_param("i", $app_id);
        $stmt_u->execute();
    }
}

// Fetch applications list (Left Panel)
$search = $_GET['search'] ?? '';
$sql_list = "SELECT a.id, a.child_name, a.module, a.created_at, 
            (SELECT COUNT(*) FROM application_documents WHERE application_id=a.id AND status='Verified') as verified_count,
            (SELECT COUNT(*) FROM application_documents WHERE application_id=a.id) as total_docs
            FROM applications a 
            WHERE a.status IN ('Pending', 'Under Review')";
if ($search) {
    $sql_list .= " AND a.child_name LIKE '%" . $conn->real_escape_string($search) . "%'";
}
$sql_list .= " ORDER BY a.created_at ASC";
$apps_list = $conn->query($sql_list);

if ($apps_list->num_rows > 0 && !$selected_app_id) {
    $first = $apps_list->fetch_assoc();
    $selected_app_id = $first['id'];
    $apps_list->data_seek(0); // Reset pointer
}

// Fetch selected app details
$selected_app = null;
$documents = [];
$all_verified = false;

if ($selected_app_id) {
    // Mark as Under Review if Pending
    $conn->query("UPDATE applications SET status='Under Review' WHERE id=$selected_app_id AND status='Pending'");
    
    $stmt_app = $conn->prepare("SELECT a.*, p.full_name as parent_name FROM applications a JOIN parents p ON a.parent_id = p.id WHERE a.id=?");
    $stmt_app->bind_param("i", $selected_app_id);
    $stmt_app->execute();
    $selected_app = $stmt_app->get_result()->fetch_assoc();
    
    $stmt_docs = $conn->prepare("SELECT * FROM application_documents WHERE application_id=? ORDER BY id ASC");
    $stmt_docs->bind_param("i", $selected_app_id);
    $stmt_docs->execute();
    $res_docs = $stmt_docs->get_result();
    
    $v_count = 0;
    while($d = $res_docs->fetch_assoc()) {
        $documents[$d['document_type']] = $d;
        if($d['status'] === 'Verified') $v_count++;
    }
    
    if ($v_count === 5 && count($documents) === 5) { // Assuming 5 mandatory docs
        $all_verified = true;
    }
}

$req_doc_types = ['MyKid', 'Parent IC', 'Passport Photo', 'Vaccination Record', 'Health Declaration'];

renderAdminHeader('Semakan Dokumen');
?>

<?php if ($success_msg): ?>
<div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 rounded mb-6 flex justify-between">
    <div><span class="material-symbols-outlined align-middle mr-2">check_circle</span><?= htmlspecialchars($success_msg) ?></div>
    <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-900"><span class="material-symbols-outlined">close</span></button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-180px)]">
    
    <!-- LEFT PANEL: Application List -->
    <div class="lg:col-span-4 flex flex-col bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden h-full">
        <div class="p-4 border-b border-gray-100 bg-[#f7f9fb]/50">
            <h3 class="text-md font-bold text-gray-800 flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined text-[#333093]">list_alt</span> Senarai Permohonan
            </h3>
            <form method="GET" class="relative">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama..." class="w-full pl-9 pr-4 py-2 rounded-lg border-gray-300 text-sm focus:border-[#333093] focus:ring-[#333093]/20">
                <span class="material-symbols-outlined absolute left-2.5 top-2 text-gray-400 text-[20px]">search</span>
            </form>
        </div>
        
        <div class="overflow-y-auto flex-1 p-2 space-y-1">
            <?php if ($apps_list->num_rows > 0): ?>
                <?php while($item = $apps_list->fetch_assoc()): 
                    $is_active = ($item['id'] == $selected_app_id);
                    $active_classes = $is_active ? 'bg-[#f0f4ff] border-[#333093]' : 'bg-white border-transparent hover:bg-gray-50';
                    $pct = $item['total_docs'] > 0 ? floor(($item['verified_count'] / 5) * 100) : 0;
                ?>
                    <a href="?app_id=<?= $item['id'] ?>" class="block p-3 rounded-lg border-l-4 <?= $active_classes ?> transition-colors">
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-semibold text-gray-800 text-sm truncate"><?= htmlspecialchars($item['child_name']) ?></h4>
                            <span class="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded whitespace-nowrap"><?= $item['module'] ?></span>
                        </div>
                        <div class="flex justify-between items-center text-xs text-gray-500">
                            <span><?= date('d/m/Y', strtotime($item['created_at'])) ?></span>
                            <span class="<?= $pct==100 ? 'text-emerald-600 font-bold' : '' ?>"><?= $item['verified_count'] ?>/5 Disahkan</span>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-6 text-center text-gray-500 text-sm">Tiada permohonan yang menunggu semakan.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT PANEL: Document Verification -->
    <div class="lg:col-span-8 flex flex-col bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 overflow-hidden h-full">
        <?php if ($selected_app): ?>
            
            <!-- Header -->
            <div class="p-6 border-b border-gray-100 bg-[#f7f9fb]/50">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($selected_app['child_name']) ?></h2>
                        <div class="flex items-center gap-4 text-sm text-gray-500">
                            <span>MyKid: <strong class="text-gray-700"><?= htmlspecialchars($selected_app['child_mykid']) ?></strong></span>
                            <span>Ibu/Bapa: <strong class="text-gray-700"><?= htmlspecialchars($selected_app['parent_name']) ?></strong></span>
                        </div>
                    </div>
                    <span class="inline-flex px-3 py-1 rounded-full text-sm font-semibold bg-gray-200 text-gray-700 border border-gray-300">
                        Modul: <?= htmlspecialchars($selected_app['module']) ?>
                    </span>
                </div>
            </div>

            <!-- Documents List -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4">
                <h3 class="font-bold text-gray-800 mb-4 border-b pb-2">Senarai Semak Dokumen</h3>
                
                <?php foreach($req_doc_types as $type): 
                    $doc = $documents[$type] ?? null;
                ?>
                <div class="flex flex-col md:flex-row md:items-center justify-between p-4 border rounded-lg <?= $doc ? 'bg-white border-gray-200' : 'bg-gray-50 border-dashed border-gray-300' ?>">
                    
                    <div class="flex items-start gap-3 mb-3 md:mb-0">
                        <?php if($doc && $doc['status'] == 'Verified'): ?>
                            <span class="material-symbols-outlined text-emerald-500">check_circle</span>
                        <?php elseif($doc && $doc['status'] == 'Rejected'): ?>
                            <span class="material-symbols-outlined text-red-500">cancel</span>
                        <?php elseif($doc): ?>
                            <span class="material-symbols-outlined text-amber-500">hourglass_empty</span>
                        <?php else: ?>
                            <span class="material-symbols-outlined text-gray-300">description</span>
                        <?php endif; ?>
                        
                        <div>
                            <div class="font-semibold text-gray-800 text-sm"><?= $type ?></div>
                            <?php if ($doc): ?>
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-[#333093] hover:underline text-xs flex items-center gap-1 mt-1">
                                    <span class="material-symbols-outlined text-[14px]">visibility</span> Lihat Dokumen
                                </a>
                                <?php if($doc['status'] == 'Rejected'): ?>
                                    <p class="text-xs text-red-600 mt-1">Sebab tolak: <?= htmlspecialchars($doc['rejection_reason']) ?></p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 mt-1">Belum dimuat naik</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($doc): ?>
                        <div class="flex items-center gap-2">
                            <?php if ($doc['status'] == 'Pending' || $doc['status'] == 'Rejected'): ?>
                                <form method="POST" class="inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="verify_doc">
                                    <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                    <input type="hidden" name="app_id" value="<?= $selected_app_id ?>">
                                    <button type="submit" class="bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 px-3 py-1.5 rounded text-xs font-semibold transition-colors flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">done</span> Sahkan
                                    </button>
                                </form>
                                <button onclick="openRejectModal(<?= $doc['id'] ?>)" class="bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 px-3 py-1.5 rounded text-xs font-semibold transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">close</span> Tolak
                                </button>
                            <?php elseif ($doc['status'] == 'Verified'): ?>
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded text-xs font-semibold flex items-center gap-1">
                                    Disahkan
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <span class="text-xs font-medium text-gray-400 bg-gray-100 px-3 py-1 rounded">Menunggu Muat Naik</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Footer Action -->
            <div class="p-6 border-t border-gray-100 bg-[#f7f9fb]/50 flex justify-end">
                <?php if ($all_verified): ?>
                    <form method="POST" onsubmit="return confirm('Hantar tawaran pendaftaran kepada ibu bapa ini?')">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="proceed_offer">
                        <input type="hidden" name="app_id" value="<?= $selected_app_id ?>">
                        <button type="submit" class="bg-[#333093] hover:bg-[#5452b5] text-white px-6 py-2.5 rounded-lg font-medium shadow-sm flex items-center gap-2">
                            Semua Disahkan — Teruskan Tawaran <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </form>
                <?php else: ?>
                    <button disabled class="bg-gray-300 text-gray-500 px-6 py-2.5 rounded-lg font-medium cursor-not-allowed flex items-center gap-2">
                        Sahkan Semua Dokumen Dahulu
                    </button>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <div class="flex flex-col items-center justify-center h-full text-gray-400">
                <span class="material-symbols-outlined text-[64px] mb-4 opacity-50">fact_check</span>
                <p class="text-lg font-medium">Pilih permohonan dari senarai di sebelah kiri</p>
                <p class="text-sm">untuk mula menyemak dokumen.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Reject Document Modal -->
<div id="rejectModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeRejectModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-red-50">
            <h3 class="text-lg font-bold text-red-800 flex items-center gap-2"><span class="material-symbols-outlined text-red-600">cancel</span> Tolak Dokumen</h3>
            <button onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600"><span class="material-symbols-outlined">close</span></button>
        </div>
        <form method="POST" class="p-6">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="reject_doc">
            <input type="hidden" name="app_id" value="<?= $selected_app_id ?>">
            <input type="hidden" name="doc_id" id="rejectDocId" value="">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sebab Penolakan</label>
                <textarea name="reject_reason" required rows="3" class="w-full rounded-lg border-gray-300 focus:border-red-500 focus:ring focus:ring-red-200 sm:text-sm" placeholder="Cth: Dokumen kabur, Sila muat naik IC berwarna..."></textarea>
                <p class="text-[10px] text-gray-500 mt-1">Sebab ini akan dihantar kepada ibu bapa.</p>
            </div>
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium text-sm shadow-sm">Sahkan Penolakan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(docId) {
    document.getElementById('rejectDocId').value = docId;
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php renderAdminFooter(); ?>
