<?php
/**
 * Sandbox Checkout — Halaman checkout pembayaran FPX (simulasi)
 * Halaman ini berdiri sendiri (standalone) tanpa layout admin/ibu bapa.
 */
session_start();

// ─── POST: Proses pembayaran ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db.php';
    require_once 'includes/payment_gateway.php';

    $transactionId = $_POST['transaction_id'] ?? '';
    $bank          = $_POST['bank'] ?? '';

    $gateway = new SandboxPaymentGateway($conn);
    $result  = $gateway->processPayment($transactionId, $bank);

    // Simpan hasil dalam sesi
    $_SESSION['sandbox_payment_result'] = $result;

    // Dapatkan return_url dari request_payload
    $txnData   = $gateway->verifyTransaction($transactionId);
    $returnUrl = $txnData['request']['return_url'] ?? 'parent/bayaran.php';

    // Redirect ke halaman asal
    $separator = (strpos($returnUrl, '?') !== false) ? '&' : '?';
    header('Location: ' . $returnUrl . $separator . 'txn=' . urlencode($transactionId));
    exit;
}

// ─── GET: Papar halaman checkout ───────────────────────────────────────
$token = $_GET['token'] ?? '';
$txn   = $_GET['txn'] ?? '';

$validSession = true;
$errorMessage = '';

if (empty($token) || empty($txn)) {
    $validSession = false;
    $errorMessage = 'Parameter token atau transaksi tidak sah.';
} elseif (!isset($_SESSION['sandbox_payment_token']) || !isset($_SESSION['sandbox_txn_id'])) {
    $validSession = false;
    $errorMessage = 'Sesi pembayaran telah tamat tempoh. Sila cuba semula.';
} elseif ($token !== $_SESSION['sandbox_payment_token'] || $txn !== $_SESSION['sandbox_txn_id']) {
    $validSession = false;
    $errorMessage = 'Token pengesahan tidak sepadan. Sila mulakan pembayaran semula.';
}

