<?php
session_start();

/*
 * Configuration - set your DB credentials here
 */
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';    // set if you have a root password
$DB_NAME = 'kikeez_db';

/* Connect */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

/* Simple helper */
function h($s){ return htmlspecialchars($s, ENT_QUOTES); }

/* Menu definition: 10 tiffins */
$menu = [
    ['id'=>1,'name'=>'Masala Dosa','price'=>80.00,'img'=>'https://i0.wp.com/binjalsvegkitchen.com/wp-content/uploads/2015/12/Masala-Dosa-L1.jpg?resize=600%2C900&ssl=1'],
    ['id'=>2,'name'=>'Plain Dosa','price'=>60.00,'img'=>'https://t3.ftcdn.net/jpg/01/86/33/72/360_F_186337209_9rbcMLu3wGCDNaEoK1jO0aNzb0pv7Xs7.jpg'],
    ['id'=>3,'name'=>'Rava Dosa','price'=>90.00,'img'=>'https://www.vegrecipesofindia.com/wp-content/uploads/2018/09/rava-dosa-recipe-1.jpg'],
    ['id'=>4,'name'=>'Idli (2 pcs)','price'=>50.00,'img'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTAPWFakUoREk2_Z4i7iH2EogYGcrcioYkNWA&s'],
    ['id'=>5,'name'=>'Medu Vada (2 pcs)','price'=>55.00,'img'=>'https://thumbs.dreamstime.com/b/delicious-medu-vada-sambar-chutney-served-plate-green-leaf-popular-south-indian-breakfast-dish-traditional-cuisine-385702711.jpg'],
    ['id'=>6,'name'=>'Upma','price'=>45.00,'img'=>'https://myfoodstory.com/wp-content/uploads/2022/11/Vegetable-Upma-3.jpg'],
    ['id'=>7,'name'=>'Pongal','price'=>70.00,'img'=>'https://www.indianhealthyrecipes.com/wp-content/uploads/2022/05/ven-pongal-recipe.jpg'],
    ['id'=>8,'name'=>'Set Dosa','price'=>85.00,'img'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSRpOuOoaxHgLzKwiJ8oooMAO40_k0HFMJJSg&s'],
    ['id'=>9,'name'=>'Puri + Potato','price'=>75.00,'img'=>'https://pipingpotcurry.com/wp-content/uploads/2018/07/Instant-Pot-Potato-Curry-in-Tomato-Sauce-1.jpg'],
    ['id'=>10,'name'=>'Masala Uttapam','price'=>95.00,'img'=>'https://c.ndtvimg.com/2022-08/4i2h41mo_uttapam_625x300_10_August_22.jpg']
];

/* Initialize cart in session */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ROUTING based on action */
$action = $_REQUEST['action'] ?? 'login';

/* ---------- SIGNUP ---------- */
if ($action === 'signup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $type = ($_POST['type'] === 'owner') ? 'owner' : 'customer';
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 4) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (email, password, type) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $email, $hash, $type);
        if ($stmt->execute()) {
            $_SESSION['user_id'] = $stmt->insert_id;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_type'] = $type;
            header('Location: ?action=welcome');
            exit;
        } else {
            $error = "Signup failed: " . h($stmt->error);
        }
    } else {
        $error = "Enter a valid email and password (min 4 chars).";
    }
}

/* ---------- LOGIN ---------- */
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $type = ($_POST['type'] === 'owner') ? 'owner' : 'customer';
    if ($type === 'owner' && $email === "kikeez123@gmail.com" && $password === "123456") {
        $_SESSION['user_id'] = 999; // any dummy id
        $_SESSION['user_email'] = $email;
        $_SESSION['user_type'] = "owner";
        header('Location: ?action=owner');
        exit;
    }
   $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param('s', $email);
$stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (password_verify($password, $row['password']) && $row['type'] === $type) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_type'] = $row['type'];
            header('Location: ?action=welcome');
            exit;
        } else {
            $error = "Wrong credentials or wrong user type selected.";
        }
    } else {
        $error = "User not found. Please sign up.";
    }
}

/* ---------- LOGOUT ---------- */
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

