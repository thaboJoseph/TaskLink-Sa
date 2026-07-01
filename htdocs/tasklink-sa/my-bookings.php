<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || 
    $_SESSION['role'] !== 'client') {
    header('Location: /login.php');
    exit();
}

$filter = $_GET['filter'] ?? 'all';

$where  = "b.client_id = ?";
$params = [$_SESSION['user_id']];

if ($filter !== 'all') {
    $where  .= " AND b.status = ?";
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT b.*,
           s.title as service_title,
           s.price as service_price,
           s.price_type,
           u.full_name as provider_name,
           c.category_name,
           (SELECT COUNT(*) FROM reviews r 
            WHERE r.booking_id = b.booking_id) 
            as has_review,
           (SELECT COUNT(*) FROM payments p 
            WHERE p.booking_id = b.booking_id AND p.status = 'completed') 
            as has_payment
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.provider_id = u.user_id
    JOIN categories c ON s.category_id = c.category_id
    WHERE $where
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:900px; margin:0 auto; 
            padding:40px 24px;">

    <div style="display:flex; 
                justify-content:space-between;
                align-items:center; 
                margin-bottom:24px;
                flex-wrap:wrap; gap:12px;">
        <h1 style="font-size:24px; font-weight:700; 
                   color:#1A202C;">
            My Bookings
        </h1>

        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Report Button -->
            <a href="/report-provider.php" 
               class="btn-secondary"
               style="padding:10px 18px; font-size:14px; background:#FEE2E2; color:#991B1B;">
                🚨 Report a Provider
            </a>

            <!-- Book a Service Button -->
            <a href="/browse.php" 
               class="btn-primary"
               style="padding:10px 20px; font-size:14px;">
                + Book a Service
            </a>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div class="alert-success" 
             style="margin-bottom:24px;">
            🎉 Booking submitted successfully! 
            The provider will respond shortly.
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div style="display:flex; gap:8px; 
                margin-bottom:24px; flex-wrap:wrap;">
        <?php 
        $tabs = [
            'all'       => 'All',
            'pending'   => 'Pending',
            'accepted'  => 'Accepted',
            'completed' => 'Completed',
            'rejected'  => 'Rejected',
        ];
        foreach($tabs as $key => $label): ?>
            <a href="?filter=<?php echo $key; ?>"
               style="padding:8px 18px; 
                      border-radius:20px; 
                      font-size:13px; 
                      font-weight:600; 
                      text-decoration:none;
                      <?php echo $filter === $key ? 
                      'background:#1B6B3A;color:white;' : 
                      'background:#F0F0F0;color:#4A5568;'; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Bookings List -->
    <?php if(empty($bookings)): ?>
        <div class="card" 
             style="text-align:center; padding:60px 20px;">
            <p style="font-size:48px;">📅</p>
            <p style="font-size:18px; font-weight:600; 
                      color:#1A202C; margin-top:16px;">
                No bookings yet
            </p>
            <p style="color:#4A5568; margin-top:8px; 
                      font-size:14px;">
                <?php echo $filter !== 'all' ? 
                'No ' . $filter . ' bookings found.' : 
                'You have not made any bookings yet.'; ?>
            </p>
            <a href="/browse.php"
               class="btn-primary"
               style="display:inline-block; 
                      margin-top:24px; 
                      padding:12px 32px;">
                Browse Services
            </a>
        </div>

    <?php else: ?>
        <?php foreach($bookings as $booking): 
            $total = $booking['estimated_hours'] * 
                     $booking['service_price'];
        ?>
        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex; 
                        justify-content:space-between;
                        align-items:flex-start; 
                        flex-wrap:wrap; gap:12px;">

                <!-- Left info -->
                <div style="flex:1;">
                    <div style="display:flex; 
                                align-items:center; 
                                gap:10px; 
                                margin-bottom:8px;
                                flex-wrap:wrap;">
                        <h3 style="font-size:16px; 
                                   font-weight:700; 
                                   color:#1A202C;">
                            <?php echo htmlspecialchars($booking['service_title']); ?>
                        </h3>
                        <span class="badge">
                            <?php echo htmlspecialchars($booking['category_name']); ?>
                        </span>
                    </div>
                    <p style="font-size:13px; 
                              color:#4A5568; 
                              margin-bottom:4px;">
                        👤 Provider: 
                        <strong>
                            <?php echo htmlspecialchars($booking['provider_name']); ?>
                        </strong>
                    </p>
                    <p style="font-size:13px; 
                              color:#4A5568; 
                              margin-bottom:4px;">
                        📅 Date: 
                        <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                    </p>
                    <p style="font-size:13px; 
                              color:#4A5568; 
                              margin-bottom:4px;">
                        📍 <?php echo htmlspecialchars($booking['address']); ?>
                    </p>
                    <p style="font-size:13px; 
                              color:#4A5568; 
                              margin-bottom:4px;">
                        ⏱️ <?php echo $booking['estimated_hours']; ?> hrs
                    </p>
                    <p style="font-size:14px; 
                              font-weight:700; 
                              color:#1B6B3A; 
                              margin-top:8px;">
                        💰 Estimate: R<?php echo number_format($total, 2); ?>
                    </p>
                </div>

                <!-- Right — status + actions -->
                <div style="display:flex; 
                            flex-direction:column; 
                            align-items:flex-end; 
                            gap:10px;">

                    <span class="status-<?php echo $booking['status']; ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </span>

                    <?php if($booking['status'] === 'accepted'): ?>
                        <a href="./payment.php?id=<?php echo $booking['booking_id']; ?>"
                           class="btn-primary"
                           style="padding:10px 20px; 
                                  font-size:13px;">
                            💳 Pay Now
                        </a>

                    <?php elseif($booking['status'] === 'completed' && 
                                 !$booking['has_review']): ?>
                        <a href="/leave-review.php?id=<?php echo $booking['booking_id']; ?>"
                           class="btn-secondary"
                           style="padding:10px 20px; 
                                  font-size:13px;">
                            ⭐ Leave Review
                        </a>
                        <a href="/request-refund.php"
                           style="font-size:12px; color:#EF4444; font-weight:600; text-decoration:none;">
                            Request Refund
                        </a>

                    <?php elseif($booking['status'] === 'completed' && 
                                 $booking['has_review']): ?>
                        <span style="font-size:12px; 
                                     color:#A0AEC0;">
                            ✅ Review submitted
                        </span>
                        <a href="/request-refund.php"
                           style="font-size:12px; color:#EF4444; font-weight:600; text-decoration:none;">
                            Request Refund
                        </a>

                    <?php elseif($booking['status'] === 'pending'): ?>
                        <span style="font-size:12px; 
                                     color:#A0AEC0;">
                            ⏳ Awaiting provider response
                        </span>

                    <?php elseif($booking['status'] === 'rejected'): ?>
                        <a href="/browse.php"
                           class="btn-secondary"
                           style="padding:10px 20px; 
                                  font-size:13px;">
                            🔍 Find Another
                        </a>
                    <?php endif; ?>

                    <a href="/messages.php?booking=<?php echo $booking['booking_id']; ?>"
                       style="font-size:12px; 
                              color:#1B6B3A; 
                              font-weight:600;
                              text-decoration:none;">
                        💬 Message Provider
                    </a>
                </div>
            </div>

            <?php if(!empty($booking['note'])): ?>
                <div style="margin-top:12px; 
                            padding:10px 14px; 
                            background:#F7F8FA; 
                            border-radius:8px; 
                            font-size:13px; 
                            color:#4A5568;">
                    📝 <?php echo htmlspecialchars($booking['note']); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>