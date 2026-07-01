<?php
session_start();
require_once 'includes/db.php';

// Must be logged in as client
if (!isset($_SESSION['user_id']) || 
    $_SESSION['role'] !== 'client') {
    header('Location: /login.php');
    exit();
}

$service_id = (int)($_GET['id'] ?? 0);

if (!$service_id) {
    header('Location: /browse.php');
    exit();
}

// Get service details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as provider_name,
           u.user_id as provider_user_id,
           c.category_name
    FROM services s
    JOIN users u ON s.provider_id = u.user_id
    JOIN categories c ON s.category_id = c.category_id
    WHERE s.service_id = ?
");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: /browse.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date     = $_POST['booking_date'] ?? '';
    $address          = trim($_POST['address'] ?? '');
    $estimated_hours  = (float)($_POST['estimated_hours'] ?? 0);
    $note             = trim($_POST['note'] ?? '');

    if (empty($booking_date)) {
        $error = 'Please select a booking date.';
    } elseif (empty($address)) {
        $error = 'Please enter your service address.';
    } elseif ($estimated_hours <= 0) {
        $error = 'Please enter estimated hours.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO bookings 
            (client_id, provider_id, service_id, 
             booking_date, address, estimated_hours, 
             note, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $service['provider_user_id'],
            $service_id,
            $booking_date,
            $address,
            $estimated_hours,
            $note
        ]);

        header('Location: /my-bookings.php?success=1');
        exit();
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:900px; margin:0 auto; padding:40px 24px;">

    <a href="/service-detail.php?id=<?php echo $service_id; ?>"
       style="color:#1B6B3A; font-size:14px; 
              font-weight:600; text-decoration:none;
              display:inline-block; margin-bottom:24px;">
        ← Back
    </a>

    <div style="display:grid; 
                grid-template-columns:1fr 320px; 
                gap:32px; align-items:start;">

        <!-- ── LEFT — Form ── -->
        <div class="card">
            <h2 style="font-size:20px; font-weight:700; 
                       color:#1A202C; margin-bottom:4px;">
                Book a Service
            </h2>

            <!-- Service reminder -->
            <div style="background:#E8F5EE; 
                        border-radius:10px; padding:14px; 
                        margin:16px 0 24px;">
                <p style="font-size:14px; font-weight:600; 
                          color:#1B6B3A;">
                    <?php echo htmlspecialchars($service['title']); ?>
                </p>
                <p style="font-size:12px; color:#4A5568; 
                          margin-top:2px;">
                    <?php echo htmlspecialchars($service['provider_name']); ?>
                    • R<?php echo number_format($service['price'],2); ?>
                    <?php echo $service['price_type']==='hourly' ? '/hr' : ' fixed'; ?>
                </p>
            </div>

            <?php if($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="form-group">
                    <label>Booking Date *</label>
                    <input type="date" name="booking_date"
                           min="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo $_POST['booking_date'] ?? ''; ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Service Address *</label>
                    <input type="text" name="address"
                           placeholder="e.g. 123 Main Rd, Soweto, GP"
                           value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"
                           required>
                </div>

                <div class="form-group">
                    <label>Estimated Hours *</label>
                    <input type="number" 
                           name="estimated_hours"
                           id="estimatedHours"
                           placeholder="e.g. 3"
                           min="1" max="24" step="0.5"
                           value="<?php echo $_POST['estimated_hours'] ?? ''; ?>"
                           required>
                    <span id="serviceRate" 
                          data-rate="<?php echo $service['price']; ?>"
                          style="display:none;"></span>
                </div>

                <div class="form-group">
                    <label>Additional Notes (optional)</label>
                    <textarea name="note" rows="3"
                              placeholder="Any special instructions for the provider..."
                              style="resize:vertical;"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-primary"
                        style="width:100%; padding:14px; 
                               font-size:16px;">
                    Confirm Booking
                </button>

                <p style="text-align:center; font-size:12px; 
                          color:#A0AEC0; margin-top:12px;">
                    💳 Payment is processed only after 
                    job completion
                </p>
            </form>
        </div>

        <!-- ── RIGHT — Price Summary ── -->
        <div style="position:sticky; top:84px;">
            <div class="card">
                <h3 style="font-size:16px; font-weight:700; 
                           color:#1A202C; margin-bottom:16px;">
                    Price Estimate
                </h3>

                <div style="display:flex; 
                            justify-content:space-between; 
                            font-size:13px; color:#4A5568; 
                            margin-bottom:10px;">
                    <span>Rate:</span>
                    <span style="font-weight:600; color:#1A202C;">
                        R<?php echo number_format($service['price'],2); ?>
                        <?php echo $service['price_type']==='hourly' ? '/hr' : ' fixed'; ?>
                    </span>
                </div>

                <div style="display:flex; 
                            justify-content:space-between; 
                            font-size:13px; color:#4A5568; 
                            margin-bottom:10px;">
                    <span>Hours:</span>
                    <span id="hoursDisplay" 
                          style="font-weight:600; color:#1A202C;">
                        -
                    </span>
                </div>

                <div style="border-top:1px solid #E2E8F0; 
                            padding-top:14px; margin-top:6px;">
                    <div style="display:flex; 
                                justify-content:space-between;">
                        <span style="font-weight:700; 
                                     font-size:15px; 
                                     color:#1A202C;">
                            Total:
                        </span>
                        <span id="totalEstimate" 
                              style="font-weight:700; 
                                     font-size:18px; 
                                     color:#1B6B3A;">
                            R0
                        </span>
                    </div>
                </div>

                <div style="margin-top:20px; 
                            background:#FEF3C7; 
                            border-radius:8px; 
                            padding:12px; 
                            font-size:12px; 
                            color:#92400E;">
                    ⚠️ Final amount may vary based on 
                    actual hours worked
                </div>
            </div>

            <!-- Provider card -->
            <div class="card" style="margin-top:16px;">
                <p style="font-size:12px; color:#A0AEC0; 
                          margin-bottom:8px;">
                    You are booking with:
                </p>
                <p style="font-weight:700; color:#1A202C; 
                          font-size:14px;">
                    <?php echo htmlspecialchars($service['provider_name']); ?>
                </p>
                <p style="font-size:12px; color:#4A5568; 
                          margin-top:4px;">
                    <?php echo htmlspecialchars($service['category_name']); ?> 
                    specialist
                </p>
            </div>
        </div>
    </div>
</div>

<script>
const hoursInput = document.getElementById('estimatedHours');
const totalDisplay = document.getElementById('totalEstimate');
const hoursDisplay = document.getElementById('hoursDisplay');
const rate = parseFloat(
    document.getElementById('serviceRate')
             .getAttribute('data-rate')
) || 0;

if (hoursInput) {
    hoursInput.addEventListener('input', function() {
        const hours = parseFloat(this.value) || 0;
        const total = hours * rate;
        totalDisplay.textContent = 'R' + total.toLocaleString();
        hoursDisplay.textContent = hours + ' hrs';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>