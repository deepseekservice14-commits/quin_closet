<?php
// Start session to manage cart
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'quin_closet_db';
$username = 'root';
$password = '';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add to cart functionality
if (isset($_GET['add_to_cart']) && is_numeric($_GET['add_to_cart'])) {
    $product_id = (int)$_GET['add_to_cart'];
    
    // Check if product exists
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Add to cart or increase quantity
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity']++;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image_path'],
                'quantity' => 1
            ];
        }
        
        // Redirect to avoid form resubmission
        $url = strtok($_SERVER['REQUEST_URI'], '?'); // Get base URL without query parameters
        header('Location: ' . $url . '#products');
        exit();
    }
}

// Remove from cart functionality
if (isset($_GET['remove_from_cart']) && is_numeric($_GET['remove_from_cart'])) {
    $product_id = (int)$_GET['remove_from_cart'];
    
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        
        // Redirect to avoid form resubmission
        $url = strtok($_SERVER['REQUEST_URI'], '?'); // Get base URL without query parameters
        header('Location: ' . $url . '#cart');
        exit();
    }
}

// Update cart quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['quantities'] as $product_id => $quantity) {
        if (is_numeric($quantity) && $quantity > 0 && isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] = (int)$quantity;
        } elseif (is_numeric($quantity) && $quantity == 0 && isset($_SESSION['cart'][$product_id])) {
            unset($_SESSION['cart'][$product_id]);
        }
    }
    
    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI'] . '#cart');
    exit();
}

// Process GCash payment
$payment_success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_gcash'])) {
    // Get customer information
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    
    // Calculate total amount
    $total_amount = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total_amount += $item['price'] * $item['quantity'];
    }
    
    $reference_number = 'GC' . time() . rand(100, 999);
    
    // Validate input
    if (empty($name) || empty($email) || empty($phone)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif ($total_amount <= 0) {
        $error_message = "Your cart is empty.";
    } else {
        // Simulate payment processing
        try {
            // Save order to database
            $stmt = $pdo->prepare("INSERT INTO orders (reference_number, customer_name, email, phone, total_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'GCash', 'pending')");
            $stmt->execute([$reference_number, $name, $email, $phone, $total_amount]);
            $order_id = $pdo->lastInsertId();
            
            // Save order items
            foreach ($_SESSION['cart'] as $product_id => $item) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $item['name'], $item['quantity'], $item['price']]);
            }
            
            // Simulate successful payment
            $payment_success = true;
            
            // Update status to completed
            $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
            $stmt->execute([$order_id]);
            
            // Clear the cart
            $_SESSION['cart'] = [];
            
        } catch(PDOException $e) {
            $error_message = "Payment processing error: " . $e->getMessage();
        }
    }
}

