<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/parent_layout.php';
require_once 'includes/csrf_helper.php';

sahkan_peranan('parent');
$parent_id = dapatkan_parent_id($conn);

// POST Handler for Final Step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("Token keselamatan tidak sah. Sila cuba lagi.");
    }
    
    $invoice_ids = $_POST['selected_invoices'] ?? '';
    $total_amount = (float)($_POST['total_amount'] ?? 0);
    $bank_code = $_POST['bank_code'] ?? '';
    
    if (empty($invoice_ids) || $total_amount <= 0 || empty($bank_code)) {
        die("Data pembayaran tidak lengkap.");
    }
    
    require_once 'includes/payment_gateway.php';
    $gateway = new SandboxPaymentGateway($conn);
    
    $params = [
        'invoice_ids' => $invoice_ids,
        'parent_id' => $parent_id,
        'amount' => $total_amount,
        'description' => "Bayaran Invois: " . count(explode(',', $invoice_ids)) . " item",
        'return_url' => "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/parent_payment_callback.php"
    ];
    
    $result = $gateway->initiatePayment($params);
    
    // Store in session for callback
    $_SESSION['pending_payment'] = [
        'invoice_ids' => $invoice_ids,
        'amount' => $total_amount
    ];
    
    // Redirect to sandbox bank
    header('Location: ' . $result['checkout_url'] . '&bank=' . urlencode($bank_code));
    exit;
}

// Fetch Outstanding Invoices
$pre_select_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

$sql = "SELECT i.*, s.full_name as student_name 
        FROM invoices i 
        JOIN students s ON i.student_id = s.id 
        WHERE i.parent_id = ? AND i.status IN ('Sent', 'Partial', 'Overdue')
        ORDER BY i.due_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$invoices = $stmt->get_result();

$outstanding_invoices = [];
while($r = $invoices->fetch_assoc()) {
    $outstanding_invoices[] = $r;
}

$banks = [
    'maybank2u' => ['name'=>'Maybank2U', 'color'=>'bg-yellow-400', 'initial'=>'M'],
    'cimbclicks' => ['name'=>'CIMB Clicks', 'color'=>'bg-red-600', 'initial'=>'C'],
    'rhbnow' => ['name'=>'RHB Now', 'color'=>'bg-blue-600', 'initial'=>'R'],
    'pbebank' => ['name'=>'Public Bank', 'color'=>'bg-red-700', 'initial'=>'P'],
    'hlbconnect' => ['name'=>'HLB Connect', 'color'=>'bg-blue-800', 'initial'=>'H'],
    'ambank' => ['name'=>'AmBank', 'color'=>'bg-yellow-500', 'initial'=>'A'],
    'bankislam' => ['name'=>'Bank Islam', 'color'=>'bg-red-800', 'initial'=>'B']
];

renderParentHeader('Buat Pembayaran');
?>

<!-- Sandbox Warning -->
<div class="bg-amber-100 border-l-4 border-amber-500 text-amber-800 p-3 sm:p-4 rounded-xl mb-6 shadow-sm flex items-start gap-3">
    <span class="material-symbols-outlined shrink-0 text-amber-600 mt-0.5">warning</span>
    <div>
        <h4 class="font-bold text-sm">Mod Sandbox Aktif</h4>
        <p class="text-xs mt-0.5">Tiada transaksi wang sebenar akan berlaku. Ini adalah persekitaran simulasi (FPX Test Environment) untuk tujuan ujian sistem.</p>
    </div>
</div>

<!-- Progress Steps -->
<div class="mb-8">
    <div class="flex items-center justify-between relative max-w-sm mx-auto">
        <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1 bg-gray-200 z-0 rounded-full"></div>
        <div id="progress-line" class="absolute left-0 top-1/2 transform -translate-y-1/2 w-0 h-1 bg-[#333093] z-0 transition-all duration-300 rounded-full"></div>
        
        <div class="relative z-10 flex flex-col items-center gap-2">
            <div id="step1-icon" class="w-8 h-8 rounded-full bg-[#333093] text-white flex items-center justify-center font-bold text-sm shadow-md transition-colors">1</div>
            <span class="text-[10px] font-bold text-[#333093] uppercase tracking-wide hidden sm:block">Pilih Invois</span>
        </div>
        <div class="relative z-10 flex flex-col items-center gap-2">
            <div id="step2-icon" class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-colors border border-gray-300">2</div>
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide hidden sm:block">Kaedah</span>
        </div>
        <div class="relative z-10 flex flex-col items-center gap-2">
            <div id="step3-icon" class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-colors border border-gray-300">3</div>
            <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wide hidden sm:block">Sahkan</span>
        </div>
    </div>
