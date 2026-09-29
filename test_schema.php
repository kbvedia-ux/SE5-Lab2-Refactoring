<?php
// test_schema.php
require_once 'config.php';

function runDatabaseSchemaTests(): void
{
    global $conn;

    echo "==================================================\n";
    echo "Running Database Schema Verification Suite...\n";
    echo "==================================================\n\n";

    if (!$conn || $conn->connect_error) {
        die("[CRITICAL ERROR] Database connection failed: " . ($conn->connect_error ?? 'Check config.php settings') . "\n");
    }

    // 1. Run the schema migration function
    ensureApplicationSchema();

    $passCount = 0;
    $totalCount = 0;

    // ------------------------------------------------------------------
    // TEST CASE 1: Verify Dynamically Added Schema Columns
    // ------------------------------------------------------------------
    echo "[TEST 1] Verifying dynamically created table columns...\n";

    $expectedColumns = [
        'users'                  => ['phone', 'role', 'account_status', 'reviewed_at', 'review_notes'],
        'boarding_houses'        => ['application_type', 'description', 'room_limit', 'reviewed_at', 'review_notes'],
        'rooms'                  => ['remarks'],
        'accreditation_documents' => ['original_file_name', 'requested_room_limit', 'approved_room_limit', 'review_notes', 'reviewed_at'],
        'payments'               => ['tenant_id', 'rent_due', 'amount_paid', 'balance', 'billing_month', 'notes'],
        'banlist'                => ['landlord_id', 'status'],
    ];

    foreach ($expectedColumns as $table => $columns) {
        foreach ($columns as $column) {
            $totalCount++;
            if (dbColumnExists($table, $column)) {
                echo "  -> [PASS] Column '{$column}' exists in table '{$table}'\n";
                $passCount++;
            } else {
                echo "  -> [FAIL] Column '{$column}' missing in table '{$table}'\n";
            }
        }
    }

    // ------------------------------------------------------------------
    // TEST CASE 2: Verify Required Table Existence
    // ------------------------------------------------------------------
    echo "\n[TEST 2] Verifying table creation ('tenants')...\n";
    $totalCount++;

    $tableCheck = $conn->query("SHOW TABLES LIKE 'tenants'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        echo "  -> [PASS] Table 'tenants' exists in database.\n";
        $passCount++;
    } else {
        echo "  -> [FAIL] Table 'tenants' does not exist in database.\n";
    }

    // ------------------------------------------------------------------
    // TEST CASE 3 (EDGE CASE): Idempotent Re-execution
    // ------------------------------------------------------------------
    echo "\n[TEST 3 - Edge Case] Testing Idempotent Re-execution...\n";
    $totalCount++;

    try {
        ensureApplicationSchema();
        echo "  -> [PASS] Re-execution completed successfully with zero database errors.\n";
        $passCount++;
    } catch (Exception $e) {
        echo "  -> [FAIL] Re-execution threw an exception: " . $e->getMessage() . "\n";
    }

    // ------------------------------------------------------------------
    // SUMMARY
    // ------------------------------------------------------------------
    echo "\n==================================================\n";
    echo "SUMMARY: {$passCount} / {$totalCount} tests passed.\n";
    echo "==================================================\n";
}

runDatabaseSchemaTests();
?>