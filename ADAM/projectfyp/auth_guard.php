<?php
// auth_guard.php
// Pengawal Sesi - Include di setiap halaman yang memerlukan login
// Penggunaan: require 'auth_guard.php'; sahkan_peranan('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * Semak sama ada pengguna telah log masuk
 * Jika tidak, hala ke login.php
 */
function semak_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Sahkan peranan pengguna - hala ke halaman yang betul jika peranan tidak sepadan
 * @param string $required_role - Peranan yang diperlukan ('admin', 'teacher', 'parent')
 */
function sahkan_peranan($required_role) {
    semak_login();
    if ($_SESSION['role'] !== $required_role) {
        // Hala ke dashboard yang betul berdasarkan peranan sebenar
        $redirect = array(
            'admin'   => 'admin_dashboard.php',
            'teacher' => 'teacher_dashboard.php',
            'parent'  => 'parent_dashboard.php'
        );
        $role = $_SESSION['role'];
        if (isset($redirect[$role])) {
            header('Location: ' . $redirect[$role]);
        } else {
            header('Location: login.php');
        }
        exit();
    }
}

/**
 * Dapatkan ID parent dari jadual parents berdasarkan user_id sesi
 * @param object $conn - Sambungan MySQL
 * @return int|false - ID parent atau false
 */
function dapatkan_parent_id($conn) {
    $user_id = (int)$_SESSION['user_id'];
    $sql = "SELECT id FROM parents WHERE user_id = " . $user_id . " LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['id'];
    }
    return false;
}

/**
 * Dapatkan ID teacher dari jadual teachers berdasarkan user_id sesi
 * @param object $conn - Sambungan MySQL
 * @return int|false - ID teacher atau false
 */
function dapatkan_teacher_id($conn) {
    $user_id = (int)$_SESSION['user_id'];
    $sql = "SELECT id FROM teachers WHERE user_id = " . $user_id . " LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['id'];
    }
    return false;
}

/**
 * Dapatkan senarai class_id yang berkaitan dengan parent (melalui anak mereka)
 * @param object $conn - Sambungan MySQL
 * @param int $parent_id - ID parent
 * @return array - Senarai class_id
 */
function dapatkan_kelas_parent($conn, $parent_id) {
    $parent_id = (int)$parent_id;
    $sql = "SELECT DISTINCT sc.class_id 
            FROM student_classes sc 
            INNER JOIN students s ON sc.student_id = s.id 
            WHERE s.parent_id = " . $parent_id;
    $result = mysqli_query($conn, $sql);
    $kelas = array();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $kelas[] = (int)$row['class_id'];
        }
    }
    return $kelas;
}

/**
 * Dapatkan class_id yang diajar oleh guru
 * @param object $conn - Sambungan MySQL
 * @param int $teacher_id - ID teacher
 * @return int|false - class_id atau false
 */
function dapatkan_kelas_guru($conn, $teacher_id) {
    $teacher_id = (int)$teacher_id;
    $sql = "SELECT id FROM classes WHERE teacher_id = " . $teacher_id . " LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['id'];
    }
    return false;
}
?>
