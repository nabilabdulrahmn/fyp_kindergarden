<?php
// complete_profile_teacher.php — Step 2: Teacher Profile Completion
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user_id = $_SESSION['user_id'];
    
    $full_name = trim($_POST['full_name']);
    $ic_number = trim($_POST['ic_number']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $qualification = trim($_POST['qualification']);
    $experience = trim($_POST['experience']);
    $specialization = trim($_POST['specialization']);

    $conn->begin_transaction();

    try {
        // Handle teacher file uploads
        $upload_dir = 'uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg'];
        
        $qualification_cert_path = null;
        $resume_path = null;
        $ic_copy_path = null;
        $username = $_SESSION['username'];

        // 1. Qualification Certificate
        if (isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['cert_file']['name'];
            $file_tmp = $_FILES['cert_file']['tmp_name'];
            $file_size = $_FILES['cert_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed_exts) && $file_size <= 5242880) {
                $new_filename = 'teacher_' . $username . '_cert_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $qualification_cert_path = $dest_path;
                }
            }
        }

        // 2. Resume / CV
        if (isset($_FILES['resume_file']) && $_FILES['resume_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['resume_file']['name'];
            $file_tmp = $_FILES['resume_file']['tmp_name'];
            $file_size = $_FILES['resume_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed_exts) && $file_size <= 5242880) {
                $new_filename = 'teacher_' . $username . '_resume_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $resume_path = $dest_path;
                }
            }
        }

        // 3. Copy of IC
        if (isset($_FILES['ic_copy_file']) && $_FILES['ic_copy_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['ic_copy_file']['name'];
            $file_tmp = $_FILES['ic_copy_file']['tmp_name'];
            $file_size = $_FILES['ic_copy_file']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($file_ext, $allowed_exts) && $file_size <= 5242880) {
                $new_filename = 'teacher_' . $username . '_ic_' . time() . '.' . $file_ext;
                $dest_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $ic_copy_path = $dest_path;
                }
            }
        }

        // UPDATE users status
        $user_stmt = $conn->prepare("UPDATE users SET status = 'Pending' WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_stmt->close();

        // INSERT into teachers
        $teacher_stmt = $conn->prepare("INSERT INTO teachers (user_id, full_name, ic_number, ic_copy_path, phone_number, email, address, qualification, qualification_cert_path, resume_path, experience, specialization, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $teacher_stmt->bind_param("isssssssssss", $user_id, $full_name, $ic_number, $ic_copy_path, $phone, $email, $address, $qualification, $qualification_cert_path, $resume_path, $experience, $specialization);
        $teacher_stmt->execute();
        $teacher_stmt->close();

        // INSERT into system_logs
        $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, 'Teacher Profile Completion', 'Success', ?)");
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iss", $user_id, $username, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();

        // Mock email notification to admin
        $email_subject = "New Teacher Registration - Pending Verification";
        $email_message = "A new teacher ($full_name) has completed their profile.\nUsername: $username\nQualification: $qualification\nPlease verify their account.";
        @mail('admin@tadika-kiddiecare.com', $email_subject, $email_message, "From: no-reply@tadika-kiddiecare.com");

        // Destroy session so they must wait for admin approval
        session_destroy();

        echo "<script>alert('Profil berjaya dilengkapkan! Maklumat anda sedang menunggu pengesahan oleh pihak pentadbiran.'); window.location.href='pending_verification.php?role=teacher';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Ralat semasa melengkapkan profil. Sila cuba lagi.";
    }
}
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lengkapkan Profil Guru - Sistem Pengurusan Kanak-Kanak</title>
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

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .form-box { 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            width: 90%;
            max-width: 650px;
            margin-bottom: 40px;
            border-top: 5px solid #ffb347;
        }

        .form-box h2 {
            color: #ffb347;
            margin-top: 0;
            text-align: center;
            font-size: 22px;
        }

        .section-title {
            color: #ffb347;
            border-bottom: 2px solid #fff3e0;
            padding-bottom: 5px;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        input[type="text"], input[type="password"], input[type="email"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input:focus, textarea:focus {
            outline: none;
            border-color: #ffb347;
            box-shadow: 0 0 0 3px rgba(255, 179, 71, 0.15);
        }

        textarea {
            resize: vertical;
        }

        input[type="file"] {
            padding: 8px;
            background: #f8f9fa;
            border: 1px dashed #ccc;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        button {
            background-color: #ffb347;
            color: white;
            padding: 14px 20px;
            margin: 25px 0 10px 0;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background-color: #e6a030;
            transform: translateY(-1px);
        }

        .toggle-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #555;
            text-decoration: none;
            font-size: 14px;
        }

        .toggle-link:hover { color: #ffb347; }

        .msg { 
            color: #d9534f; 
            text-align: center; 
            margin-bottom: 15px; 
            font-weight: bold;
            background-color: #fdf2f2;
            padding: 10px;
            border-radius: 6px;
        }

        @media (max-width: 600px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
            .form-box {
                padding: 25px;
            }
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>🧸 Sistem Pengurusan Kanak-Kanak Terpadu</h1>
    </div>

    <div class="form-box">
        <h2>Langkah 2: Lengkapkan Profil Guru</h2>
        <?php if(isset($msg)) echo "<div class='msg'>$msg</div>"; ?>
        <form method="POST" action="complete_profile_teacher.php" enctype="multipart/form-data">
            
            <div class="section-title">👩‍🏫 Maklumat Peribadi Guru</div>
            <div class="form-group">
                <label>Nama Penuh</label>
                <input type="text" name="full_name" placeholder="Nama penuh seperti dalam IC" required>
            </div>
            <div class="grid-container">
                <div class="form-group">
                    <label>No. Kad Pengenalan (IC)</label>
                    <input type="text" name="ic_number" placeholder="Cth: 800101-14-1234" required>
                </div>
                <div class="form-group">
                    <label>No. Telefon</label>
                    <input type="text" name="phone" placeholder="Cth: 012-3456789" required>
                </div>
            </div>
            <div class="form-group">
                <label>Emel</label>
                <input type="email" name="email" placeholder="Cth: nama@email.com" required>
            </div>
            <div class="form-group">
                <label>Alamat</label>
                <textarea name="address" rows="3" placeholder="Alamat penuh" required></textarea>
            </div>

            <div class="section-title">📚 Maklumat Profesional</div>
            <div class="form-group">
                <label>Kelayakan</label>
                <input type="text" name="qualification" placeholder="Cth: Diploma Pendidikan Awal Kanak-kanak" required>
            </div>
            <div class="grid-container">
                <div class="form-group">
                    <label>Pengalaman</label>
                    <input type="text" name="experience" placeholder="Cth: 3 tahun">
                </div>
                <div class="form-group">
                    <label>Pengkhususan</label>
                    <input type="text" name="specialization" placeholder="Cth: Pendidikan Islam, Matematik Awal">
                </div>
            </div>

            <!-- Section D: Muat Naik Dokumen Bukti -->
            <div class="section-title">📂 Muat Naik Dokumen Sokongan</div>
            <div class="grid-container">
                <div class="form-group">
                    <label>Sijil Kelayakan Akademik (Diploma/Degree)</label>
                    <input type="file" name="cert_file" accept=".pdf,.png,.jpg,.jpeg">
                </div>
                <div class="form-group">
                    <label>Resume / CV</label>
                    <input type="file" name="resume_file" accept=".pdf,.png,.jpg,.jpeg">
                </div>
            </div>
            <div class="form-group">
                <label>Salinan Kad Pengenalan (IC)</label>
                <input type="file" name="ic_copy_file" accept=".pdf,.png,.jpg,.jpeg">
            </div>



            <button type="submit" name="register">Hantar Profil Guru</button>
        </form>
    </div>

</body>
</html>
