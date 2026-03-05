<?php
// test.php - Database Connection Test
echo "<!DOCTYPE html><html><head><title>DB Test</title>";
echo "<style>body{font-family:Arial;padding:40px;background:#0e0b1e;color:#fff}";
echo ".success{background:#4caf50;padding:20px;border-radius:10;margin:10px 0}";
echo ".error{background:#e53935;padding:20px;border-radius:10;margin:10px 0}";
echo ".info{background:#1a1035;padding:20px;border-radius:10;margin:10px 0}</style></head><body>";
 
echo "<h1>🧪 BazaarPK Database Test</h1>";
 
try {
    $host = 'localhost';
    $db   = 'rsoa_rsoa0142_8';      // ⚠️ YOUR EXACT DATABASE NAME
    $user = 'rsoa_rsoa0142_8';
    $pass = '654321#';
 
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 
    echo "<div class='success'>✅ Database Connected Successfully!</div>";
 
    // Test Users Table
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $users = $stmt->fetchColumn();
    echo "<div class='info'>👤 Users: <strong>$users</strong></div>";
 
    // Test Products Table
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $products = $stmt->fetchColumn();
    echo "<div class='info'>📦 Products: <strong>$products</strong></div>";
 
    // Test Orders Table
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $orders = $stmt->fetchColumn();
    echo "<div class='info'>📦 Orders: <strong>$orders</strong></div>";
 
    // Show Sample Data
    echo "<div class='info'><h3>Sample Users:</h3>";
    $stmt = $pdo->query("SELECT email, role FROM users LIMIT 3");
    while($row = $stmt->fetch()) {
        echo "• {$row['email']} ({$row['role']})<br>";
    }
    echo "</div>";
 
    echo "<div class='success'><h2>🎉 Everything Working Perfectly!</h2>";
    echo "<p>Your BazaarPK database is ready to use.</p>";
    echo "<a href='index.php' style='background:#c9a84c;color:#1f1542;padding:10px 20px;text-decoration:none;border-radius:5;font-weight:bold'>Go to Website →</a></div>";
 
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h2>❌ Connection Failed!</h2>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Check if database <strong>rsoa_rsoa0142_8</strong> exists in cPanel</li>";
    echo "<li>Verify user <strong>rsoa_rsoa0142_8</strong> is assigned to database</li>";
    echo "<li>Make sure <strong>ALL PRIVILEGES</strong> are granted</li>";
    echo "<li>Wait 2-3 minutes after permission changes</li>";
    echo "<li>Check password is correct: <strong>654321#</strong></li>";
    echo "</ul>";
    echo "</div>";
}
 
echo "</body></html>";
?>
