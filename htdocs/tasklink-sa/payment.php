<?php
session_start();
require_once 'includes/db.php';

// Must be logged in as client
if (!isset($_SESSION['user_id']) || 
    $_SESSION['role'] !== 'client') {
    header('Location: /login.php');
    exit();
}

$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    header('Location: /my-bookings.php');
    exit();
}

// Get booking details
$stmt = $pdo->prepare("
    SELECT b.*, 
           s.title as service_title,
           s.price as service_price,
           s.price_type,
           u.full_name as provider_name,
           c.category_name
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.provider_id = u.user_id
    JOIN categories c ON s.category_id = c.category_id
    WHERE b.booking_id = ? 
    AND b.client_id = ?
    AND b.status = 'accepted'
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: /my-bookings.php');
    exit();
}

// Check if already paid
$stmt = $pdo->prepare("
    SELECT payment_id FROM payments 
    WHERE booking_id = ?
");
$stmt->execute([$booking_id]);
if ($stmt->fetch()) {
    header('Location: /my-bookings.php');
    exit();
}

$error   = '';
$success = '';

$total      = $booking['estimated_hours'] * $booking['service_price'];
$commission = $total * 0.10;
$provider_earns = $total * 0.90;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';

    if (empty($payment_method)) {
        $error = 'Please select a payment method.';
    } else {
        // Insert payment
        $stmt = $pdo->prepare("
            INSERT INTO payments 
            (booking_id, amount, commission, status)
            VALUES (?, ?, ?, 'completed')
        ");
        $stmt->execute([$booking_id, $total, $commission]);

        // Update booking status to completed
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'completed' 
            WHERE booking_id = ?
        ");
        $stmt->execute([$booking_id]);

        header('Location: /leave-review.php?id=' . $booking_id . '&paid=1');
        exit();
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:700px; margin:0 auto; 
            padding:40px 24px;">

    <a href="/my-bookings.php"
       style="color:#1B6B3A; font-size:14px; 
              font-weight:600; text-decoration:none;
              display:inline-block; margin-bottom:24px;">
        ← Back to My Bookings
    </a>

    <h1 style="font-size:24px; font-weight:700; 
               color:#1A202C; margin-bottom:24px;">
        Select Payment Method
    </h1>

    <?php if($error): ?>
        <div class="alert-error" 
             style="margin-bottom:20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <!-- Payment Methods -->
        <div style="display:grid; 
                    grid-template-columns:repeat(4,1fr); 
                    gap:12px; margin-bottom:24px;">

            <?php 
            $methods = [
                'google_pay' => ['💳', 'Google Pay'],
                'visa'       => ['💳', 'Visa Card'],
                'ebucks'     => ['💰', 'eBucks'],
                'cash'       => ['💵', 'Cash'],
            ];
            foreach($methods as $value => $method): 
            ?>
                <label style="cursor:pointer;">
                    <input type="radio" 
                           name="payment_method" 
                           value="<?php echo $value; ?>"
                           style="display:none;"
                           class="payment-radio">
                    <div class="payment-option"
                         style="border:2px solid #E2E8F0; 
                                border-radius:12px; 
                                padding:16px; 
                                text-align:center; 
                                transition:all 0.2s;
                                cursor:pointer;">
                        <div style="font-size:28px; 
                                    margin-bottom:8px;">
                            <?php echo $method[0]; ?>
                        </div>
                        <div style="font-size:12px; 
                                    font-weight:600; 
                                    color:#4A5568;">
                            <?php echo $method[1]; ?>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <!-- Payment Summary -->
        <div class="card" style="margin-bottom:24px;">
            <h3 style="font-size:16px; font-weight:700; 
                       color:#1A202C; margin-bottom:16px;">
                Payment Summary
            </h3>

            <div style="display:flex; 
                        justify-content:space-between; 
                        font-size:13px; color:#4A5568; 
                        margin-bottom:10px;">
                <span>Service:</span>
                <span style="font-weight:600; color:#1A202C;">
                    <?php echo htmlspecialchars($booking['service_title']); ?>
                </span>
            </div>

            <div style="display:flex; 
                        justify-content:space-between; 
                        font-size:13px; color:#4A5568; 
                        margin-bottom:10px;">
                <span>Provider:</span>
                <span style="font-weight:600; color:#1A202C;">
                    <?php echo htmlspecialchars($booking['provider_name']); ?>
                </span>
            </div>

            <div style="display:flex; 
                        justify-content:space-between; 
                        font-size:13px; color:#4A5568; 
                        margin-bottom:10px;">
                <span>Hourly Rate:</span>
                <span>R<?php echo number_format($booking['service_price'], 2); ?>/hr</span>
            </div>

            <div style="display:flex; 
                        justify-content:space-between; 
                        font-size:13px; color:#4A5568; 
                        margin-bottom:10px;">
                <span>Hours Worked:</span>
                <span><?php echo $booking['estimated_hours']; ?> hrs</span>
            </div>

            <div style="display:flex; 
                        justify-content:space-between; 
                        font-size:13px; color:#4A5568; 
                        margin-bottom:10px;">
                <span>Amount:</span>
                <span>
                    R<?php echo number_format($booking['service_price'], 2); ?> 
                    × <?php echo $booking['estimated_hours']; ?> 
                    = R<?php echo number_format($total, 2); ?>
                </span>
            </div>

            <div style="border-top:1px solid #E2E8F0; 
                        padding-top:14px; margin-top:6px;">
                <div style="display:flex; 
                            justify-content:space-between; 
                            margin-bottom:8px;">
                    <span style="font-size:13px; 
                                 color:#4A5568;">
                        Platform commission (10%):
                    </span>
                    <span style="font-size:13px; 
                                 color:#F5A623; 
                                 font-weight:600;">
                        R<?php echo number_format($commission, 2); ?>
                    </span>
                </div>
                <div style="display:flex; 
                            justify-content:space-between;">
                    <span style="font-size:16px; 
                                 font-weight:700; 
                                 color:#1A202C;">
                        Total Due:
                    </span>
                    <span style="font-size:20px; 
                                 font-weight:700; 
                                 color:#1B6B3A;">
                        R<?php echo number_format($total, 2); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn-primary"
                style="width:100%; padding:16px; 
                       font-size:16px;">
            💳 Pay R<?php echo number_format($total, 2); ?>
        </button>

        <p style="text-align:center; font-size:12px; 
                  color:#A0AEC0; margin-top:12px;">
            🔒 This is a simulated payment — 
            no real money will be charged
        </p>

    </form>
</div>

<style>
.payment-radio:checked + .payment-option {
    border-color: #1B6B3A;
    background: #E8F5EE;
}
.payment-option:hover {
    border-color: #1B6B3A;
}
</style>

<?php require_once 'includes/footer.php'; ?>