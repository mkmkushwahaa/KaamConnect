<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDBConnection();
    
    // Update Customer Profiles
    $stmt1 = $pdo->prepare("UPDATE customer_profiles SET district = ?, state = ?, latitude = ?, longitude = ? WHERE id = ?");
    $stmt1->execute(['Gautam Buddha Nagar', 'Uttar Pradesh', 28.6280, 77.3649, 1]);
    $stmt1->execute(['South East Delhi', 'Delhi', 28.5677, 77.2433, 2]);

    // Update Jobs
    $stmt2 = $pdo->prepare("UPDATE jobs SET district = ?, state = ?, latitude = ?, longitude = ? WHERE id = ?");
    $stmt2->execute(['Gautam Buddha Nagar', 'Uttar Pradesh', 28.6280, 77.3649, 1]);
    $stmt2->execute(['Gautam Buddha Nagar', 'Uttar Pradesh', 28.6280, 77.3649, 2]);
    $stmt2->execute(['South East Delhi', 'Delhi', 28.5677, 77.2433, 3]);

    echo "GEO_DATA_UPDATED_SUCCESSFULLY\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

