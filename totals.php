<?php
// totals.php: what everyone owes.
// A trip counts as owed until it's on an invoice that has been PAID.
// (Not billed yet, or on an invoice that's been sent but not paid, both count.)

function getTotals(PDO $pdo): array {
    return $pdo->query("
        SELECT clients.id, clients.name, COUNT(trips.id) AS trip_count,
               COALESCE(SUM(trips.amount), 0) AS total_owed
        FROM clients
        LEFT JOIN trips ON trips.client_id = clients.id
            AND (trips.invoice_id IS NULL
                 OR trips.invoice_id IN (SELECT id FROM invoices WHERE paid_at IS NULL))
        GROUP BY clients.id, clients.name
        ORDER BY clients.name
    ")->fetchAll();
}