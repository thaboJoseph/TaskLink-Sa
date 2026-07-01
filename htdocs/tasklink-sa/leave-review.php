<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: /login.php');
    exit();
}

$booking_id = (int)($_GET['id'] ?? 0);

if (!$booking_id) {
    header('Location: /my-bookings.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT b.*, s.title as service_title, u.full_name as provider_name, u.user_id as provider_id
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.provider_id = u.user_id
    WHERE b.booking_id = ? AND b.client_id = ?
");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: /my-bookings.php');
    exit();
}

$stmt = $pdo->prepare("SELECT review_id FROM reviews WHERE booking_id = ?");
$stmt->execute([$booking_id]);
if ($stmt->fetch()) {
    header('Location: /my-bookings.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5 stars.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO reviews (booking_id, client_id, provider_id, rating, comment)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $booking_id,
            $_SESSION['user_id'],
            $booking['provider_id'],
            $rating,
            $comment
        ]);

        $stmt = $pdo->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as total_rev 
            FROM reviews 
            WHERE provider_id = ?
        ");
        $stmt->execute([$booking['provider_id']]);
        $stats = $stmt->fetch();

        $stmt = $pdo->prepare("
            UPDATE provider_profiles 
            SET rating = ?, total_reviews = ? 
            WHERE provider_id = ?
        ");
        $stmt->execute([
            $stats['avg_rating'],
            $stats['total_rev'],
            $booking['provider_id']
        ]);

        header('Location: /my-bookings.php');
        exit();
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:600px; margin:0 auto; padding:40px 24px;">
    
    <a href="/my-bookings.php" 
       style="color:#1B6B3A; font-size:14px; font-weight:600; text-decoration:none; display:inline-block; margin-bottom:24px;">
        ← Back to My Bookings
    </a>

    <div class="card" style="background:white; padding:32px; border-radius:16px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
        
        <div style="text-align:center; margin-bottom:28px;">
            <p style="font-size:40px; margin:0 0 12px 0;">⭐</p>
            <h1 style="font-size:24px; font-weight:700; color:#1A202C; margin:0 0 6px 0;">Leave a Review</h1>
            <p style="color:#4A5568; font-size:14px; margin:0;">
                How was your experience with <strong><?php echo htmlspecialchars($booking['provider_name']); ?></strong> for 
                <em><?php echo htmlspecialchars($booking['service_title']); ?></em>?
            </p>
            <p style="margin-top:12px; font-size:13px;">
                <a href="/report-provider.php?provider_id=<?php echo $booking['provider_id']; ?>" 
                   style="color:#EF4444; font-weight:600; text-decoration:none;">
                    🚨 Report this provider
                </a>
                <span style="color:#CBD5E0; margin:0 8px;">|</span>
                <a href="/request-refund.php" 
                   style="color:#EF4444; font-weight:600; text-decoration:none;">
                    Request Refund
                </a>
            </p>
        </div>

        <?php if(!empty($error)): ?>
            <div style="background:#FED7D7; color:#C53030; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:20px; font-weight:500;">
                ⚠️ <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['paid'])): ?>
            <div style="background:#E8F5EE; color:#1B6B3A; padding:12px 16px; border-radius:8px; font-size:14px; margin-bottom:24px; font-weight:500; text-align:center;">
                💳 Payment Successful! Your booking is complete. Take a second to rate the service.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            
            <div class="form-group" style="margin-bottom:24px;">
                <label style="display:block; font-size:14px; font-weight:600; color:#4A5568; margin-bottom:10px;">
                    Your Rating
                </label>
                <div style="display:flex; gap:10px;">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <label style="cursor:pointer; text-align:center; flex:1;">
                            <input type="radio" name="rating" value="<?php echo $i; ?>" style="display:none;" class="rating-radio" required>
                            <div class="rating-box" style="padding:14px 0; border:2px solid #E2E8F0; border-radius:10px; font-size:16px; font-weight:700; color:#4A5568; transition: all 0.2s ease;">
                                <?php echo $i; ?> ★
                            </div>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:24px;">
                <label for="comment" style="display:block; font-size:14px; font-weight:600; color:#4A5568; margin-bottom:8px;">
                    Review Comments (Optional)
                </label>
                <textarea id="comment" name="comment" rows="4" placeholder="Share your experience working with this provider..."
                          style="width:100%; padding:12px 16px; border:2px solid #E2E8F0; border-radius:10px; font-size:14px; color:#1A202C; resize:vertical; outline:none; font-family:inherit; box-sizing:border-box; transition:border-color 0.2s;"></textarea>
            </div>

            <button type="submit" class="btn-primary" 
                    style="width:100%; padding:14px; font-size:15px; font-weight:600; border-radius:10px; border:none; cursor:pointer;">
                Submit Review 🎉
            </button>

        </form>
    </div>
</div>

<style>
.rating-radio:checked + .rating-box {
    border-color: #1B6B3A;
    background: #E8F5EE;
    color: #1B6B3A;
    box-shadow: 0 0 0 1px #1B6B3A;
}
.rating-box:hover {
    border-color: #1B6B3A;
    color: #1B6B3A;
}
textarea:focus {
    border-color: #1B6B3A !important;
}
</style>

<?php require_once 'includes/footer.php'; ?>