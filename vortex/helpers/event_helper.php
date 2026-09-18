<?php
// Helper function to generate unique sequential Event IDs (e.g., EVT-2027-000001)

require_once __DIR__ . '/../config/db.php';

function generateEventId($pdo) {
    $year = date('Y');
    $prefix = "EVT-" . $year . "-";

    // Find the highest current sequence for the current year
    $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_id LIKE :prefix ORDER BY id DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '%']);
    $lastEventId = $stmt->fetchColumn();

    if ($lastEventId) {
        $parts = explode('-', $lastEventId);
        $lastNumber = (int)end($parts);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}
