<?php
// update_db.php
// Database migration utility to add financial system helper columns

require_once 'db.php';

echo "<h3>Running Childcare DB Financial Upgrade Migrations...</h3>";

function add_column_if_not_exists($conn, $table, $column, $definition) {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($result && $result->num_rows > 0) {
        echo "Column `{$table}`.`{$column}` already exists. Skipping.<br>";
    } else {
        $sql = "ALTER TABLE `$table` ADD `$column` $definition";
        if ($conn->query($sql)) {
            echo "Successfully added column `{$table}`.`{$column}`.<br>";
        } else {
            echo "Error adding column `{$table}`.`{$column}`: " . $conn->error . "<br>";
        }
    }
}

// 1. Add rejection_reason to payments
add_column_if_not_exists($conn, 'payments', 'rejection_reason', 'VARCHAR(255) NULL AFTER `status`');

// 2. Add items_json to invoices
add_column_if_not_exists($conn, 'invoices', 'items_json', 'TEXT NULL AFTER `type`');

// 3. Add allowance_details to payroll
add_column_if_not_exists($conn, 'payroll', 'allowance_details', 'TEXT NULL AFTER `allowances`');

// 4. Add deduction_details to payroll
add_column_if_not_exists($conn, 'payroll', 'deduction_details', 'TEXT NULL AFTER `deductions`');

echo "<br><strong>Database migrations completed!</strong>";
?>