// Get all products for display
$products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Calculate cart total
$cart_total = 0;
$cart_count = 0;
foreach ($_SESSION['cart'] as $item) {
    $cart_total += $item['price'] * $item['quantity'];
    $cart_count += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quin's Closet - Fashion Boutique</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .logo span {
            color: #ffdd40;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            align-items: center;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
        }
        
        nav ul li a:hover {
            color: #ffdd40;
        }
        
        .cart-count {
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .hero {
            background: url('https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') no-repeat center center/cover;
            height: 500px;
            display: flex;
            align-items: center;
            color: white;
            text-align: center;
            position: relative;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
        }
        
        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.5);
        }
        
        .hero p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto 30px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
        }
        
        .btn {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 12px 25px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #6a11cb;
            color: #6a11cb;
        }
        
        .btn-outline:hover {
            background: #6a11cb;
            color: white;
        }
        
        .section-title {
            text-align: center;
            margin: 50px 0 30px;
            font-size: 32px;
            color: #333;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 50px;
            height: 4px;
            background: #6a11cb;
            margin: 10px auto;
            border-radius: 2px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }
        
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            height: 250px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-info h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .product-info .price {
            color: #6a11cb;
            font-weight: bold;
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .product-info .description {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        footer {
            background: #333;
            color: white;
            padding: 40px 0;
            text-align: center;
        }
        
        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .footer-logo {
            font-size: 24px;
            margin-bottom: 20px;
        }
        
        .footer-links {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .footer-links a {
            color: #ddd;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: #6a11cb;
        }
        
        .copyright {
            color: #aaa;
            font-size: 14px;
        }
        
        .cart-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 50px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item-image {
            width: 100px;
            height: 100px;
            overflow: hidden;
            border-radius: 8px;
            margin-right: 20px;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            color: #6a11cb;
            font-weight: bold;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .cart-item-quantity input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cart-item-remove {
            color: #ff6b6b;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .cart-item-remove i {
            margin-right: 5px;
        }
        
        .cart-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .cart-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .checkout-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }
        
        .order-summary {
            flex: 1;
            min-width: 300px;
            padding: 30px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        
        .order-summary h2 {
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .payment-form {
            flex: 1;
            min-width: 300px;
            padding: 30px;
        }
        
        .payment-form h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #6a11cb;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            border-color: #6a11cb;
            outline: none;
        }
        
        .gcash-logo {
            text-align: center;
            margin: 20px 0;
        }
        
        .gcash-logo img {
            max-width: 150px;
            height: auto;
        }
        
        .success-message {
            text-align: center;
            padding: 30px;
            background: #f0f9f0;
            border-radius: 10px;
            border: 1px solid #4caf50;
            margin-top: 20px;
        }
        
        .success-message i {
            font-size: 50px;
            color: #4caf50;
            margin-bottom: 20px;
        }
        
        .success-message h2 {
            color: #4caf50;
            margin-bottom: 15px;
        }
        
        .error-message {
            text-align: center;
            padding: 15px;
            background: #ffe6e6;
            border-radius: 8px;
            border: 1px solid #f44336;
            margin-bottom: 20px;
            color: #f44336;
        }
        
        .payment-steps {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
        }
        
        .payment-steps h3 {
            margin-bottom: 15px;
            color: #6a11cb;
        }
        
        .payment-steps ol {
            padding-left: 20px;
        }
        
        .payment-steps li {
            margin-bottom: 10px;
        }
        
        .empty-cart {
            text-align: center;
            padding: 40px;
        }
        
        .empty-cart i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-cart p {
            font-size: 18px;
            color: #888;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 15px;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 36px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .checkout-container {
                flex-direction: column;
            }
            
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cart-item-image {
                margin-bottom: 15px;
            }
            
            .cart-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .cart-actions .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-content">
            <div class="logo">Quin's <span>Closet</span></div>
            <nav>
                <ul>
                    <li><a href="#home">Home</a></li>
                    <li><a href="#products">Products</a></li>
                    <li><a href="#cart">Cart <span class="cart-count"><?php echo $cart_count; ?></span></a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Discover Your Style</h1>
            <p>Explore our exclusive collection of fashion items that reflect your unique personality.</p>
            <a href="#products" class="btn">Shop Now</a>
        </div>
    </section>

    <section id="products" class="container">
        <h2 class="section-title">Featured Products</h2>
        
        <div class="products-grid">
            <?php if (count($products) > 0): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $product['image_path']; ?>" alt="<?php echo $product['name']; ?>">
                        </div>
                        <div class="product-info">
                            <h3><?php echo $product['name']; ?></h3>
                            <p class="price">₱<?php echo number_format($product['price'], 2); ?></p>
                            <p class="description"><?php echo substr($product['description'], 0, 100); ?>...</p>
                            <a href="?add_to_cart=<?php echo $product['id']; ?>" class="btn">Add to Cart</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No products available. Please check back later.</p>
            <?php endif; ?>
        </div>
    </section>

    <section id="cart" class="container">
        <h2 class="section-title">Your Shopping Cart</h2>
        
        <?php if ($cart_count > 0): ?>
        <div class="cart-container">
            <form method="POST" action="">
                <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                <div class="cart-item">
                    <div class="cart-item-image">
                        <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                    </div>
                    <div class="cart-item-details">
                        <div class="cart-item-name"><?php echo $item['name']; ?></div>
                        <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                        <div class="cart-item-quantity">
                            <label for="quantity_<?php echo $product_id; ?>">Quantity:</label>
                            <input type="number" id="quantity_<?php echo $product_id; ?>" name="quantities[<?php echo $product_id; ?>]" value="<?php echo $item['quantity']; ?>" min="1">
                        </div>
                        <a href="?remove_from_cart=<?php echo $product_id; ?>" class="cart-item-remove">
                            <i class="fas fa-trash"></i> Remove
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    
                    <div class="cart-actions">
                        <button type="submit" name="update_cart" class="btn btn-outline">Update Cart</button>
                        <a href="#checkout" class="btn">Proceed to Checkout</a>
                    </div>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <p>Your cart is empty</p>
            <a href="#products" class="btn">Continue Shopping</a>
        </div>
        <?php endif; ?>
    </section>

    <?php if ($cart_count > 0): ?>
    <section id="checkout" class="container">
        <h2 class="section-title">Checkout with GCash</h2>
        
        <?php if ($payment_success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h2>Payment Successful!</h2>
                <p>Thank you for your purchase. Your GCash payment has been processed successfully.</p>
                <p>Reference Number: <strong><?php echo $reference_number; ?></strong></p>
                <p>We've sent a confirmation email to <?php echo $email; ?></p>
                <br>
                <a href="index.php" class="btn">Continue Shopping</a>
            </div>
        <?php else: ?>
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="checkout-container">
                <div class="order-summary">
                    <h2>Order Summary</h2>
                    <div class="order-items">
                        <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                            <div class="order-item">
                                <span><?php echo $item['name']; ?> (x<?php echo $item['quantity']; ?>)</span>
                                <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="order-total">
                        <span>Total Amount:</span>
                        <span>₱<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                </div>
                
                <div class="payment-form">
                    <h2>GCash Payment</h2>
                    
                    <div class="gcash-logo">
                        <img src="https://www.gcash.com/assets/images/logo.png" alt="GCash Logo">
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required placeholder="Enter your full name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Mobile Number</label>
                            <input type="tel" id="phone" name="phone" required placeholder="Enter your mobile number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <input type="hidden" name="amount" value="<?php echo $cart_total; ?>">
                        
                        <button type="submit" name="process_gcash" class="btn">Pay with GCash</button>
                    </form>
                </div>
            </div>
            
            <div class="payment-steps">
                <h3>How to pay with GCash:</h3>
                <ol>
                    <li>Fill in your details and click "Pay with GCash"</li>
                    <li>You will be redirected to the GCash payment page</li>
                    <li>Log in to your GCash account</li>
                    <li>Confirm the payment details</li>
                    <li>You will receive a confirmation message once payment is successful</li>
                </ol>
                <p><strong>Note:</strong> GCash is available for Philippine mobile numbers only.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <footer>
        <div class="container footer-content">
            <div class="footer-logo">Quin's Closet</div>
            <div class="footer-links">
                <a href="#home">Home</a>
                <a href="#products">Products</a>
                <a href="#cart">Cart</a>
            </div>
            <p class="copyright">© 2023 Quin's Closet. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>