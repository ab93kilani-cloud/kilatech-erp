<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=kilatech_v3", "root", "");
    $stmt = $pdo->prepare("SELECT name, image_path FROM products WHERE id = 4");
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        echo "NAME: " . $product['name'] . "\n";
        echo "IMAGE: " . ($product['image_path'] ?? 'NULL') . "\n";
    } else {
        echo "Product 4 not found.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
