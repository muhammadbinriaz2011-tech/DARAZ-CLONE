<?php
// index.php
session_start();
require 'db.php';
 
// --- Actions (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
 
    if ($action === 'login') {
        $user = getUser($pdo, $_POST['email'], $_POST['password']);
        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['toast'] = ['msg' => "Welcome back, {$user['name']}!", 'type' => 'success'];
            header("Location: ?page=home"); exit;
        } else {
            $_SESSION['toast'] = ['msg' => "Invalid email or password.", 'type' => 'error'];
        }
    }
    elseif ($action === 'logout') {
        session_destroy();
        session_start();
        $_SESSION['toast'] = ['msg' => "Logged out successfully", 'type' => 'success'];
        header("Location: ?page=home"); exit;
    }
    elseif ($action === 'add_to_cart') {
        $id = $_POST['id'];
        $qty = (int)$_POST['qty'];
        $product = getProduct($pdo, $id);
        if ($product) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['id'] == $id) { $item['qty'] += $qty; $found = true; break; }
            }
            if (!$found) $_SESSION['cart'][] = [...$product, 'qty' => $qty];
            $_SESSION['toast'] = ['msg' => "{$product['name']} added to cart! 🛒", 'type' => 'success'];
        }
        header("Location: " . ($_POST['redirect'] ?? $_SERVER['HTTP_REFERER'])); exit;
    }
    elseif ($action === 'update_cart') {
        $id = $_POST['id'];
        $qty = (int)$_POST['qty'];
        if (isset($_SESSION['cart'])) {
            if ($qty < 1) {
                $_SESSION['cart'] = array_filter($_SESSION['cart'], fn($i) => $i['id'] != $id);
            } else {
                foreach ($_SESSION['cart'] as &$item) { if ($item['id'] == $id) { $item['qty'] = $qty; break; } }
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
        header("Location: ?page=cart"); exit;
    }
    elseif ($action === 'place_order') {
        if (!isset($_SESSION['user']) || empty($_SESSION['cart'])) { header("Location: ?page=login"); exit; }
        $orderId = 'ORD-' . time();
        $total = array_sum(array_column($_SESSION['cart'], 'price')); // Simplified total calc
        $total = 0; foreach($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
 
        $stmt = $pdo->prepare("INSERT INTO orders (id, items, total, status, date, address, payment, buyerId) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderId, json_encode($_SESSION['cart']), $total, 'Pending', date('Y-m-d'), 
            "{$_POST['address']}, {$_POST['city']}", $_POST['payment'], $_SESSION['user']['id']
        ]);
 
        $_SESSION['cart'] = [];
        $_SESSION['toast'] = ['msg' => "Order placed successfully! 🎉", 'type' => 'success'];
        header("Location: ?page=orders"); exit;
    }
    elseif ($action === 'add_product' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'seller') {
        $stmt = $pdo->prepare("INSERT INTO products (name, category, price, originalPrice, brand, description, image, stock, sellerId, rating, reviews, sold) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0)");
        $stmt->execute([$_POST['name'], $_POST['category'], $_POST['price'], $_POST['originalPrice'], $_POST['brand'], $_POST['description'], $_POST['image'], $_POST['stock'], $_SESSION['user']['id']]);
        $_SESSION['toast'] = ['msg' => "Product listed successfully!", 'type' => 'success'];
        header("Location: ?page=seller"); exit;
    }
    elseif ($action === 'delete_product' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'seller') {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND sellerId = ?");
        $stmt->execute([$_POST['id'], $_SESSION['user']['id']]);
        $_SESSION['toast'] = ['msg' => "Product deleted.", 'type' => 'success'];
        header("Location: ?page=seller"); exit;
    }
    elseif ($action === 'update_order_status' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'seller') {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['order_id']]);
        $_SESSION['toast'] = ['msg' => "Order status updated", 'type' => 'success'];
        header("Location: ?page=orders"); exit;
    }
}
 
// --- Routing & Data Prep ---
$page = $_GET['page'] ?? 'home';
$user = $_SESSION['user'] ?? null;
$cart = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart, 'qty'));
$cartTotal = 0; foreach($cart as $i) $cartTotal += $i['price'] * $i['qty'];
 
// Toast Handling
$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
 
// --- Helper Functions for Views ---
function renderStars($r) {
    $full = floor($r); $empty = 5 - $full;
    return "<span style='color:#c9a84c'>" . str_repeat("★", $full) . str_repeat("☆", $empty) . " <span style='color:rgba(240,236,232,0.6);font-size:12px'>$r</span></span>";
}
 
function renderNavbar($page, $user, $cartCount) {
    $categories = ["All", "Electronics", "Fashion", "Home & Kitchen"];
    $searchQuery = $_GET['query'] ?? '';
    ?>
    <nav style="background:#1f1542;padding:0 20px;position:sticky;top:0;z-index:1000;box-shadow:0 2px 20px rgba(0,0,0,0.3)">
        <div style="max-width:1300;margin:0 auto;display:flex;align-items:center;gap:16;height:64">
            <div onclick="window.location='?page=home'" style="cursor:pointer;display:flex;align-items:center;gap:6;flex-shrink:0">
                <span style="font-size:28;font-weight:900;color:#fff;font-family:'Playfair Display',serif;letter-spacing:-1">Bazaar</span>
                <span style="background:#c9a84c;color:#1f1542;font-size:10;font-weight:800;padding:2px 6px;border-radius:4;letter-spacing:1">PK</span>
            </div>
            <div style="flex:1;display:flex;max-width:600">
                <form action="?page=search" method="GET" style="flex:1;display:flex">
                    <input name="query" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search products, brands and categories..." style="flex:1;padding:10px 16px;border-radius:8px 0 0 8px;border:none;font-size:14;font-family:'Outfit',sans-serif;outline:none;background:#120f2a;color:#f0ece8" />
                    <button type="submit" style="background:#c9a84c;border:none;padding:10px 18px;border-radius:0 8px 8px 0;cursor:pointer;font-size:18">🔍</button>
                </form>
            </div>
            <div style="display:flex;align-items:center;gap:8;flex-shrink:0">
                <?php if ($user): ?>
                <div style="color:#fff;font-size:13;font-family:'Outfit',sans-serif">
                    <div style="font-size:10;opacity:0.7">Hello,</div>
                    <div style="font-weight:700"><?= explode(" ", $user['name'])[0] ?></div>
                </div>
                <div style="position:relative">
                    <button onclick="toggleMenu()" style="background:rgba(255,255,255,0.1);border:none;border-radius:50%;width:38;height:38;color:#fff;cursor:pointer;font-size:13;font-weight:700"><?= $user['avatar'] ?></button>
                    <div id="userMenu" style="display:none;position:absolute;top:48;right:0;background:#1a1035;border-radius:10;box-shadow:0 8px 32px rgba(0,0,0,0.15);padding:8px 0;min-width:180;z-index:100">
                        <div onclick="window.location='?page=profile'" style="padding:10px 20px;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14;color:#f0ece8">👤 My Profile</div>
                        <?php if ($user['role'] === 'buyer'): ?>
                        <div onclick="window.location='?page=orders'" style="padding:10px 20px;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14;color:#f0ece8">📦 My Orders</div>
                        <?php else: ?>
                        <div onclick="window.location='?page=seller'" style="padding:10px 20px;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14;color:#f0ece8">🏪 Seller Dashboard</div>
                        <?php endif; ?>
                        <form method="POST" style="margin:0"><input type="hidden" name="action" value="logout"><button type="submit" style="width:100%;text-align:left;padding:10px 20px;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14;color:#f0ece8;background:none;border:none">🚪 Logout</button></form>
                    </div>
                </div>
                <?php else: ?>
                <button onclick="window.location='?page=login'" style="background:#c9a84c;border:none;padding:8px 18px;border-radius:8;cursor:pointer;font-weight:700;font-family:'Outfit',sans-serif;font-size:13">Login / Sign Up</button>
                <?php endif; ?>
                <button onclick="window.location='?page=cart'" style="background:rgba(255,255,255,0.1);border:none;border-radius:8;padding:8px 14px;color:#fff;cursor:pointer;font-family:'Outfit',sans-serif;font-size:13;position:relative;display:flex;align-items:center;gap:6">
                    🛒 Cart
                    <?php if ($cartCount > 0): ?><span style="background:#c9a84c;color:#1f1542;border-radius:50%;width:20;height:20;font-size:11;font-weight:800;display:flex;align-items:center;justify-content:center"><?= $cartCount ?></span><?php endif; ?>
                </button>
            </div>
        </div>
        <div style="background:#150e30;padding:0 20px">
            <div style="max-width:1300;margin:0 auto;display:flex;gap:4">
                <?php foreach ($categories as $c): 
                    $isActive = ($page === 'search' && ($_GET['category'] ?? 'All') === $c);
                ?>
                <a href="?page=search&category=<?= $c ?>" style="background:<?= $isActive ? 'rgba(245,166,35,0.15)' : 'none' ?>;border:none;border-bottom:<?= $isActive ? '3px solid #c9a84c' : '3px solid transparent' ?>;color:<?= $isActive ? '#c9a84c' : 'rgba(255,255,255,0.8)' ?>;padding:10px 16px;cursor:pointer;font-family:'Outfit',sans-serif;font-size:13;font-weight:<?= $isActive ? 700 : 500 ?>;text-decoration:none;transition:all 0.2s"><?= $c ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
    <?php
}
 
