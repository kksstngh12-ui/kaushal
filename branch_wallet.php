<?php
/**
 * Branch Wallet Helper
 *
 * Provides CRUD operations for branch wallet balance and transactions.
 * Requires a PDO instance in $db (MySQL).
 */

if (!isset($db) || !($db instanceof PDO)) {
    // When this file is included directly without a valid DB connection, stop execution.
    die("Database connection (PDO) not found.\nInclude this file AFTER creating \$db.");
}

/**
 * Ensure wallet row exists for given branch id.
 * Internal helper – creates row with zero balance if necessary.
 */
function bw_ensure_wallet_exists(string $brid, PDO $db): void
{
    $stmt = $db->prepare("INSERT IGNORE INTO branch_wallet (brid, balance) VALUES (:brid, 0)");
    $stmt->execute([':brid' => $brid]);
}

/**
 * Get current wallet balance for a branch.
 *
 * @param string $brid Branch ID from session
 * @param PDO    $db   PDO connection
 * @return float       Current balance (0 if none)
 */
function bw_get_balance(string $brid, PDO $db): float
{
    bw_ensure_wallet_exists($brid, $db);

    $stmt = $db->prepare("SELECT balance FROM branch_wallet WHERE brid = :brid LIMIT 1");
    $stmt->execute([':brid' => $brid]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Add funds to branch wallet (e.g. after successful top-up / deposit).
 *
 * @param string $brid   Branch ID
 * @param float  $amount Positive amount to add
 * @param PDO    $db     PDO connection
 * @return bool          True on success
 */
function bw_add_funds(string $brid, float $amount, PDO $db): bool
{
    if ($amount <= 0) {
        return false;
    }

    bw_ensure_wallet_exists($brid, $db);

    $sql = "UPDATE branch_wallet SET balance = balance + :amt WHERE brid = :brid";
    $stmt = $db->prepare($sql);
    return $stmt->execute([':amt' => $amount, ':brid' => $brid]);
}

/**
 * Deduct funds from wallet (e.g. when paying for student result).
 *
 * This call is atomic and will fail if insufficient balance.
 *
 * @param string $brid   Branch ID
 * @param float  $amount Positive amount to deduct
 * @param PDO    $db     PDO connection
 * @return bool          True if deducted, false if insufficient funds or error
 */
function bw_deduct_funds(string $brid, float $amount, PDO $db): bool
{
    if ($amount <= 0) {
        return false;
    }

    try {
        $db->beginTransaction();

        $current = bw_get_balance($brid, $db);
        if ($current < $amount) {
            $db->rollBack();
            return false; // not enough balance
        }

        $sql = "UPDATE branch_wallet SET balance = balance - :amt WHERE brid = :brid";
        $stmt = $db->prepare($sql);
        $stmt->execute([':amt' => $amount, ':brid' => $brid]);

        // Optionally insert transaction record
        $sql2 = "INSERT INTO branch_wallet_transactions (brid, amount, type) VALUES (:brid, :amt, 'DEBIT')";
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute([':brid' => $brid, ':amt' => $amount]);

        return $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Wallet deduct error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Record a credit (top-up) transaction.
 * Called automatically by bw_add_funds().
 */
function bw_log_credit(string $brid, float $amount, PDO $db): void
{
    $sql = "INSERT INTO branch_wallet_transactions (brid, amount, type) VALUES (:brid, :amt, 'CREDIT')";
    $stmt = $db->prepare($sql);
    $stmt->execute([':brid' => $brid, ':amt' => $amount]);
}
?>