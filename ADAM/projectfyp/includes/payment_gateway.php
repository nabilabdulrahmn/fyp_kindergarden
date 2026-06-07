<?php
/**
 * SandboxPaymentGateway
 * Gateway pembayaran sandbox untuk sistem pengurusan taska.
 * Simulasi pembayaran FPX tanpa transaksi sebenar.
 */

class SandboxPaymentGateway
{
    /** @var mysqli */
    private $conn;

    /**
     * @param mysqli $conn Sambungan pangkalan data
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Mulakan pembayaran baharu.
     *
     * @param array $params Kunci yang diperlukan:
     *   - invoice_ids (array|string)  – ID invois (array atau rentetan dipisah koma)
     *   - parent_id   (int)           – ID ibu bapa
     *   - amount      (float)         – Jumlah bayaran
     *   - description (string)        – Penerangan pembayaran
     *   - return_url  (string)        – URL untuk redirect selepas pembayaran
     * @return array
     */
    public function initiatePayment(array $params): array
    {
        // Pastikan sesi bermula
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Normalisasi invoice_ids kepada rentetan dipisah koma
        $invoiceIds = $params['invoice_ids'];
        if (is_array($invoiceIds)) {
            $invoiceIds = implode(',', $invoiceIds);
        }
        $firstInvoiceId = explode(',', $invoiceIds)[0];

        $parentId    = (int) $params['parent_id'];
        $amount      = (float) $params['amount'];
        $description = $params['description'] ?? '';
        $returnUrl   = $params['return_url'] ?? '';

        // Jana ID transaksi dan token sesi
        $transactionId = 'SBX-' . strtoupper(bin2hex(random_bytes(8)));
        $token         = bin2hex(random_bytes(32));

        // Simpan dalam sesi untuk pengesahan di checkout
        $_SESSION['sandbox_payment_token'] = $token;
        $_SESSION['sandbox_txn_id']        = $transactionId;

        // Dapatkan alamat IP
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Payload permintaan
        $requestPayload = json_encode($params);

        // INSERT ke payment_gateway_log
        $stmt = $this->conn->prepare(
            "INSERT INTO payment_gateway_log
                (payment_id, invoice_id, parent_id, gateway, transaction_id, amount, currency, status, request_payload, ip_address, created_at)
             VALUES
                (NULL, ?, ?, 'Sandbox', ?, ?, 'MYR', 'Initiated', ?, ?, NOW())"
        );
        $stmt->bind_param(
            'iisdss',
            $firstInvoiceId,
            $parentId,
            $transactionId,
            $amount,
            $requestPayload,
            $ipAddress
        );
        $stmt->execute();
        $stmt->close();

        // URL checkout
        $checkoutUrl = 'sandbox_checkout.php?token=' . urlencode($token) . '&txn=' . urlencode($transactionId);

        return [
            'transaction_id' => $transactionId,
            'checkout_url'   => $checkoutUrl,
        ];
    }

    /**
     * Proses pembayaran (dipanggil dari halaman checkout sandbox).
     *
     * @param string $transactionId ID transaksi
     * @param string $bank          Nama bank yang dipilih
     * @return array
     */
    public function processPayment(string $transactionId, string $bank): array
    {
        // Sahkan transaksi wujud dengan status yang betul
        $stmt = $this->conn->prepare(
            "SELECT id, amount, status FROM payment_gateway_log WHERE transaction_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return [
                'status'         => 'Failed',
                'receipt_number' => null,
                'transaction_id' => $transactionId,
                'error_code'     => 'E0000',
                'error_message'  => 'Transaksi tidak ditemui',
            ];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        if (!in_array($row['status'], ['Initiated', 'Pending'])) {
            return [
                'status'         => 'Failed',
                'receipt_number' => null,
                'transaction_id' => $transactionId,
                'error_code'     => 'E0001',
                'error_message'  => 'Status transaksi tidak sah: ' . $row['status'],
            ];
        }

        $amount = (float) $row['amount'];

        // Simulasi keputusan pembayaran
        if ($amount == 1.00) {
            $success = true;
        } elseif ($amount == 0.01) {
            $success = false;
        } else {
            $success = (rand(1, 100) <= 85);
        }

        $timestamp = date('Y-m-d H:i:s');

        if ($success) {
            $receipt = 'REC-SBX-' . strtoupper(substr(md5($transactionId), 0, 8));

            $responsePayload = json_encode([
                'bank'           => $bank,
                'receipt_number' => $receipt,
                'timestamp'      => $timestamp,
                'status'         => 'Success',
            ]);

            $updateStmt = $this->conn->prepare(
                "UPDATE payment_gateway_log SET status = 'Success', response_payload = ?, updated_at = NOW() WHERE transaction_id = ?"
            );
            $updateStmt->bind_param('ss', $responsePayload, $transactionId);
            $updateStmt->execute();
            $updateStmt->close();

            return [
                'status'         => 'Success',
                'receipt_number' => $receipt,
                'transaction_id' => $transactionId,
                'error_code'     => null,
                'error_message'  => null,
            ];
        } else {
            $errorCode = 'E' . rand(1000, 9999);

            $responsePayload = json_encode([
                'bank'          => $bank,
                'error_code'    => $errorCode,
                'error_message' => 'Transaksi ditolak oleh bank',
                'timestamp'     => $timestamp,
                'status'        => 'Failed',
            ]);

            $updateStmt = $this->conn->prepare(
                "UPDATE payment_gateway_log SET status = 'Failed', response_payload = ?, updated_at = NOW() WHERE transaction_id = ?"
            );
            $updateStmt->bind_param('ss', $responsePayload, $transactionId);
            $updateStmt->execute();
            $updateStmt->close();

            return [
                'status'         => 'Failed',
                'receipt_number' => null,
                'transaction_id' => $transactionId,
                'error_code'     => $errorCode,
                'error_message'  => 'Transaksi ditolak oleh bank',
            ];
        }
    }

