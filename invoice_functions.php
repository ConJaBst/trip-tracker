<?php
// invoice_functions.php: the invoice rules, in one place.
// Used by new_invoice.php (the button) and cron_invoices.php (the weekly job).

// Which trips go on a new invoice for this person:
//   - their unbilled trips in the date range, PLUS
//   - every trip on their UNPAID invoices (carried over, whatever the date).
// Returns the SQL and the values to go with it.
function invoiceTripFilter(int $clientId, string $from, string $to): array {
    $sql = "
        client_id = :client
        AND (
            (invoice_id IS NULL AND trip_date BETWEEN :from AND :to)
            OR invoice_id IN (SELECT id FROM invoices WHERE client_id = :client2 AND paid_at IS NULL)
        )
    ";
    $values = ['client' => $clientId, 'client2' => $clientId, 'from' => $from, 'to' => $to];
    return [$sql, $values];
}

// Creates the invoice. Returns its id, or null if there were no trips to bill.
function createInvoice(PDO $pdo, int $clientId, string $from, string $to): ?int {
    [$tripFilter, $filterValues] = invoiceTripFilter($clientId, $from, $to);

    $pdo->beginTransaction();
    try {
        // 1. Remember which unpaid invoices we're replacing.
        $stmt = $pdo->prepare("SELECT id FROM invoices WHERE client_id = ? AND paid_at IS NULL");
        $stmt->execute([$clientId]);
        $oldInvoiceIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 2. Make the new invoice (total filled in at step 5).
        $stmt = $pdo->prepare("
            INSERT INTO invoices (client_id, period_start, period_end, total)
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$clientId, $from, $to]);
        $invoiceId = (int) $pdo->lastInsertId();

        // 3. Move the trips onto it.
        $stmt = $pdo->prepare("UPDATE trips SET invoice_id = :invoice WHERE $tripFilter");
        $stmt->execute(['invoice' => $invoiceId] + $filterValues);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return null;   // nothing to bill
        }

        // 4. The old unpaid invoices are now empty, so delete them.
        if (count($oldInvoiceIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($oldInvoiceIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM invoices WHERE id IN ($placeholders) AND paid_at IS NULL");
            $stmt->execute($oldInvoiceIds);
        }

        // 5. Total, and stretch the start date back if carried-over trips are older.
        $stmt = $pdo->prepare("SELECT SUM(amount) AS total, MIN(trip_date) AS first_trip FROM trips WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $sums = $stmt->fetch();
        $start = min($from, $sums['first_trip']);

        $stmt = $pdo->prepare("UPDATE invoices SET total = ?, period_start = ? WHERE id = ?");
        $stmt->execute([$sums['total'], $start, $invoiceId]);

        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}