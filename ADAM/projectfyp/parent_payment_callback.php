<?php
session_start();
require_once 'db.php';
require_once 'auth_guard.php';
require_once 'includes/parent_layout.php';
require_once 'includes/payment_gateway.php';

sahkan_peranan('parent');

$status = $_GET['status'] ?? 'failed';
$tx_id = $_GET['tx_id'] ?? '';

$gw = new SandboxPaymentGateway($conn);
$payment_status = $gw->verifyTransaction($tx_id);

$success = ($payment_status && $payment_status['status'] === 'Success');
$amount = $payment_status['amount'] ?? 0;
$ref = $payment_status['ref'] ?? $tx_id;

renderParentHeader('Status Pembayaran');
?>

<div class="max-w-md mx-auto mt-10">
    <div class="bg-white rounded-2xl shadow-lg border border-[#c7c5d4]/20 overflow-hidden text-center">
        
        <?php if ($success): ?>
            <div class="bg-emerald-50 py-8 px-6 border-b border-emerald-100">
                <div class="w-20 h-20 mx-auto bg-emerald-500 rounded-full flex items-center justify-center text-white shadow-lg shadow-emerald-500/30 mb-4 animate-[bounce_1s_ease-in-out_1]">
                    <span class="material-symbols-outlined text-[40px]">check_circle</span>
                </div>
                <h2 class="text-2xl font-black text-emerald-700 mb-1">Pembayaran Berjaya!</h2>
                <p class="text-emerald-600 font-medium">Terima kasih, transaksi anda telah disahkan.</p>
            </div>
            
            <div class="p-8">
                <div class="space-y-4 text-left text-sm mb-8 bg-gray-50 p-6 rounded-xl border border-gray-100">
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-gray-500">Jumlah Dibayar</span>
                        <span class="font-bold text-gray-800 text-lg">RM <?= number_format($amount, 2) ?></span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-gray-500">No. Rujukan (FPX)</span>
                        <span class="font-mono text-gray-800 font-medium"><?= htmlspecialchars($ref) ?></span>
                    </div>
                    <div class="flex justify-between items-center pb-3 border-b border-gray-200">
                        <span class="text-gray-500">Tarikh/Masa</span>
                        <span class="font-medium text-gray-800"><?= date('d M Y, H:i') ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Status Resit</span>
                        <span class="text-blue-600 font-bold bg-blue-50 px-2 py-0.5 rounded">Sedang Dijana</span>
                    </div>
                </div>
                
                <a href="parent_payment_history.php" class="block w-full bg-[#333093] hover:bg-[#5452b5] text-white py-3.5 rounded-xl font-bold shadow-md transition-colors text-lg">
                    Lihat Sejarah Pembayaran
                </a>
            </div>
            
        <?php else: ?>
            <div class="bg-red-50 py-8 px-6 border-b border-red-100">
                <div class="w-20 h-20 mx-auto bg-red-500 rounded-full flex items-center justify-center text-white shadow-lg shadow-red-500/30 mb-4">
                    <span class="material-symbols-outlined text-[40px]">cancel</span>
                </div>
                <h2 class="text-2xl font-black text-red-700 mb-1">Pembayaran Gagal</h2>
                <p class="text-red-600 font-medium">Transaksi tidak dapat diproses.</p>
            </div>
            
            <div class="p-8">
                <p class="text-gray-600 mb-8 text-sm">Sila periksa baki akaun anda atau cuba gunakan kaedah pembayaran yang lain. Jika masalah berterusan, hubungi pihak admin.</p>
                
                <div class="flex flex-col gap-3">
                    <a href="parent_payment.php" class="w-full bg-[#333093] hover:bg-[#5452b5] text-white py-3.5 rounded-xl font-bold shadow-md transition-colors text-lg">
                        Cuba Lagi
                    </a>
                    <a href="parent_invoices.php" class="w-full bg-white border-2 border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 py-3 rounded-xl font-bold transition-colors">
                        Kembali ke Invois
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<?php renderParentFooter(); ?>