    /**
     * Sahkan status transaksi.
     *
     * @param string $transactionId ID transaksi
     * @return array
     */
    public function verifyTransaction(string $transactionId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM payment_gateway_log WHERE transaction_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return [
                'error' => 'Transaksi tidak ditemui',
            ];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        return [
            'status'           => $row['status'],
            'amount'           => (float) $row['amount'],
            'parent_id'        => (int) $row['parent_id'],
            'invoice_id'       => $row['invoice_id'],
            'gateway_response' => json_decode($row['response_payload'] ?? '{}', true),
            'request'          => json_decode($row['request_payload'] ?? '{}', true),
            'created_at'       => $row['created_at'],
        ];
    }

    /**
     * Mulakan proses bayaran balik.
     *
     * @param string $transactionId ID transaksi asal
     * @param float  $amount        Jumlah bayaran balik
     * @param string $reason        Sebab bayaran balik
     * @return array
     */
    public function initiateRefund(string $transactionId, float $amount, string $reason): array
    {
        // Sahkan transaksi asal wujud dan berjaya
        $stmt = $this->conn->prepare(
            "SELECT id, amount, response_payload FROM payment_gateway_log WHERE transaction_id = ? AND status = 'Success' LIMIT 1"
        );
        $stmt->bind_param('s', $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return [
                'status'        => 'Failed',
                'error_message' => 'Transaksi asal tidak ditemui atau tidak berjaya',
            ];
        }

        $row = $result->fetch_assoc();
        $stmt->close();

        $originalAmount = (float) $row['amount'];

        // Sahkan jumlah bayaran balik tidak melebihi amaun asal
        if ($amount > $originalAmount) {
            return [
                'status'        => 'Failed',
                'error_message' => 'Jumlah bayaran balik (RM ' . number_format($amount, 2) . ') melebihi amaun asal (RM ' . number_format($originalAmount, 2) . ')',
            ];
        }

        $refundId  = 'REF-' . strtoupper(bin2hex(random_bytes(6)));
        $timestamp = date('Y-m-d H:i:s');

        // Gabungkan maklumat bayaran balik ke response_payload sedia ada
        $existingPayload = json_decode($row['response_payload'] ?? '{}', true);
        $existingPayload['refund'] = [
            'refund_id'     => $refundId,
            'refund_amount' => $amount,
            'reason'        => $reason,
            'timestamp'     => $timestamp,
        ];

        $updatedPayload = json_encode($existingPayload);

        $updateStmt = $this->conn->prepare(
            "UPDATE payment_gateway_log SET status = 'Refunded', response_payload = ?, updated_at = NOW() WHERE transaction_id = ?"
        );
        $updateStmt->bind_param('ss', $updatedPayload, $transactionId);
        $updateStmt->execute();
        $updateStmt->close();

        return [
            'status'               => 'Refunded',
            'refund_id'            => $refundId,
            'original_transaction' => $transactionId,
            'refund_amount'        => $amount,
        ];
    }
}
