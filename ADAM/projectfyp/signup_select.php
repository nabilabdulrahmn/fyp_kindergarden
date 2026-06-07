<?php
// signup_select.php
session_start();
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Jenis Pendaftaran - Sistem Pengurusan Kanak-Kanak</title>
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

        .container {
            max-width: 700px;
            width: 90%;
            text-align: center;
        }

        .container h2 {
            color: #ff6f91;
            font-size: 26px;
            margin-bottom: 10px;
        }

        .container .subtitle {
            color: #777;
            font-size: 15px;
            margin-bottom: 35px;
        }

        .cards-wrapper {
            display: flex;
            gap: 25px;
            justify-content: center;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 40px 30px;
            flex: 1;
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card-parent {
            border-top: 5px solid #84b6f4;
        }

        .card-teacher {
            border-top: 5px solid #ffb347;
        }

        .card .icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #333;
        }

        .card p {
            margin: 0;
            font-size: 14px;
            color: #777;
            line-height: 1.5;
        }

        .card-parent:hover {
            border-top-color: #6a9bd8;
        }

        .card-teacher:hover {
            border-top-color: #e6a030;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 35px;
            color: #555;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link:hover {
            color: #ff6f91;
        }

        .back-link strong {
            color: #84b6f4;
        }

        .back-link:hover strong {
            color: #ff6f91;
        }

        @media (max-width: 768px) {
            .cards-wrapper {
                flex-direction: column;
            }
            .card {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🧸 Sistem Pengurusan Kanak-Kanak Terpadu</h1>
    </div>

    <div class="container">
        <h2>Pilih Jenis Pendaftaran</h2>
        <p class="subtitle">Sila pilih jenis akaun yang ingin anda daftarkan</p>

        <div class="cards-wrapper">
            <a href="register_account.php?role=parent" class="card card-parent" id="card-parent">
                <span class="icon">👨‍👩‍👧</span>
                <h3>Ibu Bapa / Penjaga</h3>
                <p>Daftar untuk mendaftarkan anak anda ke pusat penjagaan kami</p>
            </a>

            <a href="register_account.php?role=teacher" class="card card-teacher" id="card-teacher">
                <span class="icon">👩‍🏫</span>
                <h3>Guru / Pengajar</h3>
                <p>Daftar sebagai tenaga pengajar di pusat kami</p>
            </a>
        </div>

        <a href="login.php" class="back-link">Sudah ada akaun? <strong>Log Masuk</strong></a>
    </div>

</body>
</html>