/* ---------- ADD TO CART ---------- */
if ($action === 'add_cart' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    foreach ($menu as $m) if ($m['id'] === $id) { $item = $m; break; }

    if (!isset($item)) {
        $_SESSION['msg'] = "❌ Item not found!";
    } else {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$id] = ['item'=>$item, 'qty'=>$qty];
        }

        $_SESSION['msg'] = "✅ ".$item['name']." added to cart!";
    }

    header('Location: ?action=menu');
    exit;
}


/* ---------- REMOVE FROM CART ---------- */
if ($action === 'remove' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
    header('Location: ?action=cart');
    exit;
}

/* ---------- PLACE ORDER ---------- */
if ($action === 'place_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        $error = "Please login to place an order.";
    } elseif (empty($_SESSION['cart'])) {
        $error = "Cart is empty.";
    } else {
        $dining_type = ($_POST['dining_type'] === 'dining') ? 'dining' : 'parcel';
        $user_id = $_SESSION['user_id'];
        // calculate total
        $total = 0.0;
        $items_arr = [];
        foreach ($_SESSION['cart'] as $cart_item) {
            $items_arr[] = [
                'id' => $cart_item['item']['id'],
                'name' => $cart_item['item']['name'],
                'price' => $cart_item['item']['price'],
                'qty' => $cart_item['qty']
            ];
            $total += $cart_item['item']['price'] * $cart_item['qty'];
        }
        $order_id = uniqid('KZORD'); // unique order id
        $items_json = json_encode($items_arr, JSON_UNESCAPED_UNICODE);

        $stmt = $conn->prepare("INSERT INTO orders (order_id, user_id, items, total, dining_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sisss', $order_id, $user_id, $items_json, $total, $dining_type);
        if ($stmt->execute()) {
            // success - clear cart and show bill
            $_SESSION['cart'] = [];
            header('Location: ?action=order_success&order_id=' . urlencode($order_id));
            exit;
        } else {
            $error = "Failed to place order: " . h($stmt->error);
        }
    }
}

