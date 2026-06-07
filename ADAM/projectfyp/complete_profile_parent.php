<?php
// complete_profile_parent.php — Step 2: Parent Profile Completion
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header('Location: login.php');
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $user_id = $_SESSION['user_id'];
    
    // Parent info
    $full_name = trim($_POST['full_name']);
    $ic_number = trim($_POST['ic_number']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Child info
    $child_name = trim($_POST['child_name']);
    $mykid_number = trim($_POST['mykid_number']);
    $dob = $_POST['date_of_birth'];
    $module = $_POST['module'];
    $child_address = trim($_POST['child_address']);
    $postcode = trim($_POST['postcode']);
    $state = $_POST['state'];
    $health_record = trim($_POST['health_record']);
    $allergies = trim($_POST['allergies']);

    $conn->begin_transaction();

    try {
        // UPDATE users status to Pending
        $user_stmt = $conn->prepare("UPDATE users SET status = 'Pending' WHERE id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_stmt->close();

        // INSERT into parents
        $parent_stmt = $conn->prepare("INSERT INTO parents (user_id, full_name, ic_number, phone_number, email, address) VALUES (?, ?, ?, ?, ?, ?)");
        $parent_stmt->bind_param("isssss", $user_id, $full_name, $ic_number, $phone, $email, $address);
        $parent_stmt->execute();
        $new_parent_id = $conn->insert_id;
        $parent_stmt->close();

        // INSERT into students
        $student_stmt = $conn->prepare("INSERT INTO students (parent_id, full_name, mykid_number, date_of_birth, module, health_record, allergies, status, parent_name, parent_ic, parent_phone, parent_email, address, postcode, state) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?)");
        $student_stmt->bind_param("isssssssssssss", $new_parent_id, $child_name, $mykid_number, $dob, $module, $health_record, $allergies, $full_name, $ic_number, $phone, $email, $child_address, $postcode, $state);
        $student_stmt->execute();
        $new_student_id = $conn->insert_id;
        $student_stmt->close();

        // Handle file uploads for student documents
        $upload_dir = 'uploads/documents/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg'];
        $doc_types = [
            'mykid_file' => 'MyKid Copy',
            'birth_cert_file' => 'Birth Certificate',
            'health_file' => 'Health Record'
        ];
        foreach ($doc_types as $input_name => $db_type) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                $file_name = $_FILES[$input_name]['name'];
                $file_tmp = $_FILES[$input_name]['tmp_name'];
                $file_size = $_FILES[$input_name]['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_exts) && $file_size <= 5242880) { // Limit 5MB
                    $new_filename = 'student_' . $new_student_id . '_' . str_replace(' ', '_', strtolower($db_type)) . '_' . time() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $doc_stmt = $conn->prepare("INSERT INTO student_documents (student_id, document_type, file_path, original_filename, verification_status) VALUES (?, ?, ?, ?, 'Pending')");
                        $doc_stmt->bind_param("isss", $new_student_id, $db_type, $dest_path, $file_name);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
            }
        }

        // INSERT into enrollment_history
        $enroll_stmt = $conn->prepare("INSERT INTO enrollment_history (student_id, from_status, to_status, notes) VALUES (?, NULL, 'Pending', 'New registration via parent sign-up')");
        $enroll_stmt->bind_param("i", $new_student_id);
        $enroll_stmt->execute();
        $enroll_stmt->close();

        // INSERT into system_logs
        $username = $_SESSION['username'];
        $log_stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, action, status, ip_address) VALUES (?, ?, 'Parent Profile Completion', 'Success', ?)");
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("iss", $user_id, $username, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();

        $conn->commit();

        // Mock email notification to admin
        $email_subject = "New Parent Registration - Pending Verification";
        $email_message = "A new parent ($full_name) has completed their profile with child ($child_name).\nUsername: $username\nModule: $module\nPlease verify their account and enrollment.";
        @mail('admin@tadika-kiddiecare.com', $email_subject, $email_message, "From: no-reply@tadika-kiddiecare.com");

        // Destroy session to lock them out until approved
        session_destroy();
        
        echo "<script>alert('Profil berjaya dilengkapkan! Maklumat anda sedang diproses dan menunggu pengesahan oleh pihak pentadbiran.'); window.location.href='pending_verification.php?role=parent';</script>";
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
    <title>Lengkapkan Profil Ibu Bapa - Sistem Pengurusan Kanak-Kanak</title>
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
            border-top: 5px solid #84b6f4;
        }

        .form-box h2 {
            color: #ff6f91;
            margin-top: 0;
            text-align: center;
            font-size: 22px;
        }

        .section-title {
            color: #84b6f4;
            border-bottom: 2px solid #f0f8ff;
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

        input[type="text"], input[type="password"], input[type="email"], input[type="date"], select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #84b6f4;
            box-shadow: 0 0 0 3px rgba(132, 182, 244, 0.15);
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

        .full-width {
            grid-column: 1 / -1;
        }

        button {
            background-color: #ff6f91;
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
            background-color: #e65c7a;
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

        .toggle-link:hover { color: #84b6f4; }

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
        <h2>Langkah 2: Lengkapkan Profil Ibu Bapa & Anak</h2>
        <?php if(isset($msg)) echo "<div class='msg'>$msg</div>"; ?>
        <form method="POST" action="complete_profile_parent.php" enctype="multipart/form-data">
            
            <!-- Section A: Parent Info -->
            <div class="section-title">👤 Maklumat Ibu Bapa / Penjaga</div>
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
                <textarea name="address" rows="3" placeholder="Alamat penuh ibu bapa / penjaga" required></textarea>
            </div>



            <!-- Section C: Child Info -->
            <div class="section-title">👶 Maklumat Anak</div>
            <div class="form-group">
                <label>Nama Penuh Anak</label>
                <input type="text" name="child_name" placeholder="Nama penuh pelajar" required>
            </div>
            <div class="grid-container">
                <div class="form-group">
                    <label>No. MyKid / IC Anak</label>
                    <input type="text" name="mykid_number" placeholder="Cth: 200101-14-1234" required>
                </div>
                <div class="form-group">
                    <label>Tarikh Lahir</label>
                    <input type="date" name="date_of_birth" required>
                </div>
            </div>
            <div class="form-group">
                <label>Pilihan Modul</label>
                <select name="module" required>
                    <option value="" disabled selected>-- Pilih Modul --</option>
                    <option value="Taska">Taska (0-4 Tahun)</option>
                    <option value="Tadika">Tadika (5-6 Tahun)</option>
                    <option value="KAFA Care">KAFA Care</option>
                </select>
            </div>
            <div class="form-group">
                <label>Alamat Anak (jika berbeza)</label>
                <textarea name="child_address" rows="2" placeholder="Kosongkan jika sama dengan alamat ibu bapa"></textarea>
            </div>
            <div class="grid-container">
                <div class="form-group">
                    <label>Poskod</label>
                    <input type="text" name="postcode" placeholder="Cth: 50000">
                </div>
                <div class="form-group">
                    <label>Negeri</label>
                    <select name="state">
                        <option value="" disabled selected>-- Pilih Negeri --</option>
                        <option value="Johor">Johor</option>
                        <option value="Kedah">Kedah</option>
                        <option value="Kelantan">Kelantan</option>
                        <option value="Melaka">Melaka</option>
                        <option value="Negeri Sembilan">Negeri Sembilan</option>
                        <option value="Pahang">Pahang</option>
                        <option value="Perak">Perak</option>
                        <option value="Perlis">Perlis</option>
                        <option value="Pulau Pinang">Pulau Pinang</option>
                        <option value="Sabah">Sabah</option>
                        <option value="Sarawak">Sarawak</option>
                        <option value="Selangor">Selangor</option>
                        <option value="Terengganu">Terengganu</option>
                        <option value="W.P. Kuala Lumpur">W.P. Kuala Lumpur</option>
                        <option value="W.P. Putrajaya">W.P. Putrajaya</option>
                        <option value="W.P. Labuan">W.P. Labuan</option>
                    </select>
                </div>
            </div>
            <div class="grid-container">
                <div class="form-group">
                    <label>Rekod Kesihatan (Jika ada)</label>
                    <textarea name="health_record" rows="2" placeholder="Cth: Asma, Lemah jantung"></textarea>
                </div>
                <div class="form-group">
                    <label>Alahan (Jika ada)</label>
                    <textarea name="allergies" rows="2" placeholder="Cth: Kacang, Makanan laut"></textarea>
                </div>
            </div>

            <!-- Section D: Upload Dokumen Sokongan -->
            <div class="section-title">📂 Muat Naik Dokumen Sokongan</div>
            <div class="grid-container">
                <div class="form-group">
                    <label>Salinan MyKid (Anak)</label>
                    <input type="file" name="mykid_file" accept=".pdf,.png,.jpg,.jpeg">
                </div>
                <div class="form-group">
                    <label>Surat Beranak / Sijil Kelahiran (Anak)</label>
                    <input type="file" name="birth_cert_file" accept=".pdf,.png,.jpg,.jpeg">
                </div>
            </div>
            <div class="form-group">
                <label>Kad Imunisasi / Rekod Kesihatan (Jika ada)</label>
                <input type="file" name="health_file" accept=".pdf,.png,.jpg,.jpeg">
            </div>

            <button type="submit" name="register">Hantar Pendaftaran</button>
        </form>
    </div>

</body>
</html>
