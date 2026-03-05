<?php
// db.php - BazaarPK Database Connection
$host = 'localhost';
$db   = 'rsoa_rsoa0142_8';         // ⚠️ YOUR EXACT DATABASE NAME
$user = 'rsoa_rsoa0142_8';         // Your MySQL username
$pass = '654321#';                 // Your MySQL password
$charset = 'utf8mb4';
 
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
 
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("❌ DB Connection Error: " . $e->getMessage());
}
 
// ============================================
// HELPER FUNCTIONS
// ============================================
 
function getProducts($pdo, $category = 'All', $search = '', $sort = 'featured') {
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];
    if ($category !== 'All') { $sql .= " AND category = ?"; $params[] = $category; }
    if ($search) { $sql .= " AND (name LIKE ? OR brand LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
 
    if ($sort === 'price_asc') $sql .= " ORDER BY price ASC";
    elseif ($sort === 'price_desc') $sql .= " ORDER BY price DESC";
    elseif ($sort === 'rating') $sql .= " ORDER BY rating DESC";
    elseif ($sort === 'newest') $sql .= " ORDER BY id DESC";
    else $sql .= " ORDER BY sold DESC";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
 
function getProduct($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
 
function getUser($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND password = ?");
    $stmt->execute([$email, $password]);
    return $stmt->fetch();
}
 
function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}
 
function getOrders($pdo, $userId = null, $sellerId = null) {
    if ($sellerId) {
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE buyerId = ? ORDER BY id DESC");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll();
}
 
function fmt($n) { return 'Rs. ' . number_format($n); }
function disc($o, $p) { return $o > $p ? round((1 - $p / $o) * 100) : 0; }
?>
