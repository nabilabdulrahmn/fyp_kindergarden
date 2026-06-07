<?php
/**
 * Upload Helper
 * Fungsi untuk mengesahkan dan menyimpan fail yang dimuat naik.
 */

/**
 * Sahkan fail yang dimuat naik.
 * Memeriksa ralat muat naik, saiz fail, dan jenis MIME menggunakan finfo.
 *
 * @param array $file          Elemen dari $_FILES
 * @param array $allowed_types Senarai jenis MIME yang dibenarkan
 * @param int   $max_size      Saiz maksimum dalam bait (lalai: 5 MB)
 * @return array ['valid' => bool, 'error' => string]
 */
function validateUpload(array $file, array $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'], int $max_size = 5242880): array
{
    // Semak ralat muat naik PHP
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'Fail melebihi had saiz pelayan.',
            UPLOAD_ERR_FORM_SIZE  => 'Fail melebihi had saiz borang.',
            UPLOAD_ERR_PARTIAL    => 'Fail hanya dimuat naik sebahagian.',
            UPLOAD_ERR_NO_FILE    => 'Tiada fail dipilih untuk dimuat naik.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori sementara tidak dijumpai.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis fail ke cakera.',
            UPLOAD_ERR_EXTENSION  => 'Muat naik dihentikan oleh sambungan PHP.',
        ];

        $error_code = $file['error'] ?? -1;
        $error_msg  = $error_messages[$error_code] ?? 'Ralat muat naik tidak diketahui.';

        return ['valid' => false, 'error' => $error_msg];
    }

    // Semak fail sementara wujud
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'Fail muat naik tidak sah.'];
    }

    // Semak saiz fail
    if ($file['size'] > $max_size) {
        $max_mb = round($max_size / 1048576, 1);
        return ['valid' => false, 'error' => "Saiz fail melebihi had maksimum {$max_mb} MB."];
    }

    // Semak jenis MIME menggunakan finfo (bukan sambungan fail sahaja)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected_type = $finfo->file($file['tmp_name']);

    if (!in_array($detected_type, $allowed_types, true)) {
        $allowed_str = implode(', ', $allowed_types);
        return [
            'valid' => false,
            'error' => "Jenis fail '{$detected_type}' tidak dibenarkan. Jenis yang diterima: {$allowed_str}."
        ];
    }

    return ['valid' => true, 'error' => ''];
}

/**
 * Simpan fail yang dimuat naik ke direktori destinasi.
 * Mencipta direktori secara rekursif jika belum wujud.
 * Menjana nama fail unik untuk mengelakkan konflik.
 *
 * @param array  $file     Elemen dari $_FILES
 * @param string $dest_dir Direktori destinasi (relatif kepada root projek)
 * @param string $prefix   Awalan nama fail
 * @return string|false Laluan relatif fail yang disimpan, atau false jika gagal
 */
function saveUpload(array $file, string $dest_dir, string $prefix = 'file'): string|false
{
    // Cipta direktori jika belum wujud
    if (!is_dir($dest_dir)) {
        if (!mkdir($dest_dir, 0755, true)) {
            error_log("saveUpload: Gagal mencipta direktori '{$dest_dir}'.");
            return false;
        }
    }

    // Dapatkan sambungan fail asal
    $original_name = $file['name'] ?? '';
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    // Jika sambungan kosong, cuba tentukan daripada MIME type
    if (empty($extension)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $mime_to_ext = [
            'image/jpeg'       => 'jpg',
            'image/png'        => 'png',
            'image/gif'        => 'gif',
            'application/pdf'  => 'pdf',
        ];
        $extension = $mime_to_ext[$mime] ?? 'bin';
    }

    // Jana nama fail unik
    $safe_prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix);
    $unique_name = $safe_prefix . '_' . uniqid('', true) . '.' . $extension;
    $dest_path   = rtrim($dest_dir, '/\\') . DIRECTORY_SEPARATOR . $unique_name;

    // Pindahkan fail
    if (!move_uploaded_file($file['tmp_name'], $dest_path)) {
        error_log("saveUpload: Gagal memindahkan fail ke '{$dest_path}'.");
        return false;
    }

    // Kembalikan laluan relatif (gunakan forward slash untuk konsistensi URL)
    return str_replace('\\', '/', $dest_path);
}
