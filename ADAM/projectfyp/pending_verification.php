<?php
// pending_verification.php
// No session needed — user is NOT logged in
$role = isset($_GET['role']) ? $_GET['role'] : 'parent';
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menunggu Pengesahan - Sistem Pengurusan Kanak-Kanak</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f0f8ff;
            margin: 0; 
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .header {
            background-color: #ffb6c1;
            width: 100%;
            padding: 20px 0;
            text-align: center;
            color: #333;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 550px;
            width: 90%;
            padding: 45px 40px;
            text-align: center;
            border-top: 8px solid #f0ad4e;
        }

        .hourglass {
            font-size: 64px;
            display: inline-block;
            animation: pulse 2s ease-in-out infinite;
            margin-bottom: 10px;
        }

        @keyframes pulse {
            0%, 100% { 
                transform: scale(1) rotate(0deg); 
                opacity: 1;
            }
            25% {
                transform: scale(1.1) rotate(10deg);
                opacity: 0.9;
            }
            50% { 
                transform: scale(1) rotate(0deg); 
                opacity: 0.7;
            }
            75% {
                transform: scale(1.1) rotate(-10deg);
                opacity: 0.9;
            }
        }

        .card h2 {
            color: #e6930d;
            font-size: 22px;
            margin: 10px 0 5px 0;
        }

        .card .role-subtitle {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
            font-style: italic;
        }

        .card .message {
            color: #555;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 25px;
            padding: 0 10px;
        }

        .contact-box {
            background-color: #e8f4fd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: left;
        }

        .contact-box .contact-label {
            font-weight: bold;
            color: #84b6f4;
            font-size: 14px;
            margin-bottom: 12px;
            text-align: center;
        }

        .contact-box .contact-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
            color: #444;
        }

        .contact-box .contact-item:last-child {
            margin-bottom: 0;
        }

        .contact-box .contact-icon {
            font-size: 18px;
        }

        .divider {
            border: none;
            border-top: 1px solid #eee;
            margin: 20px 0;
        }

        .btn-back {
            display: inline-block;
            background-color: #84b6f4;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn-back:hover {
            background-color: #6a9bd8;
        }

        .footer-text {
            color: #999;
            font-size: 13px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🧸 Sistem Pengurusan Kanak-Kanak Terpadu</h1>
    </div>

    <div class="card">
        <div class="hourglass">⏳</div>

        <h2>Pendaftaran Anda Sedang Diproses</h2>

        <p class="role-subtitle">
            <?php if ($role === 'teacher'): ?>
                Pendaftaran guru anda sedang dalam semakan
            <?php else: ?>
                Pendaftaran ibu bapa anda sedang dalam semakan
            <?php endif; ?>
        </p>

        <p class="message">
            Pihak pentadbiran sedang menyemak maklumat anda. 
            Proses ini biasanya mengambil masa <strong>1-2 hari bekerja</strong>. 
            Anda akan dimaklumkan setelah akaun anda diaktifkan.
        </p>

        <div class="contact-box">
            <div class="contact-label">📞 Untuk pertanyaan lanjut, sila hubungi kami</div>
            <div class="contact-item">
                <span class="contact-icon">📧</span>
                <span>Email: <strong>info@tadika-kiddiecare.com</strong></span>
            </div>
            <div class="contact-item">
                <span class="contact-icon">📱</span>
                <span>Telefon: <strong>03-12345678</strong></span>
            </div>
        </div>

        <hr class="divider">

        <a href="login.php" class="btn-back">Kembali ke Log Masuk</a>

        <p class="footer-text">Terima kasih atas kesabaran anda 🙏</p>
    </div>

</body>
</html>
