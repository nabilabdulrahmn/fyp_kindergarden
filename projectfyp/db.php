<?php
// db.php
// Sambungan Pangkalan Data - Sistem Pengurusan Kanak-Kanak Terpadu

// Matikan exception mysqli supaya tiada stack trace dipaparkan
mysqli_report(MYSQLI_REPORT_OFF);

$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'childcare_db';

$conn = @mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die('
    <!DOCTYPE html>
    <html lang="ms">
    <head>
        <meta charset="UTF-8">
        <title>Ralat Sistem</title>
        <style>
            body { font-family: "Segoe UI", sans-serif; background: #f0f4f3; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .error-box { background: #fff; border-left: 6px solid #e53935; border-radius: 12px; padding: 36px 40px; max-width: 520px; box-shadow: 0 4px 20px rgba(0,0,0,.08); text-align: center; }
            .error-box h1 { color: #c62828; font-size: 22px; margin: 0 0 12px; }
            .error-box p { color: #555; font-size: 14px; line-height: 1.7; margin: 0; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ Ralat Sistem</h1>
            <p>Pangkalan data tidak dapat diakses pada masa ini.<br>
            Sila pastikan pelayan <strong>XAMPP MySQL</strong> sedang berjalan dan cuba semula sebentar lagi.</p>
        </div>
    </body>
    </html>');
}
?>