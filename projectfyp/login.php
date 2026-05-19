<?php
// login.php
session_start();
require 'db.php'; 

// --- PROSES LOG MASUK ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username='$user'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($pass, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            header("Location: home.php");
            exit();
        } else {
            echo "<script>alert('Password Salah!');</script>";
        }
    } else {
        echo "<script>alert('Username tidak wujud!');</script>";
    }
}

// --- PROSES PENDAFTARAN ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user = $_POST['username'];
    $pass = $_POST['password'];
    $role = $_POST['role'];
    $hashed_password = password_hash($pass, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (username, password, role) VALUES ('$user', '$hashed_password', '$role')";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('Pendaftaran Berjaya! Sila Log Masuk.'); window.location.href='login.php';</script>";
    } else {
        echo "<script>alert('Ralat: Username mungkin sudah wujud.');</script>";
    }
}

$mode = isset($_GET['action']) && $_GET['action'] == 'register' ? 'register' : 'login';
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Login - Sistem Pengurusan Kanak-Kanak</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f0f8ff; /* Soft Alice Blue */
            margin: 0; 
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        /* Header Banner */
        .header {
            background-color: #ffb6c1; /* Light Pink Pastel */
            width: 100%;
            padding: 20px 0;
            text-align: center;
            color: #333;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .main-container {
            display: flex;
            gap: 30px;
            max-width: 900px;
            width: 90%;
            margin-top: 20px;
        }

        /* Form Box */
        .form-box { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            flex: 1;
        }
        
        .form-box h2 {
            color: #ff6f91;
            margin-top: 0;
            text-align: center;
        }

        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            margin: 8px 0 20px 0;
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            background-color: #84b6f4; /* Pastel Blue */
            color: white;
            padding: 12px 20px;
            margin: 8px 0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover {
            background-color: #6a9bd8;
        }

        .toggle-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #555;
            text-decoration: none;
            font-size: 14px;
        }
        .toggle-link:hover { color: #ff6f91; }

        /* Intro Box */
        .intro-box { 
            background-color: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            flex: 1;
        }
        
        .intro-box h3 { color: #84b6f4; margin-top: 0; }
        .intro-box ul { padding-left: 20px; color: #555;}
        .intro-box p { color: #555; line-height: 1.6;}
        
        /* Placeholder Gambar */
        .img-placeholder {
            width: 100%;
            height: 150px;
            background-color: #ffe4e1; /* Misty Rose */
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff6f91;
            font-weight: bold;
            margin-bottom: 20px;
            background-image: url('https://images.unsplash.com/photo-1516627145497-ae6968895b74?q=80&w=600&auto=format&fit=crop'); /* Gambar hiasan Taska */
            background-size: cover;
            background-position: center;
        }

        /* Responsiveness */
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🧸 Sistem Pengurusan Kanak-Kanak Terpadu</h1>
    </div>

    <div class="main-container">
        
        <div class="intro-box">
            <div class="img-placeholder"></div>
            <h3>Mengenai Pusat Penjagaan Kami</h3>
            <p>Platform bersepadu untuk pengurusan pusat penjagaan kanak-kanak. Beroperasi sejak tahun 2021, menawarkan perkhidmatan:</p>
            <ul>
                <li><strong>Taska:</strong> Penjagaan kanak-kanak untuk usia awal</li>
                <li><strong>Tadika:</strong> Program prasekolah berasaskan pendidikan</li>
                <li><strong>KAFA Care:</strong> Perkhidmatan transit dan pembelajaran agama</li>
            </ul>
            <p>Sila log masuk atau daftar akaun baru untuk memulakan urusan pendaftaran, semakan yuran, dan prestasi pelajar.</p>
        </div>

        <div class="form-box">
            <?php if ($mode == 'login'): ?>
                <h2>Log Masuk</h2>
                <form method="POST" action="login.php">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Masukkan ID Pengguna" required>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Masukkan Kata Laluan" required>
                    
                    <button type="submit" name="login">Log Masuk</button>
                    <a href="login.php?action=register" class="toggle-link">Belum ada akaun? <strong>Daftar Baru</strong></a>
                </form>

            <?php else: ?>
                <h2>Pendaftaran Baru</h2>
                <form method="POST" action="login.php?action=register">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="Cipta ID Pengguna" required>
                    
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Cipta Kata Laluan" required>
                    
                    <label>Daftar Sebagai:</label>
                    <select name="role" required>
                        <option value="parent">Ibu Bapa</option>
                        <option value="teacher">Guru</option>
                        <option value="admin">Admin</option>
                    </select>
                    
                    <button type="submit" name="register" style="background-color: #ff6f91;">Daftar Sekarang</button>
                    <a href="login.php" class="toggle-link">Batal & Kembali ke <strong>Log Masuk</strong></a>
                </form>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>