function renderProductCard($p) {
    $d = disc($p['originalPrice'], $p['price']);
    ?>
    <div style="background:#1a1035;border-radius:14;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,0.4);border:1px solid rgba(201,168,76,0.15);transition:transform 0.2s, box-shadow 0.2s;cursor:pointer;display:flex;flex-direction:column" onmouseenter="this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 32px rgba(0,0,0,0.15)'" onmouseleave="this.style.transform='translateY(0)';this.style.boxShadow='0 2px 12px rgba(0,0,0,0.08)'">
        <div onclick="window.location='?page=product&id=<?= $p['id'] ?>'" style="background:linear-gradient(135deg, #f5f0e8 0%, #f3e5f5 100%);display:flex;align-items:center;justify-content:center;height:180;font-size:72;position:relative">
            <?= $p['image'] ?>
            <?php if ($d > 0): ?><span style="position:absolute;top:10;left:10;background:#e53935;color:#fff;font-size:11;font-weight:800;padding:3px 8px;border-radius:6">-<?= $d ?>%</span><?php endif; ?>
        </div>
        <div style="padding:14px;flex:1;display:flex;flex-direction:column;gap:6">
            <div style="font-size:12;color:#10b981;font-weight:700;text-transform:uppercase;letter-spacing:0.5"><?= $p['brand'] ?></div>
            <div onclick="window.location='?page=product&id=<?= $p['id'] ?>'" style="font-size:14;font-weight:600;color:#120f2a;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= $p['name'] ?></div>
            <?= renderStars($p['rating']) ?>
            <div style="font-size:11;color:rgba(240,236,232,0.45)"><?= number_format($p['reviews']) ?> reviews · <?= number_format($p['sold']) ?> sold</div>
            <div style="margin-top:auto">
                <div style="font-size:18;font-weight:800;color:#c9a84c"><?= fmt($p['price']) ?></div>
                <?php if ($p['originalPrice'] > $p['price']): ?><div style="font-size:12;color:rgba(201,168,76,0.5);text-decoration:line-through"><?= fmt($p['originalPrice']) ?></div><?php endif; ?>
            </div>
            <form method="POST" style="margin-top:8px">
                <input type="hidden" name="action" value="add_to_cart">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit" style="width:100%;background:#1f1542;color:#fff;border:none;border-radius:8;padding:9px;cursor:pointer;font-weight:700;font-family:'Outfit',sans-serif;font-size:13;transition:background 0.2s" onmouseenter="this.style.background='#c9a84c'" onmouseleave="this.style.background='#1f1542'">Add to Cart</button>
            </form>
        </div>
    </div>
    <?php
}
 