// Dapatkan maklumat transaksi jika sesi sah
$txnAmount = '0.00';
$txnId     = htmlspecialchars($txn);
if ($validSession) {
    require_once 'db.php';
    require_once 'includes/payment_gateway.php';
    $gateway = new SandboxPaymentGateway($conn);
    $txnData = $gateway->verifyTransaction($txn);
    $txnAmount = number_format((float)($txnData['amount'] ?? 0), 2);
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FPX Sandbox — Pembayaran Dalam Talian</title>
    <style>
        /* ── Reset & Base ─────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: #e8ecf1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 0;
        }

        /* ── Sandbox Warning Banner ──────────────────────── */
        .sandbox-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9999;
            background: linear-gradient(135deg, #ff9800, #ffc107);
            color: #4a2800;
            text-align: center;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(255, 152, 0, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .sandbox-banner .pulse-dot {
            width: 10px; height: 10px;
            background: #d32f2f;
            border-radius: 50%;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }

        /* ── Main Card ───────────────────────────────────── */
        .checkout-wrapper {
            margin-top: 64px;
            width: 100%;
            max-width: 480px;
            padding: 16px;
        }
        .checkout-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        /* ── FPX Header ──────────────────────────────────── */
        .fpx-header {
            background: linear-gradient(135deg, #003d6b 0%, #00567a 50%, #006d8e 100%);
            color: #fff;
            padding: 24px 28px 20px;
            position: relative;
        }
        .fpx-header::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ffc107, #ff9800, #ffc107);
        }
        .fpx-logo {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }
        .fpx-logo span {
            color: #ffc107;
        }
        .fpx-subtitle {
            font-size: 12px;
            opacity: 0.75;
            letter-spacing: 0.5px;
            margin-bottom: 18px;
        }
        .txn-details {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .txn-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .txn-row .label { opacity: 0.75; }
        .txn-row .value { font-weight: 600; }
        .txn-amount {
            font-size: 28px;
            font-weight: 800;
            margin-top: 8px;
            text-align: right;
        }
        .txn-amount small {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.8;
        }

        /* ── Body Section ────────────────────────────────── */
        .checkout-body { padding: 24px 28px 28px; }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #003d6b;
            margin-bottom: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-title::before {
            content: '';
            width: 4px; height: 18px;
            background: linear-gradient(180deg, #003d6b, #0077b6);
            border-radius: 2px;
        }

        /* ── Bank Selector ───────────────────────────────── */
        .bank-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 24px;
        }
        .bank-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fafbfc;
        }
        .bank-option:hover {
            border-color: #90caf9;
            background: #f0f7ff;
            transform: translateX(4px);
        }
        .bank-option.selected {
            border-color: #003d6b;
            background: #e8f4fd;
            box-shadow: 0 0 0 1px #003d6b;
        }
        .bank-option input[type="radio"] { display: none; }
        .bank-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 13px;
            color: #fff;
            flex-shrink: 0;
            letter-spacing: -0.5px;
        }
        .bank-name {
            font-size: 14px;
            font-weight: 600;
            color: #1a2b3c;
        }
        .bank-check {
            margin-left: auto;
            width: 22px; height: 22px;
            border: 2px solid #cdd5de;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .bank-option.selected .bank-check {
            border-color: #003d6b;
            background: #003d6b;
        }
        .bank-option.selected .bank-check::after {
            content: '✓';
            color: #fff;
            font-size: 13px;
            font-weight: 700;
        }

        /* ── Mock Login ──────────────────────────────────── */
        .login-section { margin-bottom: 24px; }
        .input-group {
            margin-bottom: 14px;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }
        .input-group input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e6ed;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #fafbfc;
            color: #1a2b3c;
        }
        .input-group input:focus {
            outline: none;
            border-color: #003d6b;
            box-shadow: 0 0 0 3px rgba(0, 61, 107, 0.1);
            background: #fff;
        }
        .input-group input::placeholder {
            color: #a0aec0;
        }

        /* ── Pay Button ──────────────────────────────────── */
        .pay-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #003d6b, #0077b6);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }
        .pay-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 61, 107, 0.35);
        }
        .pay-btn:active {
            transform: translateY(0);
        }
        .pay-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── Processing Overlay ──────────────────────────── */
        .processing-overlay {
            display: none;
            padding: 48px 28px;
            text-align: center;
        }
        .processing-overlay.active { display: block; }

        .spinner {
            width: 56px; height: 56px;
            border: 4px solid #e0e6ed;
            border-top-color: #003d6b;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .processing-text {
            font-size: 16px;
            font-weight: 600;
            color: #003d6b;
            margin-bottom: 6px;
        }
        .processing-sub {
            font-size: 13px;
            color: #718096;
            margin-bottom: 24px;
        }

        .progress-bar-track {
            width: 100%;
            height: 8px;
            background: #e0e6ed;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #003d6b, #0077b6, #00b4d8);
            border-radius: 4px;
            transition: width 0.1s linear;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 20px;
            font-size: 12px;
            color: #718096;
        }
        .lock-icon {
            font-size: 14px;
        }

        /* ── Footer ──────────────────────────────────────── */
        .checkout-footer {
            text-align: center;
            padding: 16px;
            font-size: 11px;
            color: #8896a7;
            line-height: 1.6;
        }
        .checkout-footer .sandbox-tag {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        /* ── Error State ─────────────────────────────────── */
        .error-card {
            text-align: center;
            padding: 48px 28px;
        }
        .error-icon {
            width: 64px; height: 64px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
        }
        .error-title {
            font-size: 18px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 8px;
        }
        .error-desc {
            font-size: 14px;
            color: #718096;
            line-height: 1.6;
        }

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 500px) {
            .checkout-wrapper { padding: 10px; }
            .fpx-header { padding: 20px 20px 16px; }
            .checkout-body { padding: 20px 20px 24px; }
            .txn-amount { font-size: 24px; }
            .fpx-logo { font-size: 24px; }
            .bank-option { padding: 10px 12px; }
            .bank-icon { width: 36px; height: 36px; font-size: 11px; }
        }
    </style>
