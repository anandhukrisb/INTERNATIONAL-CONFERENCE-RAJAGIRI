<?php
// backend/test_db.php

require_once __DIR__ . '/db.php';

try {
    echo "Connected to the database successfully!\n";

    // 1. Create the table 'name'
    $createTableSql = "CREATE TABLE IF NOT EXISTS `name` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255) NOT NULL
    )";
    $pdo->exec($createTableSql);
    echo "Table 'name' ensured to exist.\n";

    // 2. Insert the name 'anandhu'
    $insertSql = "INSERT INTO `name` (first_name) VALUES (:name)";
    $stmt = $pdo->prepare($insertSql);
    $stmt->execute(['name' => 'anandhu']);
    echo "Successfully inserted 'anandhu' into the table.\n";

    // 3. Fetch and display the contents of the table
    $selectSql = "SELECT * FROM `name`";
    $stmt = $pdo->query($selectSql);
    $results = $stmt->fetchAll();
    
    echo "Current contents of the 'name' table:\n";
    print_r($results);

} catch (\PDOException $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}
?>
