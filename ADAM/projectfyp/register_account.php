<?php
// register_account.php — Step 1 of Two-Step Registration Flow
session_start();
require 'db.php';

// Force update users ENUM to include 'Incomplete'
try {
    $conn->query("ALTER TABLE `users` MODIFY COLUMN `status` ENUM('Incomplete', 'Pending', 'Active') NOT NULL DEFAULT 'Incomplete'");
} catch (Exception $e) {}

$role = isset($_GET['role']) && $_GET['role'] === 'teacher' ? 'teacher' : 'parent';
$role_title = $role === 'teacher' ? 'Guru / Pengajar' : 'Ibu Bapa / Penjaga';
$theme_color = $role === 'teacher' ? '#ffb347' : '#84b6f4';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_account'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $post_role = $_POST['role'];

    if ($password !== $confirm_password) {
        $msg = "Ralat: Kata laluan tidak sepadan.";
    } else {
        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $msg = "Ralat: ID Pengguna (Username) sudah wujud. Sila pilih yang lain.";
            $check_stmt->close();
        } else {
            $check_stmt->close();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // INSERT into users with status 'Incomplete'
            $user_stmt = $conn->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, 'Incomplete')");
            $user_stmt->bind_param("sss", $username, $hashed_password, $post_role);
            
            if ($user_stmt->execute()) {
                $user_stmt->close();
                echo "<script>alert('Akaun berjaya dicipta! Sila log masuk untuk melengkapkan profil anda.'); window.location.href='login.php';</script>";
                exit();
            } else {
                $msg = "Ralat semasa mencipta akaun. Sila cuba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cipta Akaun - Sistem Pengurusan Kanak-Kanak</title>
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
            margin-bottom: 30px;
        }

        .form-box { 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            box-sizing: border-box;
            border-top: 5px solid <?php echo $theme_color; ?>;
        }
        
        .form-box h2 {
            color: <?php echo $theme_color; ?>;
            margin-top: 0;
            text-align: center;
            font-size: 24px;
        }

        .subtitle {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: <?php echo $theme_color; ?>;
            box-shadow: 0 0 0 3px <?php echo $theme_color; ?>33;
        }

        button {
            background-color: <?php echo $theme_color; ?>;
            color: white;
            padding: 12px 20px;
            margin-top: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            transition: 0.3s;
        }

        button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #f5c6cb;
        }

        .toggle-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #555;
            text-decoration: none;
            font-size: 14px;
        }
        .toggle-link:hover { color: <?php echo $theme_color; ?>; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧸 Sistem Pengurusan Kanak-Kanak Terpadu</h1>
    </div>

    <div class="form-box">
        <h2>Langkah 1: Cipta Akaun</h2>
        <p class="subtitle">Mendaftar sebagai <strong><?php echo $role_title; ?></strong></p>
        
        <?php if(isset($msg)) echo "<div class='msg'>$msg</div>"; ?>
        
        <form method="POST" action="register_account.php?role=<?php echo $role; ?>">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="form-group">
                <label>ID Pengguna (Username)</label>
                <input type="text" name="username" placeholder="Cipta ID Pengguna" required>
            </div>
            
            <div class="form-group">
                <label>Kata Laluan</label>
                <input type="password" name="password" placeholder="Cipta Kata Laluan" required>
            </div>

            <div class="form-group">
                <label>Sahkan Kata Laluan</label>
                <input type="password" name="confirm_password" placeholder="Taip Semula Kata Laluan" required>
            </div>
            
            <button type="submit" name="register_account">Cipta Akaun & Seterusnya ➔</button>
            <a href="login.php" class="toggle-link">Sudah ada akaun? <strong>Log Masuk</strong></a>
        </form>
    </div>
</body>
</html>