</head>
<body>

<!-- ─── Sandbox Banner ────────────────────────────────────────────── -->
<div class="sandbox-banner">
    <div class="pulse-dot"></div>
    ⚠️ SANDBOX MODE — Tiada pembayaran sebenar akan diproses
</div>

<div class="checkout-wrapper">
    <div class="checkout-card">

<?php if (!$validSession): ?>
        <!-- ─── Error State ──────────────────────────────── -->
        <div class="error-card">
            <div class="error-icon">❌</div>
            <div class="error-title">Sesi Tidak Sah</div>
            <div class="error-desc"><?php echo htmlspecialchars($errorMessage); ?></div>
        </div>
<?php else: ?>
        <!-- ─── FPX Header ───────────────────────────────── -->
        <div class="fpx-header">
            <div class="fpx-logo">FPX<span>pay</span></div>
            <div class="fpx-subtitle">Financial Process Exchange — Sandbox Environment</div>
            <div class="txn-details">
                <div class="txn-row">
                    <span class="label">Pedagang</span>
                    <span class="value">Taska Ceria Childcare</span>
                </div>
                <div class="txn-row">
                    <span class="label">No. Transaksi</span>
                    <span class="value"><?php echo $txnId; ?></span>
                </div>
            </div>
            <div class="txn-amount">
                <small>RM</small> <?php echo $txnAmount; ?>
            </div>
        </div>

        <!-- ─── Checkout Form ────────────────────────────── -->
        <div class="checkout-body" id="checkoutForm">
            <!-- Bank Selector -->
            <div class="section-title">Pilih Bank Anda</div>
            <div class="bank-list" id="bankList">
                <label class="bank-option" data-bank="Maybank2U">
                    <input type="radio" name="bank" value="Maybank2U">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #ffc107, #f9a825);">M</div>
                    <div class="bank-name">Maybank2U</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="CIMB Clicks">
                    <input type="radio" name="bank" value="CIMB Clicks">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #d32f2f, #b71c1c);">CB</div>
                    <div class="bank-name">CIMB Clicks</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="RHB Now">
                    <input type="radio" name="bank" value="RHB Now">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #1565c0, #0d47a1);">RHB</div>
                    <div class="bank-name">RHB Now</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="Public Bank">
                    <input type="radio" name="bank" value="Public Bank">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #e91e63, #ad1457);">PB</div>
                    <div class="bank-name">Public Bank</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="HLB Connect">
                    <input type="radio" name="bank" value="HLB Connect">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #2e7d32, #1b5e20);">HL</div>
                    <div class="bank-name">HLB Connect</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="AmBank">
                    <input type="radio" name="bank" value="AmBank">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #00838f, #006064);">AM</div>
                    <div class="bank-name">AmBank</div>
                    <div class="bank-check"></div>
                </label>
                <label class="bank-option" data-bank="Bank Islam">
                    <input type="radio" name="bank" value="Bank Islam">
                    <div class="bank-icon" style="background: linear-gradient(135deg, #4a148c, #6a1b9a);">BI</div>
                    <div class="bank-name">Bank Islam</div>
                    <div class="bank-check"></div>
                </label>
            </div>

            <!-- Mock Login -->
            <div class="section-title">Log Masuk Bank</div>
            <div class="login-section">
                <div class="input-group">
                    <label for="bankUsername">Nama Pengguna / ID</label>
                    <input type="text" id="bankUsername" placeholder="Masukkan nama pengguna anda" autocomplete="off">
                </div>
                <div class="input-group">
                    <label for="bankPassword">Kata Laluan</label>
                    <input type="password" id="bankPassword" placeholder="Masukkan kata laluan anda" autocomplete="off">
                </div>
            </div>

            <!-- Pay Button -->
            <button type="button" class="pay-btn" id="payBtn" disabled>
                🔒 Bayar Sekarang — RM <?php echo $txnAmount; ?>
            </button>

            <div class="security-badge">
                <span class="lock-icon">🔐</span>
                Transaksi dilindungi dengan penyulitan 256-bit SSL
            </div>
        </div>

        <!-- ─── Processing Overlay ───────────────────────── -->
        <div class="processing-overlay" id="processingOverlay">
            <div class="spinner"></div>
            <div class="processing-text">Memproses pembayaran anda...</div>
            <div class="processing-sub">Sila tunggu, jangan tutup halaman ini</div>
            <div class="progress-bar-track">
                <div class="progress-bar-fill" id="progressBar"></div>
            </div>
            <div class="security-badge">
                <span class="lock-icon">🔐</span>
                Sambungan selamat ke bank anda
            </div>
        </div>