</div>

<form id="paymentForm" method="POST" action="">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="process_payment">
    <input type="hidden" name="selected_invoices" id="final_invoices" value="">
    <input type="hidden" name="total_amount" id="final_amount" value="0">
    <input type="hidden" name="bank_code" id="final_bank" value="">

    <!-- STEP 1: SELECT INVOICES -->
    <div id="step1" class="transition-all duration-300">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Pilih Invois Untuk Dibayar</h2>
        
        <?php if (empty($outstanding_invoices)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/20 p-8 text-center">
                <span class="material-symbols-outlined text-[48px] text-emerald-500 mb-3">check_circle</span>
                <h3 class="text-lg font-bold text-gray-800 mb-1">Tiada Invois Tertunggak</h3>
                <p class="text-sm text-gray-500">Hebat! Anda tidak mempunyai sebarang baki tertunggak.</p>
                <a href="parent_invoices.php" class="inline-block mt-4 text-[#333093] hover:underline text-sm font-semibold">Kembali ke Senarai Invois</a>
            </div>
        <?php else: ?>
            <div class="space-y-3 mb-6">
                <?php foreach($outstanding_invoices as $inv): 
                    $is_checked = ($pre_select_id == $inv['id']) || ($pre_select_id == 0 && $inv === $outstanding_invoices[0]);
                ?>
                <label class="block cursor-pointer">
                    <div class="bg-white rounded-xl shadow-sm border <?= $is_checked ? 'border-[#333093] ring-1 ring-[#333093]' : 'border-[#c7c5d4]/40 hover:border-gray-400' ?> p-4 flex items-center gap-4 transition-all" id="card-<?= $inv['id'] ?>">
                        <div class="shrink-0">
                            <input type="checkbox" class="inv-checkbox w-5 h-5 rounded border-gray-300 text-[#333093] focus:ring-[#333093]" 
                                   value="<?= $inv['id'] ?>" 
                                   data-amount="<?= $inv['balance_due'] ?>"
                                   <?= $is_checked ? 'checked' : '' ?>
                                   onchange="updateSelection(this, <?= $inv['id'] ?>)">
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="font-bold text-gray-800 text-sm truncate pr-2"><?= htmlspecialchars($inv['student_name']) ?></h4>
                                <?php if($inv['status'] == 'Overdue'): ?>
                                    <span class="shrink-0 bg-red-100 text-red-700 text-[10px] px-2 py-0.5 rounded font-bold">TERTUNGGAK</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-500 mb-1"><?= htmlspecialchars($inv['invoice_number']) ?> &bull; <?= htmlspecialchars($inv['module']) ?></p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="font-bold text-red-600">RM <?= number_format($inv['balance_due'], 2) ?></p>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            
            <div class="bg-[#f0f4ff] border border-[#d6e0ff] rounded-xl p-4 sm:p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-gray-600 font-semibold">Jumlah Dipilih:</span>
                    <span class="text-2xl font-bold text-[#333093]" id="display-total">RM 0.00</span>
                </div>
                
                <div class="border-t border-[#d6e0ff] pt-4 mb-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative">
                            <input type="checkbox" id="partial_toggle" class="sr-only" onchange="togglePartial()">
                            <div class="block bg-gray-300 w-10 h-6 rounded-full transition-colors toggle-bg"></div>
                            <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform"></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700">Buat Bayaran Separa (Partial Payment)</span>
                    </label>
                    <div id="partial_input_wrapper" class="hidden mt-3 pl-12">
                        <label class="block text-xs text-gray-500 mb-1">Sila masukkan jumlah yang ingin dibayar (Min RM 50)</label>
                        <div class="relative max-w-[200px]">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 font-medium">RM</span>
                            <input type="number" id="partial_amount" step="0.01" min="50" oninput="updatePartialDisplay()" class="w-full pl-10 pr-3 py-2 rounded-lg border-gray-300 focus:border-[#333093] focus:ring focus:ring-[#333093]/20">
                        </div>
                    </div>
                </div>

                <button type="button" onclick="nextStep(2)" class="w-full bg-[#333093] hover:bg-[#5452b5] text-white py-3 rounded-xl font-bold shadow-sm transition-colors text-center flex items-center justify-center gap-2">
                    Seterusnya <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- STEP 2: PAYMENT METHOD -->
    <div id="step2" class="hidden transition-all duration-300">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#333093]">account_balance</span> Pilih Bank FPX
        </h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
            <?php foreach($banks as $code => $b): ?>
            <label class="block cursor-pointer">
                <input type="radio" name="bank_selection" value="<?= $code ?>" class="peer sr-only" onchange="checkBankSelected()">
                <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/40 p-4 flex items-center gap-3 transition-all peer-checked:border-[#333093] peer-checked:ring-1 peer-checked:ring-[#333093] peer-checked:bg-[#f0f4ff] hover:border-gray-400">
                    <div class="w-10 h-10 rounded-full <?= $b['color'] ?> text-white font-bold flex items-center justify-center shadow-inner shrink-0">
                        <?= $b['initial'] ?>
                    </div>
                    <span class="font-semibold text-gray-800 text-sm"><?= $b['name'] ?></span>
                    <span class="material-symbols-outlined text-emerald-500 ml-auto opacity-0 peer-checked:opacity-100 transition-opacity">check_circle</span>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="prevStep(1)" class="w-1/3 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 py-3 rounded-xl font-bold shadow-sm transition-colors text-center">
                Kembali
            </button>
            <button type="button" id="btn_step2" onclick="nextStep(3)" disabled class="w-2/3 bg-gray-300 text-gray-500 py-3 rounded-xl font-bold transition-colors text-center cursor-not-allowed">
                Seterusnya
            </button>
        </div>
    </div>

    <!-- STEP 3: CONFIRMATION -->
    <div id="step3" class="hidden transition-all duration-300">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Sahkan Butiran Pembayaran</h2>
        
        <div class="bg-white rounded-xl shadow-sm border border-[#c7c5d4]/40 overflow-hidden mb-6">
            <div class="p-6 border-b border-gray-100 bg-[#f7f9fb]/50 flex flex-col items-center justify-center text-center">
                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Jumlah Yang Akan Dibayar</p>
                <p class="text-4xl font-bold text-[#333093]" id="confirm_total">RM 0.00</p>
            </div>
            
            <div class="p-6 space-y-4 text-sm">
                <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                    <span class="text-gray-500">Kaedah Pembayaran</span>
                    <span class="font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-blue-600 font-black text-lg leading-none tracking-tighter">FPX</span> 
                        <span id="confirm_bank" class="text-gray-600 font-medium">Bank</span>
                    </span>
                </div>
                <div class="flex justify-between items-center pb-3 border-b border-gray-100">
                    <span class="text-gray-500">Invois Terpilih</span>
                    <span class="font-bold text-gray-800" id="confirm_count">0 invois</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-500">Tarikh Transaksi</span>
                    <span class="font-semibold text-gray-800"><?= date('d M Y, H:i') ?></span>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="button" onclick="prevStep(2)" class="w-1/3 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 py-3 rounded-xl font-bold shadow-sm transition-colors text-center">
                Kembali
            </button>
            <button type="submit" class="w-2/3 bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-xl font-bold shadow-sm transition-colors text-center flex items-center justify-center gap-2">
                Teruskan ke Bank <span class="material-symbols-outlined text-[20px]">account_balance</span>
            </button>
        </div>
    </div>

</form>

<style>
/* Toggle Switch CSS */
input:checked ~ .toggle-bg { background-color: #333093; }
input:checked ~ .dot { transform: translateX(100%); }
</style>

<script>
let totalSelected = 0;
let isPartial = false;
let selectedBankName = '';

function updateSelection(checkbox, id) {
    const card = document.getElementById('card-' + id);
    if(checkbox.checked) {
        card.classList.add('border-[#333093]', 'ring-1', 'ring-[#333093]');
        card.classList.remove('border-[#c7c5d4]/40');
    } else {
        card.classList.remove('border-[#333093]', 'ring-1', 'ring-[#333093]');
        card.classList.add('border-[#c7c5d4]/40');
    }
    calculateTotal();
}

function calculateTotal() {
    if(isPartial) {
        let val = parseFloat(document.getElementById('partial_amount').value) || 0;
        totalSelected = val;
    } else {
        totalSelected = 0;
        document.querySelectorAll('.inv-checkbox:checked').forEach(cb => {
            totalSelected += parseFloat(cb.dataset.amount);
        });
    }
    document.getElementById('display-total').textContent = 'RM ' + totalSelected.toFixed(2);
}

function togglePartial() {
    isPartial = document.getElementById('partial_toggle').checked;
    const wrapper = document.getElementById('partial_input_wrapper');
    if(isPartial) {
        wrapper.classList.remove('hidden');
        // Calculate max from selected boxes
        let max = 0;
        document.querySelectorAll('.inv-checkbox:checked').forEach(cb => max += parseFloat(cb.dataset.amount));
        document.getElementById('partial_amount').max = max;
        if(!document.getElementById('partial_amount').value) {
            document.getElementById('partial_amount').value = max > 0 ? max.toFixed(2) : 50.00;
        }
    } else {
        wrapper.classList.add('hidden');
    }
    calculateTotal();
}

function updatePartialDisplay() {
    if(isPartial) calculateTotal();
}

function checkBankSelected() {
    const selected = document.querySelector('input[name="bank_selection"]:checked');
    const btn = document.getElementById('btn_step2');
    if (selected) {
        btn.disabled = false;
        btn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
        btn.classList.add('bg-[#333093]', 'hover:bg-[#5452b5]', 'text-white');
        
        // Find label text
        selectedBankName = selected.closest('.bg-white').querySelector('span.font-semibold').textContent;
    }
}

function setProgress(step) {
    const s1 = document.getElementById('step1-icon');
    const s2 = document.getElementById('step2-icon');
    const s3 = document.getElementById('step3-icon');
    const line = document.getElementById('progress-line');
    
    // Reset all
    [s1, s2, s3].forEach(el => {
        el.className = 'w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-bold text-sm shadow-sm transition-colors border border-gray-300';
    });
    
    if (step >= 1) {
        s1.className = 'w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm shadow-md transition-colors';
        s1.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span>';
        line.style.width = '0%';
    }
    if (step >= 2) {
        s2.className = 'w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-sm shadow-md transition-colors';
        s2.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span>';
        line.style.width = '50%';
    }
    if (step === 3) {
        s3.className = 'w-8 h-8 rounded-full bg-[#333093] text-white flex items-center justify-center font-bold text-sm shadow-md transition-colors';
        s3.innerHTML = '3';
        line.style.width = '100%';
    } else {
        document.getElementById(`step${step}-icon`).className = 'w-8 h-8 rounded-full bg-[#333093] text-white flex items-center justify-center font-bold text-sm shadow-md transition-colors';
        document.getElementById(`step${step}-icon`).innerHTML = step;
    }
}

function nextStep(step) {
    if(step === 2) {
        // Validate Step 1
        const checked = document.querySelectorAll('.inv-checkbox:checked');
        if(checked.length === 0) {
            alert('Sila pilih sekurang-kurangnya satu invois.');
            return;
        }
        if(isPartial) {
            const amt = parseFloat(document.getElementById('partial_amount').value);
            if(isNaN(amt) || amt < 50) {
                alert('Jumlah minimum untuk bayaran separa ialah RM 50.00');
                return;
            }
        }
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');
        setProgress(2);
    }
    if(step === 3) {
        const bank = document.querySelector('input[name="bank_selection"]:checked');
        if(!bank) return;
        
        // Prepare final data
        let ids = [];
        document.querySelectorAll('.inv-checkbox:checked').forEach(cb => ids.push(cb.value));
        
        document.getElementById('final_invoices').value = ids.join(',');
        document.getElementById('final_amount').value = totalSelected;
        document.getElementById('final_bank').value = bank.value;
        
        // Update summary UI
        document.getElementById('confirm_total').textContent = 'RM ' + totalSelected.toFixed(2);
        document.getElementById('confirm_count').textContent = ids.length + ' invois terpilih';
        document.getElementById('confirm_bank').textContent = '(' + selectedBankName + ')';
        
        document.getElementById('step2').classList.add('hidden');
        document.getElementById('step3').classList.remove('hidden');
        setProgress(3);
    }
}

function prevStep(step) {
    if(step === 1) {
        document.getElementById('step2').classList.add('hidden');
        document.getElementById('step1').classList.remove('hidden');
        setProgress(1);
    }
    if(step === 2) {
        document.getElementById('step3').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');
        setProgress(2);
    }
}

// Init
calculateTotal();
</script>

<?php renderParentFooter(); ?>
