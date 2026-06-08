<?php
// login_process.php
// Proses Pengesahan & Penghalaan Automatik Berdasarkan Peranan
// Tiada dropdown pemilihan peranan - semuanya automatik dari kolum `role`

session_start();
require 'db.php';

// ============================================================
// FUNGSI PEMBANTU: Bersihkan input pengguna
// ============================================================
function bersih_input($conn, $data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// ============================================================
// PROSES LOG MASUK
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {

    $email    = bersih_input($conn, $_POST['email']);
    $password = $_POST['password']; // Jangan escape - akan disahkan dengan password_verify()

    // Validasi asas
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Sila masukkan emel dan kata laluan.';
        header('Location: login.php');
        exit();
    }

    // Cari pengguna berdasarkan emel
    $sql = "SELECT id, username, email, password, role, status FROM users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (!$result || mysqli_num_rows($result) === 0) {
        $_SESSION['login_error'] = 'Emel atau kata laluan tidak sah.';
        header('Location: login.php');
        exit();
    }

    $user = mysqli_fetch_assoc($result);

    // Sahkan kata laluan
    if (!password_verify($password, $user['password'])) {
        $_SESSION['login_error'] = 'Emel atau kata laluan tidak sah.';
        header('Location: login.php');
        exit();
    }

    // Semak status kelulusan akaun
    if ($user['status'] === 'pending') {
        $_SESSION['login_error'] = 'Akaun anda masih menunggu kelulusan admin.';
        header('Location: login.php');
        exit();
    }

    if ($user['status'] === 'rejected') {
        $_SESSION['login_error'] = 'Akaun anda telah ditolak oleh admin.';
        header('Location: login.php');
        exit();
    }

    // ============================================================
    // LOGIN BERJAYA - Set sesi & hala tuju berdasarkan peranan
    // ============================================================
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $user['role'];

    // Regenerate session ID untuk keselamatan (cegah session fixation)
    session_regenerate_id(true);

    // Penghalaan automatik berdasarkan peranan
    $redirect_map = array(
        'admin'   => 'admin_dashboard.php',
        'teacher' => 'teacher_dashboard.php',
        'parent'  => 'parent_dashboard.php'
    );

    $role = $user['role'];
    if (isset($redirect_map[$role])) {
        header('Location: ' . $redirect_map[$role]);
    } else {
        // Fallback jika peranan tidak dikenali
        header('Location: home.php');
    }
    exit();
}

// ============================================================
// PROSES LUPA KATA LALUAN - Jana token reset
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {

    $email = bersih_input($conn, $_POST['email']);

    if (empty($email)) {
        $_SESSION['forgot_error'] = 'Sila masukkan alamat emel anda.';
        header('Location: forgot_password.php');
        exit();
    }

    // Semak emel wujud
    $sql = "SELECT id FROM users WHERE email = '" . mysqli_real_escape_string($conn, $email) . "' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Jana token selamat 32-byte hex
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $sql_update = "UPDATE users SET reset_token = '" . mysqli_real_escape_string($conn, $token) . "', 
                       reset_token_expiry = '" . $expiry . "' 
                       WHERE id = " . (int)$row['id'];
        mysqli_query($conn, $sql_update);

        // Dalam sistem sebenar, hantar emel dengan pautan reset.
        // Untuk pembangunan, simpan pautan dalam sesi.
        $_SESSION['reset_link'] = 'reset_password.php?token=' . $token;
    }

    // Sentiasa papar mesej yang sama (cegah email enumeration)
    $_SESSION['forgot_success'] = 'Jika emel berdaftar, pautan reset telah dihantar.';
    header('Location: forgot_password.php');
    exit();
}

// ============================================================
// PROSES RESET KATA LALUAN - Sahkan token & kemas kini
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {

    $token        = bersih_input($conn, $_POST['token']);
    $new_password = $_POST['new_password'];
    $confirm      = $_POST['confirm_password'];

    // Validasi
    if (empty($token) || empty($new_password) || empty($confirm)) {
        $_SESSION['reset_error'] = 'Sila lengkapkan semua ruangan.';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }

    if ($new_password !== $confirm) {
        $_SESSION['reset_error'] = 'Kata laluan tidak sepadan.';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }

    if (strlen($new_password) < 8) {
        $_SESSION['reset_error'] = 'Kata laluan mesti sekurang-kurangnya 8 aksara.';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }

    // Sahkan token masih sah
    $sql = "SELECT id FROM users WHERE reset_token = '" . mysqli_real_escape_string($conn, $token) . "' 
            AND reset_token_expiry > NOW() LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (!$result || mysqli_num_rows($result) === 0) {
        $_SESSION['reset_error'] = 'Token tidak sah atau telah tamat tempoh.';
        header('Location: forgot_password.php');
        exit();
    }

    $row = mysqli_fetch_assoc($result);
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);

    // Kemas kini kata laluan & padamkan token
    $sql_update = "UPDATE users SET password = '" . mysqli_real_escape_string($conn, $hashed) . "', 
                   reset_token = NULL, reset_token_expiry = NULL 
                   WHERE id = " . (int)$row['id'];
    mysqli_query($conn, $sql_update);

    $_SESSION['login_success'] = 'Kata laluan berjaya dikemas kini. Sila log masuk.';
    header('Location: login.php');
    exit();
}

// Jika akses langsung tanpa POST
header('Location: login.php');
exit();
?>