<?php endif; ?>

    </div>

    <!-- ─── Footer ───────────────────────────────────────── -->
    <div class="checkout-footer">
        <div class="sandbox-tag">🧪 PERSEKITARAN UJIAN</div><br>
        Ini adalah persekitaran ujian (sandbox). Tiada wang sebenar dipindahkan.<br>
        Sebarang maklumat yang dimasukkan tidak akan disimpan atau diproses.
    </div>
</div>

<?php if ($validSession): ?>
<script>
(function() {
    const bankOptions = document.querySelectorAll('.bank-option');
    const payBtn      = document.getElementById('payBtn');
    const checkoutForm     = document.getElementById('checkoutForm');
    const processingOverlay = document.getElementById('processingOverlay');
    const progressBar  = document.getElementById('progressBar');

    let selectedBank = '';

    // ── Bank selection ──
    bankOptions.forEach(function(option) {
        option.addEventListener('click', function() {
            bankOptions.forEach(function(o) { o.classList.remove('selected'); });
            option.classList.add('selected');
            option.querySelector('input[type="radio"]').checked = true;
            selectedBank = option.getAttribute('data-bank');
            updatePayButton();
        });
    });

    // ── Enable pay button when bank + credentials filled ──
    const usernameInput = document.getElementById('bankUsername');
    const passwordInput = document.getElementById('bankPassword');

    usernameInput.addEventListener('input', updatePayButton);
    passwordInput.addEventListener('input', updatePayButton);

    function updatePayButton() {
        var bankChosen = selectedBank !== '';
        var hasUsername = usernameInput.value.trim() !== '';
        var hasPassword = passwordInput.value.trim() !== '';
        payBtn.disabled = !(bankChosen && hasUsername && hasPassword);
    }

    // ── Pay button click ──
    payBtn.addEventListener('click', function() {
        if (payBtn.disabled) return;

        // Hide form, show processing
        checkoutForm.style.display = 'none';
        processingOverlay.classList.add('active');

        // Animate progress bar over ~3 seconds
        var progress = 0;
        var interval = setInterval(function() {
            progress += 1;
            progressBar.style.width = progress + '%';
            if (progress >= 100) {
                clearInterval(interval);
                // Submit the form
                submitPayment();
            }
        }, 30);
    });

    function submitPayment() {
        // Create and submit a hidden form
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'sandbox_checkout.php';

        var txnInput = document.createElement('input');
        txnInput.type = 'hidden';
        txnInput.name = 'transaction_id';
        txnInput.value = '<?php echo $txnId; ?>';
        form.appendChild(txnInput);

        var bankInput = document.createElement('input');
        bankInput.type = 'hidden';
        bankInput.name = 'bank';
        bankInput.value = selectedBank;
        form.appendChild(bankInput);

        document.body.appendChild(form);
        form.submit();
    }
})();
</script>
<?php endif; ?>

</body>
</html>
