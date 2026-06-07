<?php
/**
 * Invoice Helper Functions
 * Sistem Pengurusan Taska - Fungsi Pembantu Invois & Kewangan
 * 
 * Provides invoice/receipt number generation, calculation utilities,
 * monthly invoice creation, overdue marking, and payment application.
 */

if (!defined('INVOICE_HELPER_LOADED')) {
    define('INVOICE_HELPER_LOADED', true);
}

/**
 * Generate a unique invoice number in format INV-YYYY-NNNNN
 * Queries the MAX invoice_number for the current year and increments the sequence.
 *
 * @param mysqli $conn Database connection
 * @return string Generated invoice number
 */
function generateInvoiceNumber($conn) {
    $year = date('Y');
    $prefix = 'INV-' . $year . '-';

    $stmt = $conn->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
    $likePattern = $prefix . '%';
    $stmt->bind_param('s', $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $next = 1;
    if ($row = $result->fetch_assoc()) {
        $lastNumber = $row['invoice_number'];
        $parts = explode('-', $lastNumber);
        if (isset($parts[2])) {
            $next = intval($parts[2]) + 1;
        }
    }
    $stmt->close();

    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique receipt number in format REC-YYYY-NNNNN
 * Queries the MAX receipt_number from payments for the current year.
 *
 * @param mysqli $conn Database connection
 * @return string Generated receipt number
 */
function generateReceiptNumber($conn) {
    $year = date('Y');
    $prefix = 'REC-' . $year . '-';

    $stmt = $conn->prepare("SELECT receipt_number FROM payments WHERE receipt_number LIKE ? ORDER BY receipt_number DESC LIMIT 1");
    $likePattern = $prefix . '%';
    $stmt->bind_param('s', $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $next = 1;
    if ($row = $result->fetch_assoc()) {
        $lastNumber = $row['receipt_number'];
        $parts = explode('-', $lastNumber);
        if (isset($parts[2])) {
            $next = intval($parts[2]) + 1;
        }
    }
    $stmt->close();

    return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate invoice totals from line items with optional overall discount.
 *
 * @param array $line_items Array of items, each with 'quantity', 'unit_price', 'discount_pct'
 * @param float $discount_pct Overall discount percentage (0-100)
 * @return array ['subtotal' => float, 'discount_amount' => float, 'total_amount' => float]
 */
function calculateInvoiceTotals(array $line_items, float $discount_pct = 0): array {
    $subtotal = 0.0;

    foreach ($line_items as $item) {
        $qty = floatval($item['quantity'] ?? 0);
        $unit_price = floatval($item['unit_price'] ?? 0);
        $line_discount = floatval($item['discount_pct'] ?? 0);

        $line_total = $qty * $unit_price * (1 - $line_discount / 100);
        $subtotal += $line_total;
    }

    $discount_amount = $subtotal * ($discount_pct / 100);
    $total_amount = $subtotal - $discount_amount;

    return [
        'subtotal'        => round($subtotal, 2),
        'discount_amount' => round($discount_amount, 2),
        'total_amount'    => round($total_amount, 2),
    ];
}

/**
 * Create monthly invoices for all active students (or filtered by class).
 *
 * Steps:
 *  1. Get active students with parent_id, module, class via student_classes
 *  2. Optionally filter by class_id
 *  3. Skip if invoice already exists for the period_month (YYYY-MM)
 *  4. Get applicable fee_structures (active, Monthly, within valid date range)
 *  5. Apply sibling discount rules based on parent's active student count
 *  6. Create invoice + line items with proper totals
 *
 * @param mysqli $conn        Database connection
 * @param int    $month       Month (1-12)
 * @param int    $year        Year (e.g. 2026)
 * @param int|null $class_filter Optional class_id to filter students
 * @param string|null $due_date   Optional due date (YYYY-MM-DD), defaults to CURDATE()+30
 * @return array ['created' => int, 'skipped' => int]
 */
function createMonthlyInvoices($conn, $month, $year, $class_filter = null, $due_date = null) {
    $created = 0;
    $skipped = 0;
    $period_month = sprintf('%04d-%02d', $year, $month);

    if ($due_date === null) {
        $due_date = date('Y-m-d', strtotime('+30 days'));
    }

    // 1. Get all active students with their class and module info
    $studentQuery = "
        SELECT s.id AS student_id, s.parent_id, s.full_name, s.module,
               sc.class_id
        FROM students s
        LEFT JOIN student_classes sc ON s.id = sc.student_id
        WHERE s.status = 'Active'
    ";

    if ($class_filter !== null) {
        $studentQuery .= " AND sc.class_id = ?";
        $stmt = $conn->prepare($studentQuery);
        $stmt->bind_param('i', $class_filter);
    } else {
        $stmt = $conn->prepare($studentQuery);
    }

    $stmt->execute();
    $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 2. Load sibling discount rules
    $discountRules = [];
    $drStmt = $conn->prepare("SELECT child_order, discount_pct FROM sibling_discount_rules ORDER BY child_order ASC");
    if ($drStmt) {
        $drStmt->execute();
        $drResult = $drStmt->get_result();
        while ($dr = $drResult->fetch_assoc()) {
            $discountRules[intval($dr['child_order'])] = floatval($dr['discount_pct']);
        }
        $drStmt->close();
    }

    // 3. Count active students per parent for sibling discount
    $siblingCounts = [];
    $scStmt = $conn->prepare("SELECT parent_id, COUNT(*) as child_count FROM students WHERE status = 'Active' GROUP BY parent_id");
    $scStmt->execute();
    $scResult = $scStmt->get_result();
    while ($sc = $scResult->fetch_assoc()) {
        $siblingCounts[intval($sc['parent_id'])] = intval($sc['child_count']);
    }
    $scStmt->close();

    // Determine each student's order among siblings (by student id ascending as proxy)
    $parentChildren = [];
    $orderStmt = $conn->prepare("SELECT id, parent_id FROM students WHERE status = 'Active' ORDER BY parent_id, id ASC");
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    while ($oc = $orderResult->fetch_assoc()) {
        $pid = intval($oc['parent_id']);
        if (!isset($parentChildren[$pid])) {
            $parentChildren[$pid] = [];
        }
        $parentChildren[$pid][] = intval($oc['id']);
    }
    $orderStmt->close();


    // Build a map of student_id => child_order (1-based)
    $studentOrder = [];
    foreach ($parentChildren as $pid => $children) {
        foreach ($children as $index => $sid) {
            $studentOrder[$sid] = $index + 1; // 1-based order
        }
    }

    // 4. Process each student
    foreach ($students as $student) {
        $student_id = intval($student['student_id']);
        $parent_id  = intval($student['parent_id']);
        $module     = $student['module'];

        // Check if invoice already exists for this student and period
        $checkStmt = $conn->prepare("SELECT id FROM invoices WHERE student_id = ? AND period_month = ?");
        $checkStmt->bind_param('is', $student_id, $period_month);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $skipped++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();

        // Get applicable fee structures for this student's module
        $feeStmt = $conn->prepare("
            SELECT id, fee_name, amount
            FROM fee_structures
            WHERE module = ?
              AND is_active = 1
              AND frequency = 'Monthly'
              AND (valid_from IS NULL OR valid_from <= CURDATE())
              AND (valid_until IS NULL OR valid_until >= CURDATE())
        ");
        $feeStmt->bind_param('s', $module);
        $feeStmt->execute();
        $fees = $feeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $feeStmt->close();

        if (empty($fees)) {
            $skipped++;
            continue;
        }

        // Determine sibling discount for this student
        $childOrder = $studentOrder[$student_id] ?? 1;
        $siblingDiscountPct = 0.0;
        if ($childOrder >= 2 && !empty($discountRules)) {
            if (isset($discountRules[$childOrder])) {
                $siblingDiscountPct = $discountRules[$childOrder];
            } elseif ($childOrder >= 3) {
                // For 3rd+ child, use the highest defined rule for 3+
                $maxOrder = max(array_keys($discountRules));
                if ($childOrder >= $maxOrder) {
                    $siblingDiscountPct = $discountRules[$maxOrder];
                }
            }
        }

        // Build line items
        $lineItems = [];
        foreach ($fees as $fee) {
            $lineItems[] = [
                'fee_structure_id' => $fee['id'],
                'fee_name'         => $fee['fee_name'],
                'description'      => $fee['fee_name'],
                'quantity'         => 1,
                'unit_price'       => floatval($fee['amount']),
                'discount_pct'     => 0, // line-level discount (none by default)
            ];
        }

        // Calculate totals with sibling discount as overall discount
        $totals = calculateInvoiceTotals($lineItems, $siblingDiscountPct);

        // Generate invoice number
        $invoiceNumber = generateInvoiceNumber($conn);

        // Begin transaction for atomicity
        $conn->begin_transaction();

        try {
            // Insert invoice
            $invStmt = $conn->prepare("
                INSERT INTO invoices 
                    (invoice_number, student_id, parent_id, issued_date, due_date, period_month,
                     subtotal, discount_amount, total_amount, paid_amount, balance_due, status, created_at)
                VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 0, ?, 'Sent', NOW())
            ");
            $invStmt->bind_param(
                'siissdddd',
                $invoiceNumber,
                $student_id,
                $parent_id,
                $due_date,
                $period_month,
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['total_amount'],
                $totals['total_amount'] // balance_due = total_amount initially
            );
            $invStmt->execute();
            $invoice_id = $conn->insert_id;
            $invStmt->close();

            // Insert line items
            $liStmt = $conn->prepare("
                INSERT INTO invoice_line_items 
                    (invoice_id, fee_structure_id, description, quantity, unit_price, discount_pct, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($lineItems as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'] * (1 - $item['discount_pct'] / 100);
                $lineTotal = round($lineTotal, 2);
                $liStmt->bind_param(
                    'iisiddd',
                    $invoice_id,
                    $item['fee_structure_id'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['discount_pct'],
                    $lineTotal
                );
                $liStmt->execute();
            }
            $liStmt->close();

            $conn->commit();
            $created++;
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Gagal mencipta invois untuk pelajar ID $student_id: " . $e->getMessage());
            $skipped++;
        }
    }

    return [
        'created' => $created,
        'skipped' => $skipped,
    ];
}

/**
 * Mark all overdue invoices.
 * Updates status to 'Overdue' for invoices that are 'Sent' and past due date.
 *
 * @param mysqli $conn Database connection
 * @return int Number of affected rows
 */
function markOverdueInvoices($conn): int {
    $stmt = $conn->prepare("UPDATE invoices SET status = 'Overdue' WHERE status = 'Sent' AND due_date < CURDATE()");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    return $affected;
}

/**
 * Apply a payment amount to an invoice, updating paid_amount, balance_due, and status.
 *
 * @param mysqli $conn       Database connection
 * @param int    $invoice_id Invoice ID
 * @param float  $amount     Payment amount to apply
 * @return array ['balance' => float, 'status' => string]
 */
function applyPaymentToInvoice($conn, $invoice_id, $amount): array {
    // Get current invoice totals
    $stmt = $conn->prepare("SELECT paid_amount, total_amount FROM invoices WHERE id = ?");
    $stmt->bind_param('i', $invoice_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $invoice = $result->fetch_assoc();
    $stmt->close();

    if (!$invoice) {
        throw new Exception("Invois tidak ditemui: ID $invoice_id");
    }

    $current_paid = floatval($invoice['paid_amount']);
    $total_amount = floatval($invoice['total_amount']);

    $new_paid = $current_paid + floatval($amount);
    $new_balance = $total_amount - $new_paid;

    if ($new_balance <= 0) {
        $new_status = 'Paid';
        $new_balance = 0.00;
        $new_paid = $total_amount; // Cap at total to avoid overpayment recorded
    } else {
        $new_status = 'Partial';
    }

    $updateStmt = $conn->prepare("UPDATE invoices SET paid_amount = ?, balance_due = ?, status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('ddsi', $new_paid, $new_balance, $new_status, $invoice_id);
    $updateStmt->execute();
    $updateStmt->close();

    return [
        'balance' => round($new_balance, 2),
        'status'  => $new_status,
    ];
}