/* ---------- FETCH ORDER FOR SUCCESS PAGE ---------- */
if ($action === 'order_success' && isset($_GET['order_id'])) {
    $oid = $_GET['order_id'];
    $stmt = $conn->prepare("SELECT o.*, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.order_id = ?");
    $stmt->bind_param('s', $oid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($order_row = $res->fetch_assoc()) {
        // ok
    } else {
        $error = "Order not found.";
    }
}

/* ---------- Simple Owner view (optional) ---------- */
if ($action === 'owner' && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'owner') {
    // show last 20 orders
    $orders = [];
    $q = $conn->query("SELECT o.order_id,o.user_id,o.items,o.total,o.dining_type,o.created_at,u.email FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 50");
    while ($r = $q->fetch_assoc()) $orders[] = $r;
}

/* ---------- HTML templates below ---------- */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Kikkeez - South Indian Tiffins</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; margin:0; padding:0; background:#fff9c4; }
    header { background:#fff; padding:12px 20px; box-shadow:0 1px 4px rgba(0,0,0,0.08); display:flex; align-items:center; justify-content:space-between; }
    .logo {
    text-align: right;      /* centers text */
    margin-top: 15px;        /* push down from top */
}

.logo h1 {
    font-family: 'Segoe UI', Arial, sans-serif; /* change font */
    font-size: 700px;         /* bigger size */
    font-weight: 700;        /* bold */
    color: #0056d2;          /* blue text */
    letter-spacing: 5px;     /* spacing between letters */
    text-transform: uppercase; /* full capital */
}

    nav a { margin-left:16px; text-decoration:none; color:#333; }
    nav a, .right-top a {
        background:#007bff;
        color:white !important;
        padding:6px 12px;
        border-radius:6px;
        text-decoration:none;
        margin-left:6px;
        font-size:14px;
    }
    nav a:hover, .right-top a:hover {
        background:#236307;
    }
    .container { max-width:2000px; margin:80px auto; padding:50px; background:#fff; border-radius:6px; box-shadow:5px 6px rgba(0,0,0,0.06); }
    .row { display:flex; gap:20px; flex-wrap:wrap; }
    .col { flex:1; min-width:220px; }
    .menu-item { border:1px solid #eee; padding:8px; border-radius:6px; text-align:center; background:#fafafa; }
    img { max-width:100%; height:auto; border-radius:6px; }
    .right-top { text-align:right; }
    .welcome-center { text-align:center; padding:40px; }
    .btn { padding:8px 14px; border-radius:6px; display:inline-block; text-decoration:none; background:#2b7a0b; color:#fff; }
    form { margin:0; }
    .cart-list { width:100%; border-collapse:collapse; }
    .cart-list th, .cart-list td { padding:8px; border:1px solid #eee; text-align:left; }
    .small { font-size:0.9rem; color:#666; }
    .error { color: #b00020; margin:8px 0; }
    footer { text-align:center; padding:10px; color:#666; font-size:14px; margin-top:20px; }
  </style>
</head>
<body>

<header>
  <div class="logo">Kikkeez (South Indian Tiffins)</div>
  <div class="right-top">
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="?action=login">Login</a> | <a href="?action=signup">Sign up</a>
    <?php else: ?>
      <span class="small">Hi, <?=h($_SESSION['user_email'])?> (<?=h($_SESSION['user_type'])?>)</span>
      <a href="?action=welcome">Home</a>
      <a href="?action=menu">Menu</a>
      <a href="?action=aboutus">About Us</a>
      <a href="?action=cart">Cart (<?=array_sum(array_map(function($c){return $c['qty'];}, $_SESSION['cart']))?>)</a>
      <?php if ($_SESSION['user_type'] === 'owner'): ?>
        <a href="?action=owner">Owner</a>
      <?php endif; ?>
      <a href="?action=logout">Logout</a>
    <?php endif; ?>
  </div>
</header>

<div class="container">
    <?php if (!empty($error)): ?>
      <div class="error"><?=h($error)?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['msg'])): ?>
    <div style="color: green; font-weight:bold; margin-bottom:10px;">
        <?=h($_SESSION['msg'])?>
    </div>
    <?php unset($_SESSION['msg']); ?>
<?php endif; ?>


    <!-- LOGIN -->
    <?php if ($action === 'login' && !isset($_SESSION['user_id'])): ?>
      <h2>Login</h2>
      <form method="post" action="?action=login">
        <label>Email:<br><input type="email" name="email" required></label><br><br>
        <label>Password:<br><input type="password" name="password" required></label><br><br>
        <label>User type:
          <select name="type">
            <option value="customer">Customer</option>
            <option value="owner">Owner</option>
          </select>
        </label><br><br>
        <button class="btn" type="submit">Login</button>
      </form>

    <!-- SIGNUP -->
    <?php elseif ($action === 'signup' && !isset($_SESSION['user_id'])): ?>
      <h2>Sign up</h2>
      <form method="post" action="?action=signup">
        <label>Email:<br><input type="email" name="email" required></label><br><br>
        <label>Password:<br><input type="password" name="password" required></label><br><br>
        <label>User type:
          <select name="type">
            <option value="customer">Customer</option>
            <option value="owner">Owner</option>
          </select>
        </label><br><br>
        <button class="btn" type="submit">Sign up</button>
      </form>

    <!-- WELCOME / HOME -->
    <?php elseif ($action === 'welcome' || $action === 'menu' || $action === 'aboutus' || $action === 'cart' || $action === 'order_success' || $action === 'owner'): ?>

      <?php if ($action === 'welcome'): ?>
        <div class="welcome-center">
          <h1 style="font-family:serif;">Kikkeez</h1>
          <p style="max-width:800px; margin:0 auto; text-align:justify;">
            Kikkeez is a celebration of authentic South Indian flavours — from the crisp, golden dosas to the soft, steaming idlis that pair perfectly with tangy chutneys and rich sambar.
            Our tiffins are prepared using traditional recipes, fresh ingredients, and a passion for taste that brings the warmth of home-cooked south Indian meals to every plate.
            Whether you crave the flavorful masala dosa, comforting pongal, or spicy uttapam, Kikkeez promises a delightful and comforting meal experience.
          </p>
        </div>
      <?php endif; ?>

      <?php if ($action === 'aboutus'): ?>
        <h2>About Us</h2>
        <p>
          Kikkeez South Indian Hotels is a small chain dedicated to serving classic south Indian tiffins with care.
          We take pride in traditional recipes, fresh dosa batter, homemade sambar, and coconut chutney.
          Our mission is to share the joy of South Indian breakfast and tiffin culture with friendly service and clean dining.
        </p>
      <?php endif; ?>

      <?php if ($action === 'menu'): ?>
        <h2>Menu</h2>
        <div class="row">
          <?php foreach ($menu as $m): ?>
            <div class="col">
              <div class="menu-item">
                <img src="<?=h($m['img'])?>" alt="<?=h($m['name'])?>">
                <h3><?=h($m['name'])?></h3>
                <p class="small">₹ <?=number_format($m['price'],2)?></p>
                <form method="post" action="?action=add_cart">
                  <input type="hidden" name="id" value="<?=h($m['id'])?>">
                  <label>Qty: <input type="number" name="qty" value="1" min="1" style="width:60px"></label><br><br>
                  <button class="btn" type="submit">Add to cart</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($action === 'cart'): ?>
        <h2>Your Cart</h2>
        <?php if (empty($_SESSION['cart'])): ?>
          <p>Your cart is empty. <a href="?action=menu">Go to menu</a></p>
        <?php else: ?>
          <table class="cart-list">
            <tr><th>Item</th><th>Price</th><th>Qty</th><th>Subtotal</th><th>Action</th></tr>
            <?php $total=0; foreach ($_SESSION['cart'] as $id=>$c): $sub = $c['item']['price']*$c['qty']; $total += $sub; ?>
              <tr>
                <td><?=h($c['item']['name'])?></td>
                <td>₹ <?=number_format($c['item']['price'],2)?></td>
                <td><?=h($c['qty'])?></td>
                <td>₹ <?=number_format($sub,2)?></td>
                <td><a href="?action=remove&id=<?=h($id)?>">Remove</a></td>
              </tr>
            <?php endforeach; ?>
            <tr><th colspan="3">Total</th><th colspan="2">₹ <?=number_format($total,2)?></th></tr>
          </table>

          <h3>Choose type and place order</h3>
          <form method="post" action="?action=place_order">
            <label><input type="radio" name="dining_type" value="dining" checked> Dining</label>
            <label><input type="radio" name="dining_type" value="parcel"> Parcel</label><br><br>
            <button class="btn" type="submit">Place Order</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($action === 'order_success' && isset($order_row)): ?>
        <h2>Order Placed</h2>
        <p>Thank you, <?=h($order_row['email'])?>! Your order has been placed.</p>
        <p><strong>Order ID:</strong> <?=h($order_row['order_id'])?></p>
        <p><strong>Placed at:</strong> <?=h($order_row['created_at'])?></p>
        <p><strong>Type:</strong> <?=h($order_row['dining_type'])?></p>
        <h3>Items</h3>
        <?php
          $items = json_decode($order_row['items'], true);
        ?>
        <ul>
          <?php foreach ($items as $it): ?>
            <li><?=h($it['name'])?> × <?=h($it['qty'])?> — ₹ <?=number_format($it['price'],2)?></li>
          <?php endforeach; ?>
        </ul>
        <p><strong>Total:</strong> ₹ <?=number_format($order_row['total'],2)?></p>
      <?php endif; ?>

      <?php if ($action === 'owner' && $_SESSION['user_type']==='owner'): ?>
        <h2>Owner Dashboard — Recent Orders</h2>
        <?php if (empty($orders)): ?>
          <p>No orders yet.</p>
        <?php else: ?>
          <table class="cart-list">
            <tr><th>Order ID</th><th>User</th><th>Items</th><th>Total</th><th>Type</th><th>Time</th></tr>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><?=h($o['order_id'])?></td>
                <td><?=h($o['email'])?></td>
                <td style="max-width:480px;">
                  <?php
                    $its = json_decode($o['items'], true);
                    foreach ($its as $ii) echo h($ii['name'])." × ".h($ii['qty'])."<br>";
                  ?>
                </td>
                <td>₹ <?=number_format($o['total'],2)?></td>
                <td><?=h($o['dining_type'])?></td>
                <td><?=h($o['created_at'])?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      <?php endif; ?>

    <?php else: ?>
      <!-- default route: if logged in show welcome, else show login -->
      <?php if (isset($_SESSION['user_id'])) header('Location: ?action=welcome'); else header('Location: ?action=login'); exit; ?>
    <?php endif; ?>

</div>

<footer>
  © <?=date('Y')?> Kikkeez — South Indian tiffins.
</footer>

</body>
</html>