function renderFooter() {
    ?>
    <footer style="background:#120f2a;color:rgba(255,255,255,0.7);padding:40px 40px 20px;font-family:'Outfit',sans-serif;margin-top:60">
        <div style="max-width:1300;margin:0 auto">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:32;margin-bottom:32">
                <div>
                    <div style="font-family:'Playfair Display',serif;font-size:24;font-weight:900;color:#fff;margin-bottom:12">BazaarPK</div>
                    <p style="font-size:13;line-height:1.7">Pakistan's premier e-commerce destination. Connecting buyers and sellers across the country.</p>
                </div>
                <?php foreach ([["Shop", ["Electronics", "Fashion", "Home & Kitchen", "Flash Deals"]], ["Sell", ["Seller Center", "List Products", "Track Orders", "Analytics"]], ["Support", ["Help Center", "Returns", "Track Order", "Contact Us"]]] as $col): ?>
                <div>
                    <h4 style="color:#fff;font-weight:800;margin-bottom:12;font-size:15"><?= $col[0] ?></h4>
                    <?php foreach ($col[1] as $l): ?><div style="font-size:13;margin-bottom:8;cursor:pointer;transition:color 0.2s" onmouseenter="this.style.color='#c9a84c'" onmouseleave="this.style.color='rgba(255,255,255,0.7)'"><?= $l ?></div><?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="border-top:1px solid rgba(255,255,255,0.1);padding-top:20;text-align:center;font-size:13">© 2025 BazaarPK. All rights reserved. 🇵🇰 Made for Pakistan</div>
        </div>
    </footer>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BazaarPK - Ramadan Edition</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Outfit:wght@400;500;600;700;800;900&family=Amiri:wght@400;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0e0b1e; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: #c9a84c; border-radius: 3px; }
        /* Ramadan Banner */
        .ramadan-banner { position: relative; background: linear-gradient(135deg, #0a1628 0%, #1a3a2a 40%, #0d2618 70%, #0a1628 100%); padding: 18px 60px 14px; text-align: center; overflow: hidden; border-bottom: 2px solid #c9a84c; }
        .ramadan-banner::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse at 50% 0%, rgba(201,168,76,0.18) 0%, transparent 70%); pointer-events: none; }
        .ramadan-stars { display: flex; justify-content: center; gap: 18px; margin-bottom: 6px; }
        .ramadan-star { color: #c9a84c; font-size: 11px; animation: ramadanTwinkle 2s ease-in-out infinite alternate; opacity: 0.7; }
        @keyframes ramadanTwinkle { from { opacity: 0.3; transform: scale(0.8); } to { opacity: 1; transform: scale(1.2); } }
        .ramadan-content { display: flex; align-items: center; justify-content: center; gap: 20px; }
        .ramadan-moon { font-size: 32px; color: #c9a84c; animation: moonSway 3s ease-in-out infinite alternate; filter: drop-shadow(0 0 8px rgba(201,168,76,0.7)); }
        @keyframes moonSway { from { transform: rotate(-8deg) scale(1); } to { transform: rotate(8deg) scale(1.1); } }
        .ramadan-text-block { display: flex; align-items: center; gap: 14px; }
        .ramadan-arabic { font-family: 'Amiri', serif; font-size: 28px; font-weight: 700; color: #f0d080; letter-spacing: 1px; animation: ramadanGlow 2.5s ease-in-out infinite alternate; }
        .ramadan-divider { color: #c9a84c; font-size: 16px; opacity: 0.8; }
        .ramadan-english { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: #f0d080; letter-spacing: 2px; animation: ramadanGlow 2.5s ease-in-out infinite alternate; animation-delay: 0.5s; }
        @keyframes ramadanGlow { from { text-shadow: 0 0 10px rgba(201,168,76,0.4); } to { text-shadow: 0 0 30px rgba(201,168,76,0.9), 0 0 60px rgba(201,168,76,0.4); } }
        .ramadan-sub { font-family: 'Outfit', sans-serif; font-size: 12px; color: rgba(201,168,76,0.75); margin-top: 6px; letter-spacing: 0.5px; }
        .ramadan-close { position: absolute; top: 10px; right: 14px; background: none; border: 1px solid rgba(201,168,76,0.4); color: #c9a84c; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 11px; transition: all 0.2s; }
        .ramadan-close:hover { background: rgba(201,168,76,0.15); }
        .ramadan-lanterns { position: absolute; top: 0; left: 0; right: 0; display: flex; justify-content: space-between; padding: 0 40px; pointer-events: none; }
        .lantern { font-size: 20px; animation: lanternSwing 2.5s ease-in-out infinite alternate; display: inline-block; opacity: 0.8; }
        @keyframes lanternSwing { from { transform: rotate(-12deg); } to { transform: rotate(12deg); } }
        .floating-lanterns-wrap { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; overflow: hidden; }
        .float-lantern { position: absolute; top: -40px; animation: floatUp linear infinite; opacity: 0.18; }
        @keyframes floatUp { 0% { transform: translateY(0) rotate(0deg); opacity: 0; } 10% { opacity: 0.22; } 90% { opacity: 0.15; } 100% { transform: translateY(110vh) rotate(360deg); opacity: 0; } }
        .ramadan-fading-bar { background: linear-gradient(90deg, #1f1542 0%, #2d1f6e 50%, #1f1542 100%); padding: 10px 20px; display: flex; align-items: center; justify-content: center; gap: 16px; border-bottom: 1px solid rgba(201,168,76,0.3); }
        .rfb-icon { font-size: 18px; animation: rfbIconPulse 2s ease-in-out infinite alternate; }
        @keyframes rfbIconPulse { from { transform: scale(1); } to { transform: scale(1.3); } }
        .rfb-text { font-family: 'Amiri', serif; font-size: 15px; color: #f0d080; letter-spacing: 1.5px; transition: opacity 0.5s ease; text-shadow: 0 0 12px rgba(201,168,76,0.5); min-width: 340px; text-align: center; }
        .rmodal-overlay { position: fixed; inset: 0; background: rgba(10,8,30,0.82); z-index: 9999; display: flex; align-items: center; justify-content: center; animation: fadeInOverlay 0.5s ease; }
        @keyframes fadeInOverlay { from { opacity: 0; } to { opacity: 1; } }
        .rmodal-box { position: relative; background: linear-gradient(145deg, #1f1542 0%, #0f2d1a 60%, #1f1542 100%); border: 2px solid #c9a84c; border-radius: 24px; padding: 48px 40px 36px; text-align: center; max-width: 460px; width: 90%; box-shadow: 0 0 60px rgba(201,168,76,0.25), 0 30px 80px rgba(0,0,0,0.6); animation: modalPop 0.5s cubic-bezier(0.34,1.56,0.64,1); overflow: hidden; }
        @keyframes modalPop { from { transform: scale(0.6) translateY(40px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
        .rmodal-bg-stars { position: absolute; inset: 0; pointer-events: none; }
        .rmodal-star { position: absolute; color: #c9a84c; animation: ramadanTwinkle 2s ease-in-out infinite alternate; opacity: 0.4; }
        .rmodal-moon-row { display: flex; justify-content: center; gap: 20px; margin-bottom: 16px; }
        .rmodal-moon-big { font-size: 40px; animation: moonSway 3s ease-in-out infinite alternate; filter: drop-shadow(0 0 10px rgba(201,168,76,0.8)); }
        .rmodal-arabic { font-family: 'Amiri', serif; font-size: 36px; font-weight: 700; color: #f0d080; margin-bottom: 8px; animation: ramadanGlow 2.5s ease-in-out infinite alternate; }
        .rmodal-title { font-family: 'Playfair Display', serif; font-size: 26px; font-weight: 900; color: #fff; margin-bottom: 12px; letter-spacing: 1px; }
        .rmodal-sub { font-family: 'Outfit', sans-serif; font-size: 14px; color: rgba(255,255,255,0.75); line-height: 1.7; margin-bottom: 24px; }
        .rmodal-lantern-row { display: flex; justify-content: center; gap: 16px; margin-bottom: 24px; }
        .rmodal-lantern-icon { font-size: 28px; animation: lanternSwing 2s ease-in-out infinite alternate; display: inline-block; }
        .rmodal-btn { background: linear-gradient(135deg, #c9a84c, #f0d080, #c9a84c); color: #1f1542; border: none; padding: 14px 36px; border-radius: 50px; font-family: 'Playfair Display', serif; font-size: 16px; font-weight: 700; cursor: pointer; letter-spacing: 1px; box-shadow: 0 4px 20px rgba(201,168,76,0.4); transition: transform 0.2s, box-shadow 0.2s; }
        .rmodal-btn:hover { transform: scale(1.05); box-shadow: 0 8px 32px rgba(201,168,76,0.6); }
        .rmodal-close { position: absolute; top: 14px; right: 16px; background: none; border: 1px solid rgba(201,168,76,0.4); color: #c9a84c; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 12px; }
    </style>
</head>
<body>
 
<!-- Ramadan Components -->
<div class="ramadan-banner" id="ramadanBanner">
    <div class="ramadan-stars"><?php for($i=0;$i<12;$i++): ?><span class="ramadan-star" style="animation-delay:<?= $i*0.3 ?>s"><?= ["✦","☽","✦","★","✦","☽","✦","★","✦","☽","✦"][$i%11] ?></span><?php endfor; ?></div>
    <div class="ramadan-content">
        <span class="ramadan-moon">☽</span>
        <div class="ramadan-text-block">
            <span class="ramadan-arabic">رمضان مبارک</span>
            <span class="ramadan-divider">✦</span>
            <span class="ramadan-english">Ramadan Mubarak</span>
        </div>
        <span class="ramadan-moon">☾</span>
    </div>
    <div class="ramadan-sub">🕌 Wishing you a blessed and peaceful Ramadan — BazaarPK 🕌</div>
    <button class="ramadan-close" onclick="document.getElementById('ramadanBanner').style.display='none'">✕</button>
    <div class="ramadan-lanterns">
        <span class="lantern" style="animation-delay:0s">🪔</span><span class="lantern" style="animation-delay:0.5s">🪔</span><span class="lantern" style="animation-delay:1s">🪔</span>
    </div>
</div>
 
<div class="floating-lanterns-wrap" style="pointer-events:none">
    <?php $lanterns = [["🪔","3%","0s","6s",28],["☽","10%","1s","8s",22],["✦","18%","2s","5s",16],["🕌","25%","0.5s","9s",24],["🪔","38%","3s","7s",20],["★","50%","1.5s","6s",18],["🪔","62%","0.8s","8s",26],["☽","72%","2.5s","5s",20],["✦","80%","1s","7s",14],["🕌","88%","3.5s","6s",22],["🪔","95%","0.3s","9s",18]];
    foreach($lanterns as $l): ?>
    <span class="float-lantern" style="left:<?= $l[1] ?>;animation-delay:<?= $l[2] ?>;animation-duration:<?= $l[3] ?>;font-size:<?= $l[4] ?>px"><?= $l[0] ?></span>
    <?php endforeach; ?>
</div>
 
<div class="ramadan-fading-bar">
    <span class="rfb-icon">🌙</span>
    <span class="rfb-text">رمضان مبارک ✦ Ramadan Mubarak</span>
    <span class="rfb-icon">🌙</span>
</div>
 
<div class="rmodal-overlay" id="ramadanModal" onclick="this.style.display='none'">
    <div class="rmodal-box" onclick="event.stopPropagation()">
        <div class="rmodal-bg-stars"><?php for($i=0;$i<20;$i++): ?><span class="rmodal-star" style="left:<?= rand(5,95) ?>%;top:<?= rand(5,85) ?>%;animation-delay:<?= ($i*0.3)%3 ?>s;font-size:<?= 10+($i%3)*6 ?>px"><?= ["✦","★","☽"][$i%3] ?></span><?php endfor; ?></div>
        <div class="rmodal-moon-row"><span class="rmodal-moon-big">☽</span><span class="rmodal-moon-big" style="animation-delay:0.5s">🕌</span><span class="rmodal-moon-big" style="animation-delay:1s">☾</span></div>
        <div class="rmodal-arabic">رمضان کریم</div>
        <div class="rmodal-title">Ramadan Mubarak!</div>
        <div class="rmodal-sub">BazaarPK wishes you and your family a blessed, peaceful, and joyful Ramadan. May Allah accept your fasts and prayers. 🤲</div>
        <div class="rmodal-lantern-row"><?php foreach(["🪔","✦","🌙","✦","🪔"] as $x): ?><span class="rmodal-lantern-icon" style="animation-delay:0.4s"><?= $x ?></span><?php endforeach; ?></div>
        <button class="rmodal-btn" onclick="document.getElementById('ramadanModal').style.display='none'">رمضان مبارک 🌙</button>
        <button class="rmodal-close" onclick="document.getElementById('ramadanModal').style.display='none'">✕</button>
    </div>
</div>
<script>setTimeout(()=>document.getElementById('ramadanModal').style.display='none', 8000);</script>
 
<!-- Main App -->
<?php renderNavbar($page, $user, $cartCount); ?>
 
<main style="min-height:70vh">
    <?php if ($toast): ?>
    <div style="position:fixed;bottom:24;right:24;background:<?= $toast['type']=='error'?'#e53935':'#1f1542' ?>;color:#fff;padding:14px 22px;border-radius:12;box-shadow:0 8px 32px rgba(0,0,0,0.3);z-index:9999;font-family:'Outfit',sans-serif;font-weight:600;font-size:14;animation:slideUp 0.3s ease;max-width:320"><?= htmlspecialchars($toast['msg']) ?></div>
    <?php endif; ?>
 
    <?php
    // --- Page Routing ---
    if ($page === 'home'):
        $products = getProducts($pdo);
        $featured = array_slice($products, 0, 4);
        $trending = $products; usort($trending, fn($a,$b)=>$b['sold']-$a['sold']); $trending = array_slice($trending, 0, 8);
        $deals = array_filter($products, fn($p)=>disc($p['originalPrice'],$p['price'])>=25);
        $banners = [
            ["bg"=>"linear-gradient(135deg, #1f1542 0%, #3b1f6e 50%, #0f2d1a 100%)", "title"=>"رمضان مبارک", "subtitle"=>"Blessed Ramadan Sales — Up to 60% Off", "emoji"=>"🌙", "btn"=>"Shop Deals"],
            ["bg"=>"linear-gradient(135deg, #0f2d1a 0%, #1f1542 60%, #0f2d1a 100%)", "title"=>"Ramadan Kareem ✦", "subtitle"=>"Exclusive Sehri & Iftar Specials", "emoji"=>"🕌", "btn"=>"Explore"],
            ["bg"=>"linear-gradient(135deg, #1a0f00 0%, #1f1542 50%, #0f2d1a 100%)", "title"=>"Holy Month Deals", "subtitle"=>"Shop & Save this blessed Ramadan", "emoji"=>"🪔", "btn"=>"Browse"],
        ];
        ?>
        <div style="font-family:'Outfit',sans-serif">
            <div style="background:<?= $banners[0]['bg'] ?>;padding:60px 40px;text-align:center;position:relative;overflow:hidden">
                <div style="font-size:80;margin-bottom:16;animation:bounce 2s infinite"><?= $banners[0]['emoji'] ?></div>
                <h1 style="color:#fff;font-size:48;font-weight:900;margin:0 0 8px;font-family:'Playfair Display',serif"><?= $banners[0]['title'] ?></h1>
                <p style="color:rgba(255,255,255,0.85);font-size:20;margin:0 0 24px"><?= $banners[0]['subtitle'] ?></p>
                <button onclick="window.location='?page=search'" style="background:#1a1035;color:#1f1542;border:none;padding:14px 36px;border-radius:50;font-weight:800;font-size:16;cursor:pointer;font-family:'Outfit',sans-serif"><?= $banners[0]['btn'] ?></button>
            </div>
            <div style="background:#120f2a;padding:24px 40px;max-width:1300;margin:0 auto">
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:16;margin-top:8">
                    <?php foreach([["📱","Electronics","Electronics"],["👗","Fashion","Fashion"],["🍳","Home & Kitchen","Home & Kitchen"],["⭐","Top Rated","All"],["🔥","Flash Deals","All"]] as $c): ?>
                    <div onclick="window.location='?page=search&category=<?= $c[2] ?>'" style="background:linear-gradient(135deg, #f5f0e8, #fdf6e3);border-radius:12;padding:20px;text-align:center;cursor:pointer;transition:transform 0.2s" onmouseenter="this.style.transform='scale(1.04)'" onmouseleave="this.style.transform='scale(1)'">
                        <div style="font-size:36"><?= $c[0] ?></div>
                        <div style="font-weight:700;font-size:13;color:#1f1542;margin-top:8"><?= $c[1] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (count($deals) > 0): ?>
            <section style="max-width:1300;margin:0 auto;padding:24px 40px;background:transparent">
                <div style="display:flex;align-items:center;gap:12;margin-bottom:20">
                    <h2 style="margin:0;font-size:24;font-weight:900;color:#120f2a;font-family:'Playfair Display',serif">⚡ Flash Deals</h2>
                    <span style="background:#e53935;color:#fff;padding:4px 12px;border-radius:20;font-size:12;font-weight:700">Limited Time</span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20">
                    <?php foreach ($deals as $p): renderProductCard($p); endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <section style="max-width:1300;margin:0 auto;padding:24px 40px 40px">
                <h2 style="margin:0 0 20px;font-size:24;font-weight:900;color:#120f2a;font-family:'Playfair Display',serif">🔥 Trending Now</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20">
                    <?php foreach ($trending as $p): renderProductCard($p); endforeach; ?>
                </div>
            </section>
            <div style="background:linear-gradient(135deg, #1f1542 0%, #0f2d1a 100%);margin:0 auto 40px;border:1px solid rgba(201,168,76,0.3);max-width:1300;border-radius:16;padding:32px 40px;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <h3 style="color:#c9a84c;margin:0 0 8px;font-family:'Playfair Display',serif;font-size:28">Become a Seller</h3>
                    <p style="color:rgba(255,255,255,0.8);margin:0;font-size:15">List your products and reach millions of customers across Pakistan</p>
                </div>
                <button onclick="window.location='?page=login'" style="background:#c9a84c;border:none;padding:14px 28px;border-radius:10;font-weight:800;cursor:pointer;font-family:'Outfit',sans-serif;font-size:15;color:#1f1542;flex-shrink:0">Start Selling</button>
            </div>
        </div>
    <?php elseif ($page === 'search'):
        $category = $_GET['category'] ?? 'All';
        $query = $_GET['query'] ?? '';
        $sort = $_GET['sort'] ?? 'featured';
        $products = getProducts($pdo, $category, $query, $sort);
        ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <form method="GET" action="?page=search" style="display:flex;gap:12;margin-bottom:24">
                <input type="hidden" name="page" value="search">
                <input type="hidden" name="category" value="<?= $category ?>">
                <input name="query" value="<?= htmlspecialchars($query) ?>" placeholder="Search products..." style="flex:1;padding:12px 20px;border-radius:10;border:2px solid rgba(201,168,76,0.25);font-size:15;font-family:'Outfit',sans-serif;outline:none;background:#120f2a;color:#f0ece8" />
                <select name="sort" onchange="this.form.submit()" style="padding:12px 16px;border-radius:10;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;background:#120f2a;color:#f0ece8;cursor:pointer">
                    <option value="featured" <?= $sort=='featured'?'selected':'' ?>>Featured</option>
                    <option value="price_asc" <?= $sort=='price_asc'?'selected':'' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort=='price_desc'?'selected':'' ?>>Price: High to Low</option>
                    <option value="rating" <?= $sort=='rating'?'selected':'' ?>>Best Rating</option>
                </select>
                <button type="submit" style="background:#c9a84c;border:none;padding:12px 24px;border-radius:10;font-weight:700;cursor:pointer">Filter</button>
            </form>
            <div style="display:grid;grid-template-columns:240px 1fr;gap:32">
                <div style="background:#1a1035;border-radius:14;padding:24;box-shadow:0 2px 20px rgba(0,0,0,0.3);height:fit-content;position:sticky;top:80;border:1px solid rgba(201,168,76,0.15)">
                    <h3 style="margin:0 0 20px;font-weight:900;color:#120f2a;font-size:16">Filters</h3>
                    <div style="margin-bottom:24">
                        <div style="font-weight:700;margin-bottom:12;font-size:14;color:rgba(240,236,232,0.75)">Category</div>
                        <?php foreach(["All","Electronics","Fashion","Home & Kitchen"] as $c): ?>
                        <label style="display:flex;align-items:center;gap:8;margin-bottom:8;cursor:pointer;font-size:14;color:<?= $category===$c?'#1f1542':'#555' ?>;font-weight:<?= $category===$c?700:400 ?>">
                            <input type="radio" name="cat" <?= $category===$c?'checked':'' ?> onclick="window.location='?page=search&category=<?= $c ?>&query=<?= urlencode($query) ?>'" style="accent-color:#1f1542"> <?= $c ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <a href="?page=search" style="width:100%;display:block;margin-top:20;background:#f5f0e8;border:none;padding:10px;border-radius:8;cursor:pointer;font-weight:700;color:#1f1542;font-family:'Outfit',sans-serif;text-align:center;text-decoration:none">Reset Filters</a>
                </div>
                <div>
                    <div style="margin-bottom:16;color:#c9a84c;font-size:14"><?= count($products) ?> results</div>
                    <?php if (empty($products)): ?>
                    <div style="text-align:center;padding:80px 0"><div style="font-size:64">🔍</div><h3 style="color:#f0ece8;margin-top:16">No products found</h3></div>
                    <?php else: ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20">
                        <?php foreach ($products as $p): renderProductCard($p); endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php elseif ($page === 'product'):
        $p = getProduct($pdo, $_GET['id'] ?? 0);
        if ($p):
        $d = disc($p['originalPrice'], $p['price']);
        ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <button onclick="window.location='?page=home'" style="background:none;border:1px solid rgba(201,168,76,0.2);padding:8px 16px;border-radius:8;cursor:pointer;margin-bottom:24;font-family:'Outfit',sans-serif;font-size:13;color:rgba(240,236,232,0.6)">← Back</button>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:48">
                <div style="background:linear-gradient(135deg, #f5f0e8, #f3e5f5);border-radius:20;display:flex;align-items:center;justify-content:center;height:400;font-size:160"><?= $p['image'] ?></div>
                <div>
                    <div style="font-size:13;color:#10b981;font-weight:700;text-transform:uppercase;margin-bottom:8"><?= $p['brand'] ?> · <?= $p['category'] ?></div>
                    <h1 style="font-size:28;font-weight:800;color:#120f2a;margin:0 0 16px;line-height:1.3"><?= $p['name'] ?></h1>
                    <div style="display:flex;align-items:center;gap:12;margin-bottom:16"><?= renderStars($p['rating']) ?><span style="color:rgba(240,236,232,0.6);font-size:13">(<?= number_format($p['reviews']) ?> reviews)</span><span style="color:rgba(240,236,232,0.6);font-size:13">·</span><span style="color:rgba(240,236,232,0.6);font-size:13"><?= number_format($p['sold']) ?> sold</span></div>
                    <div style="background:rgba(201,168,76,0.08);border-radius:12;padding:20;margin-bottom:20">
                        <div style="font-size:36;font-weight:900;color:#c9a84c"><?= fmt($p['price']) ?></div>
                        <?php if ($d > 0): ?><div style="display:flex;gap:10;align-items:center;margin-top:4"><span style="font-size:16;color:rgba(201,168,76,0.5);text-decoration:line-through"><?= fmt($p['originalPrice']) ?></span><span style="background:#e53935;color:#fff;padding:2px 8px;border-radius:6;font-size:12;font-weight:800">Save <?= $d ?>%</span></div><?php endif; ?>
                    </div>
                    <p style="color:rgba(240,236,232,0.65);line-height:1.7;margin-bottom:20"><?= $p['description'] ?></p>
                    <form method="POST" style="display:flex;gap:12">
                        <input type="hidden" name="action" value="add_to_cart">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <div style="display:flex;align-items:center;border:2px solid #1f1542;border-radius:8;overflow:hidden">
                            <button type="button" onclick="let q=document.getElementById('qty');if(q.value>1)q.value--" style="background:#120f2a;border:none;padding:10px 16px;cursor:pointer;font-size:18;color:#1f1542;font-weight:700">-</button>
                            <input type="number" id="qty" name="qty" value="1" min="1" max="<?= $p['stock'] ?>" style="width:50;text-align:center;border:none;font-weight:700;font-size:16;background:transparent;color:#1f1542">
                            <button type="button" onclick="let q=document.getElementById('qty');if(q.value<<?= $p['stock'] ?>)q.value++" style="background:#120f2a;border:none;padding:10px 16px;cursor:pointer;font-size:18;color:#1f1542;font-weight:700">+</button>
                        </div>
                        <span style="color:<?= $p['stock']<5?'#e53935':'#4caf50' ?>;font-weight:600;font-size:13"><?= $p['stock']<5?"Only {$p['stock']} left!":"In Stock ✓" ?></span>
                        <button type="submit" style="flex:1;background:#1a1035;border:2px solid #c9a84c;color:#1f1542;padding:14px;border-radius:10;font-weight:800;cursor:pointer;font-family:'Outfit',sans-serif;font-size:15">🛒 Add to Cart</button>
                    </form>
                    <div style="margin-top:20;display:flex;gap:16;font-size:13;color:rgba(240,236,232,0.6)"><span>🚚 Free Delivery</span><span>↩️ 30-day Returns</span><span>🔒 Secure Payment</span></div>
                </div>
            </div>
        </div>
        <?php endif;
    elseif ($page === 'cart'):
        if (empty($cart)): ?>
        <div style="max-width:1300;margin:0 auto;padding:80px 40px;text-align:center;font-family:'Outfit',sans-serif">
            <div style="font-size:80">🛒</div>
            <h2 style="color:#120f2a;margin:20px 0 12px">Your cart is empty</h2>
            <button onclick="window.location='?page=home'" style="background:linear-gradient(135deg, #c9a84c, #f0d080);color:#1f1542;border:none;padding:14px 32px;border-radius:10;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;font-size:15">Continue Shopping</button>
        </div>
        <?php else: ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <h1 style="font-size:28;font-weight:900;color:#120f2a;margin:0 0 24px;font-family:'Playfair Display',serif">Shopping Cart (<?= count($cart) ?> items)</h1>
            <div style="display:grid;grid-template-columns:1fr 360px;gap:32">
                <div style="display:flex;flex-direction:column;gap:16">
                    <?php foreach ($cart as $item): ?>
                    <div style="background:#1a1035;border-radius:14;padding:20;box-shadow:0 2px 20px rgba(0,0,0,0.3);display:flex;gap:20;align-items:center;border:1px solid rgba(201,168,76,0.15)">
                        <div style="background:linear-gradient(135deg, #f5f0e8, #f3e5f5);border-radius:12;width:90;height:90;display:flex;align-items:center;justify-content:center;font-size:48;flex-shrink:0"><?= $item['image'] ?></div>
                        <div style="flex:1">
                            <div style="font-weight:700;color:#120f2a;margin-bottom:4"><?= $item['name'] ?></div>
                            <div style="font-size:13;color:#10b981;font-weight:600"><?= $item['brand'] ?></div>
                            <div style="font-weight:800;color:#1f1542;font-size:18;margin-top:8"><?= fmt($item['price']) ?></div>
                        </div>
                        <form method="POST" style="display:flex;align-items:center;gap:12">
                            <input type="hidden" name="action" value="update_cart">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <div style="display:flex;align-items:center;border:1px solid rgba(201,168,76,0.2);border-radius:8;overflow:hidden">
                                <button type="button" onclick="let f=this.closest('form');f.querySelector('input[name=qty]').value=<?= $item['qty']-1 ?>;f.submit()" style="background:#f0ede8;border:none;padding:8px 14px;cursor:pointer;font-size:16;font-weight:700">-</button>
                                <span style="padding:8px 16px;font-weight:700"><?= $item['qty'] ?></span>
                                <button type="button" onclick="let f=this.closest('form');f.querySelector('input[name=qty]').value=<?= $item['qty']+1 ?>;f.submit()" style="background:#f0ede8;border:none;padding:8px 14px;cursor:pointer;font-size:16;font-weight:700">+</button>
                            </div>
                            <input type="hidden" name="qty" value="<?= $item['qty'] ?>">
                            <div style="font-weight:700;color:#1f1542;min-width:80;text-align:right"><?= fmt($item['price'] * $item['qty']) ?></div>
                            <button type="button" onclick="let f=this.closest('form');f.querySelector('input[name=qty]').value=0;f.submit()" style="background:none;border:none;color:#e53935;cursor:pointer;font-size:20">🗑️</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="background:#1a1035;border-radius:14;padding:24;box-shadow:0 2px 20px rgba(0,0,0,0.3);height:fit-content;position:sticky;top:80;border:1px solid rgba(201,168,76,0.15)">
                    <h3 style="margin:0 0 20px;font-weight:900;font-size:18;color:#120f2a">Order Summary</h3>
                    <?php foreach ($cart as $i): ?>
                    <div style="display:flex;justify-content:space-between;margin-bottom:10;font-size:13;color:rgba(240,236,232,0.65)"><span style="flex:1;margin-right:8"><?= substr($i['name'],0,30) ?>... ×<?= $i['qty'] ?></span><span style="font-weight:600"><?= fmt($i['price'] * $i['qty']) ?></span></div>
                    <?php endforeach; ?>
                    <div style="border-top:1px solid rgba(201,168,76,0.15);padding-top:16;margin-top:8">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8;color:rgba(240,236,232,0.6);font-size:14"><span>Subtotal</span><span><?= fmt($cartTotal) ?></span></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8;color:#4caf50;font-size:14"><span>Delivery</span><span>FREE</span></div>
                        <div style="display:flex;justify-content:space-between;font-weight:900;font-size:20;color:#c9a84c;margin-top:12;padding-top:12;border-top:2px solid rgba(201,168,76,0.4)"><span>Total</span><span><?= fmt($cartTotal) ?></span></div>
                    </div>
                    <button onclick="window.location='?page=<?= $user?'checkout':'login' ?>'" style="width:100%;margin-top:20;background:#1f1542;color:#fff;border:none;padding:16px;border-radius:10;font-weight:800;cursor:pointer;font-family:'Outfit',sans-serif;font-size:16;transition:background 0.2s" onmouseenter="this.style.background='#c9a84c'" onmouseleave="this.style.background='#1f1542'">Proceed to Checkout →</button>
                    <button onclick="window.location='?page=home'" style="width:100%;margin-top:10;background:none;color:#1f1542;border:1px solid #1f1542;padding:12px;border-radius:10;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14">Continue Shopping</button>
                </div>
            </div>
        </div>
        <?php endif;
    elseif ($page === 'checkout' && $user):
        ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <h1 style="font-size:28;font-weight:900;color:#120f2a;margin:0 0 8px;font-family:'Playfair Display',serif">Checkout</h1>
            <div style="display:flex;gap:0;margin-bottom:32;background:#1a1035;border-radius:12;overflow:hidden;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.2)">
                <div style="flex:1;padding:16px;text-align:center;background:#1f1542;color:#fff;font-weight:700;font-size:14">1. Shipping</div>
                <div style="flex:1;padding:16px;text-align:center;background:#f5f0e8;color:#1f1542;font-weight:700;font-size:14">2. Payment</div>
                <div style="flex:1;padding:16px;text-align:center;background:#fff;color:#999;font-weight:700;font-size:14">3. Review</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 360px;gap:32">
                <form method="POST" style="background:#1a1035;border-radius:14;padding:28;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15)">
                    <input type="hidden" name="action" value="place_order">
                    <h3 style="margin:0 0 20px;color:#120f2a">Shipping Information</h3>
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">Full Name *</label><input name="name" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8" /></div>
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">Phone Number *</label><input name="phone" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8" /></div>
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">Address *</label><input name="address" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8" /></div>
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">City *</label><input name="city" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8" /></div>
                    <h3 style="margin:20px 0;color:#120f2a">Payment Method</h3>
                    <div style="border:2px solid #1f1542;border-radius:10;padding:16;margin-bottom:12;cursor:pointer;background:#f5f0e8"><div style="font-weight:700;font-size:15;color:#120f2a">💵 Cash on Delivery</div><div style="font-size:12;color:rgba(240,236,232,0.6);margin-top:4">Pay when you receive</div><input type="hidden" name="payment" value="cod"></div>
                    <button type="submit" style="width:100%;background:linear-gradient(135deg, #10b981, #059669);color:#fff;border:none;padding:14px;border-radius:10;font-weight:800;cursor:pointer;font-family:'Outfit',sans-serif;font-size:15">✓ Place Order</button>
                </form>
                <div style="background:#1a1035;border-radius:14;padding:24;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15);height:fit-content">
                    <h3 style="margin:0 0 16px;font-size:16;font-weight:900;color:#120f2a">Order Summary</h3>
                    <?php foreach ($cart as $i): ?>
                    <div style="display:flex;gap:10;margin-bottom:12;align-items:center"><span style="font-size:24"><?= $i['image'] ?></span><div style="flex:1"><div style="font-size:12;font-weight:600;color:#f0ece8;line-height:1.3"><?= substr($i['name'],0,35) ?>...</div><div style="font-size:11;color:rgba(240,236,232,0.6)">Qty: <?= $i['qty'] ?></div></div><div style="font-weight:700;font-size:13;color:#1f1542"><?= fmt($i['price'] * $i['qty']) ?></div></div>
                    <?php endforeach; ?>
                    <div style="border-top:2px solid rgba(201,168,76,0.5);padding-top:12;margin-top:8;display:flex;justify-content:space-between;font-weight:900;font-size:18;color:#c9a84c"><span>Total</span><span><?= fmt($cartTotal) ?></span></div>
                </div>
            </div>
        </div>
    <?php elseif ($page === 'orders' && $user):
        $orders = getOrders($pdo, $user['id'], $user['role']==='seller'?true:null);
        ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <h1 style="font-size:28;font-weight:900;color:#120f2a;margin:0 0 24px;font-family:'Playfair Display',serif"><?= $user['role']==='seller'?'📦 Manage Orders':'📦 My Orders' ?></h1>
            <?php if (empty($orders)): ?>
            <div style="text-align:center;padding:80px;background:#1a1035;border-radius:16"><div style="font-size:64">📦</div><h3 style="color:#f0ece8;margin-top:16">No orders yet</h3></div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:16">
                <?php foreach ($orders as $order): 
                    $items = json_decode($order['items'], true);
                    $statusColors = ['Pending'=>'#c9a84c', 'Shipped'=>'#1f1542', 'Delivered'=>'#4caf50', 'Cancelled'=>'#e53935'];
                ?>
                <div style="background:#1a1035;border-radius:14;padding:24;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15)">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16;flex-wrap:wrap;gap:12">
                        <div><div style="font-weight:900;font-size:16;color:#120f2a"><?= $order['id'] ?></div><div style="font-size:13;color:rgba(240,236,232,0.6);margin-top:4">Placed on <?= $order['date'] ?> · <?= $order['payment'] ?></div></div>
                        <div style="display:flex;align-items:center;gap:12">
                            <span style="background:<?= $statusColors[$order['status']] ?>25;color:<?= $statusColors[$order['status']] ?>;padding:6px 14px;border-radius:20;font-weight:700;font-size:13"><?= $order['status'] ?></span>
                            <?php if ($user['role']==='seller'): ?>
                            <form method="POST" style="display:inline"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="order_id" value="<?= $order['id'] ?>"><select name="status" onchange="this.form.submit()" style="padding:8px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;cursor:pointer"><option <?= $order['status']=='Pending'?'selected':'' ?>>Pending</option><option <?= $order['status']=='Shipped'?'selected':'' ?>>Shipped</option><option <?= $order['status']=='Delivered'?'selected':'' ?>>Delivered</option></select></form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:16;flex-wrap:wrap"><?php foreach ($items as $i): ?><div style="display:flex;align-items:center;gap:10;background:#150e30;border-radius:10;padding:10px 14px"><span style="font-size:28"><?= $i['image'] ?></span><div><div style="font-size:13;font-weight:600;color:#120f2a"><?= substr($i['name'],0,28) ?>...</div><div style="font-size:12;color:rgba(240,236,232,0.6)">×<?= $i['qty'] ?> · <?= fmt($i['price']) ?></div></div></div><?php endforeach; ?></div>
                    <div style="text-align:right;margin-top:16;font-weight:800;font-size:18;color:#1f1542">Total: <?= fmt($order['total']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php elseif ($page === 'seller' && $user && $user['role']==='seller'):
        $myProducts = getProducts($pdo, 'All', '', 'newest'); // Simplified fetch
        $myProducts = array_filter($myProducts, fn($p)=>$p['sellerId']===$user['id']);
        $totalRevenue = 0; foreach($myProducts as $p) $totalRevenue += $p['price'] * $p['sold'];
        ?>
        <div style="max-width:1300;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28">
                <h1 style="font-size:28;font-weight:900;color:#120f2a;margin:0;font-family:'Playfair Display',serif">🏪 Seller Dashboard</h1>
                <button onclick="document.getElementById('addModal').style.display='flex'" style="background:linear-gradient(135deg, #c9a84c, #f0d080);color:#1f1542;border:none;padding:12px 24px;border-radius:10;font-weight:700;cursor:pointer;font-family:'Outfit',sans-serif;font-size:14">+ List New Product</button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16;margin-bottom:28">
                <?php foreach([["Total Products",count($myProducts),"📦","#1f1542"],["Total Revenue",fmt($totalRevenue),"💰","#4caf50"],["Total Sold",number_format(array_sum(array_column($myProducts,'sold'))),"🛍️","#c9a84c"],["Avg Rating",count($myProducts)?number_format(array_sum(array_column($myProducts,'rating'))/count($myProducts),1)."★":"N/A","⭐","#e53935"]] as $s): ?>
                <div style="background:#1a1035;border-radius:14;padding:20;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15);border-left:4px solid <?= $s[3] ?>"><div style="font-size:28"><?= $s[2] ?></div><div style="font-size:22;font-weight:900;color:<?= $s[3] ?>;margin-top:8"><?= $s[1] ?></div><div style="font-size:13;color:rgba(240,236,232,0.6);margin-top:4"><?= $s[0] ?></div></div>
                <?php endforeach; ?>
            </div>
            <div style="background:#1a1035;border-radius:14;box-shadow:0 2px 20px rgba(0,0,0,0.3);overflow:hidden;border:1px solid rgba(201,168,76,0.15)">
                <div style="padding:20px 24px;border-bottom:1px solid rgba(201,168,76,0.15)"><h3 style="margin:0;font-weight:900;color:#120f2a">My Products (<?= count($myProducts) ?>)</h3></div>
                <?php if (empty($myProducts)): ?>
                <div style="text-align:center;padding:60;color:rgba(240,236,232,0.6)"><div style="font-size:48;margin-bottom:12">📦</div><p>No products listed yet.</p></div>
                <?php else: ?>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse"><thead><tr style="background:#150e30"><?php foreach(["Product","Category","Price","Stock","Rating","Sold","Actions"] as $h): ?><th style="padding:14px 20px;text-align:left;font-size:12;font-weight:700;color:rgba(240,236,232,0.6);text-transform:uppercase"><?= $h ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($myProducts as $p): ?><tr style="border-top:1px solid rgba(201,168,76,0.08)"><td style="padding:14px 20px"><div style="display:flex;align-items:center;gap:10"><span style="font-size:28"><?= $p['image'] ?></span><span style="font-weight:600;font-size:14;color:#120f2a"><?= substr($p['name'],0,30) ?>...</span></div></td><td style="padding:14px 20px;font-size:13;color:#10b981;font-weight:600"><?= $p['category'] ?></td><td style="padding:14px 20px;font-size:14;font-weight:700;color:#1f1542"><?= fmt($p['price']) ?></td><td style="padding:14px 20px"><span style="background:<?= $p['stock']<5?'#ffebee':'#e8f5e9' ?>;color:<?= $p['stock']<5?'#e53935':'#4caf50' ?>;padding:4px 10px;border-radius:6;font-size:12;font-weight:700"><?= $p['stock'] ?></span></td><td style="padding:14px 20px;color:#c9a84c;font-weight:700"><?= $p['rating'] ?>★</td><td style="padding:14px 20px;font-size:13;color:rgba(240,236,232,0.6)"><?= number_format($p['sold']) ?></td><td style="padding:14px 20px"><form method="POST" style="display:inline"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button type="submit" style="background:#ffebee;border:none;padding:6px 14px;border-radius:6;cursor:pointer;font-family:'Outfit',sans-serif;font-size:12;font-weight:700;color:#e53935">Delete</button></form></td></tr><?php endforeach; ?></tbody></table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Add Product Modal -->
        <div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:9998;padding:20" onclick="if(event.target===this)this.style.display='none'">
            <div style="background:#1a1035;border-radius:16;padding:32;width:500;max-height:90vh;overflow-y:auto;box-shadow:0 30px 80px rgba(0,0,0,0.3)">
                <h3 style="margin:0 0 24px;font-weight:900;color:#120f2a;font-size:20">➕ Add New Product</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:8">Product Icon</label><div style="display:flex;gap:8;flex-wrap:wrap"><?php foreach(["📦","💻","📱","🎧","📺","⌚","👟","👗","👖","🧥","🍲","🌀","☕","🥄","🔧"] as $e): ?><span onclick="document.getElementById('pimg').value='<?= $e ?>';this.style.background='#f5f0e8';this.style.border='2px solid #1f1542'" style="font-size:28;cursor:pointer;padding:8;border-radius:8;border:2px solid transparent"><?= $e ?></span><?php endforeach; ?><input type="hidden" id="pimg" name="image" value="📦"></div></div>
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Product Name *</label><input name="name" required style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;box-sizing:border-box;background:#120f2a;color:#f0ece8"></div>
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Category</label><select name="category" style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13"><option>Electronics</option><option>Fashion</option><option>Home & Kitchen</option></select></div>
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Brand</label><input name="brand" style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;box-sizing:border-box;background:#120f2a;color:#f0ece8"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12;margin-bottom:14"><div><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Price</label><input name="price" type="number" required style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;box-sizing:border-box;background:#120f2a;color:#f0ece8"></div><div><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Original Price</label><input name="originalPrice" type="number" style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;box-sizing:border-box;background:#120f2a;color:#f0ece8"></div></div>
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Stock</label><input name="stock" type="number" required style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;box-sizing:border-box;background:#120f2a;color:#f0ece8"></div>
                    <div style="margin-bottom:14"><label style="display:block;font-size:12;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:5">Description</label><textarea name="description" rows="3" style="width:100%;padding:10px 12px;border-radius:8;border:1px solid rgba(201,168,76,0.2);font-family:'Outfit',sans-serif;font-size:13;resize:vertical;box-sizing:border-box"></textarea></div>
                    <div style="display:flex;gap:12"><button type="button" onclick="document.getElementById('addModal').style.display='none'" style="flex:1;background:#f0ede8;border:none;padding:12px;border-radius:8;cursor:pointer;font-family:'Outfit',sans-serif;font-weight:700">Cancel</button><button type="submit" style="flex:2;background:#1f1542;color:#fff;border:none;padding:12px;border-radius:8;cursor:pointer;font-family:'Outfit',sans-serif;font-weight:800;font-size:15">Save Product</button></div>
                </form>
            </div>
        </div>
    <?php elseif ($page === 'profile' && $user):
        $myOrders = getOrders($pdo, $user['id']);
        ?>
        <div style="max-width:900;margin:0 auto;padding:32px 40px;font-family:'Outfit',sans-serif">
            <h1 style="font-size:28;font-weight:900;color:#120f2a;margin:0 0 24px;font-family:'Playfair Display',serif">My Profile</h1>
            <div style="display:grid;grid-template-columns:1fr 2fr;gap:24">
                <div style="background:#1a1035;border-radius:16;padding:28;box-shadow:0 2px 20px rgba(0,0,0,0.3);text-align:center;border:1px solid rgba(201,168,76,0.15)">
                    <div style="width:80;height:80;background:linear-gradient(135deg, #c9a84c, #f0d080);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28;font-weight:900;color:#fff;margin:0 auto 16px"><?= $user['avatar'] ?></div>
                    <h2 style="margin:0 0 4px;font-size:20;color:#120f2a"><?= $user['name'] ?></h2>
                    <div style="color:rgba(240,236,232,0.6);font-size:13;margin-bottom:8"><?= $user['email'] ?></div>
                    <span style="background:<?= $user['role']==='seller'?'#e8f5e9':'#f5f0e8' ?>;color:<?= $user['role']==='seller'?'#4caf50':'#1f1542' ?>;padding:4px 14px;border-radius:20;font-size:12;font-weight:700"><?= $user['role']==='seller'?'🏪 Seller':'👤 Buyer' ?></span>
                    <?php if ($user['role']==='buyer'): ?><div style="margin-top:20;padding:16px 0;border-top:1px solid rgba(201,168,76,0.15)"><div style="font-weight:900;font-size:24;color:#1f1542"><?= count($myOrders) ?></div><div style="font-size:13;color:rgba(240,236,232,0.6)">Total Orders</div></div><?php endif; ?>
                </div>
                <div style="background:#1a1035;border-radius:16;padding:28;box-shadow:0 2px 20px rgba(0,0,0,0.3);border:1px solid rgba(201,168,76,0.15)">
                    <h3 style="margin:0 0 20px;font-weight:900;color:#120f2a">Account Details</h3>
                    <?php foreach([["Full Name",$user['name']],["Email",$user['email']],["Account Type",ucfirst($user['role'])],["Account ID",$user['id']]] as $row): ?>
                    <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f0f0"><span style="color:rgba(240,236,232,0.6);font-size:14"><?= $row[0] ?></span><span style="font-weight:600;font-size:14;color:#120f2a"><?= $row[1] ?></span></div>
                    <?php endforeach; ?>
                    <div style="margin-top:20;padding:16;background:rgba(201,168,76,0.08);border-radius:10;font-size:13;color:rgba(240,236,232,0.6)"><strong style="color:#1f1542">Demo Notice:</strong> This is a demo platform. Profile editing is not persisted.</div>
                </div>
            </div>
        </div>
    <?php elseif ($page === 'login'):
        ?>
        <div style="min-height:80vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, #120f2a 0%, #0f2d1a 100%);padding:40;font-family:'Outfit',sans-serif">
            <div style="background:#1a1030;border-radius:20;padding:40;width:420;box-shadow:0 20px 60px rgba(0,0,0,0.12)">
                <div style="text-align:center;margin-bottom:32"><div style="font-size:40;margin-bottom:8">🛍️</div><h2 style="margin:0;font-family:'Playfair Display',serif;color:#120f2a;font-size:26">Welcome Back</h2><p style="color:rgba(240,236,232,0.6);font-size:14;margin:8px 0 0">Sign in to your account</p></div>
                <div style="margin-bottom:20;padding:16;background:rgba(201,168,76,0.08);border-radius:10;border:1px solid #f5f0e8">
                    <div style="font-size:12;font-weight:700;color:rgba(240,236,232,0.6);margin-bottom:8">Demo Accounts:</div>
                    <div style="display:flex;gap:8;flex-wrap:wrap">
                        <button onclick="document.querySelector('input[name=email]').value='buyer@test.com';document.querySelector('input[name=password]').value='test123'" style="background:#f5f0e8;border:1px solid #d4a843;padding:6px 12px;border-radius:6;cursor:pointer;font-size:12;color:#1f1542;font-family:'Outfit',sans-serif;font-weight:600">👤 Buyer</button>
                        <button onclick="document.querySelector('input[name=email]').value='seller@test.com';document.querySelector('input[name=password]').value='test123'" style="background:#f5f0e8;border:1px solid #d4a843;padding:6px 12px;border-radius:6;cursor:pointer;font-size:12;color:#1f1542;font-family:'Outfit',sans-serif;font-weight:600">🏪 Seller</button>
                    </div>
                    <div style="font-size:11;color:#888;margin-top:6">Password for all: test123</div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">Email</label><input name="email" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8"></div>
                    <div style="margin-bottom:16"><label style="display:block;font-size:13;font-weight:700;color:rgba(240,236,232,0.75);margin-bottom:6">Password</label><input name="password" type="password" required style="width:100%;padding:12px 16px;border-radius:8;border:2px solid rgba(201,168,76,0.25);font-family:'Outfit',sans-serif;font-size:14;box-sizing:border-box;outline:none;background:#120f2a;color:#f0ece8"></div>
                    <button type="submit" style="width:100%;background:linear-gradient(135deg, #c9a84c, #f0d080);color:#1f1542;border:none;padding:14px;border-radius:10;font-weight:800;cursor:pointer;font-family:'Outfit',sans-serif;font-size:16;margin-bottom:16">Sign In</button>
                </form>
                <div style="text-align:center;font-size:14;color:rgba(240,236,232,0.6)">Don't have an account? <span style="color:#1f1542;font-weight:700;cursor:pointer">Sign Up</span></div>
            </div>
        </div>
    <?php else: header("Location: ?page=home"); endif; ?>
</main>
 
<?php renderFooter(); ?>
 
<script>
function toggleMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', function(e) {
    const menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('button') && !e.target.closest('#userMenu')) {
        menu.style.display = 'none';
    }
});
</script>
</body>
</html>
