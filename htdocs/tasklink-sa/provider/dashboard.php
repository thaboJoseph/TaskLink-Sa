<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: /login.php');
    exit();
}

$provider_id   = $_SESSION['user_id'];
$provider_name = $_SESSION['full_name'] ?? 'Provider';

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    WHERE s.provider_id = ? AND b.status = 'pending'
");
$stmt->execute([$provider_id]);
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    WHERE s.provider_id = ? AND b.status = 'accepted'
");
$stmt->execute([$provider_id]);
$active_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    WHERE s.provider_id = ? AND b.status = 'completed'
");
$stmt->execute([$provider_id]);
$completed_count = $stmt->fetchColumn();

// ── FIXED: Exclude refunded payments from earnings ──────────────
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.estimated_hours * s.price), 0)
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    WHERE s.provider_id = ? 
    AND b.status = 'completed'
    AND (p.status IS NULL OR p.status != 'refunded')
");
$stmt->execute([$provider_id]);
$total_earnings = $stmt->fetchColumn() ?? 0;

// Count refunds against this provider
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM refund_requests rr
    JOIN bookings b ON rr.booking_id = b.booking_id
    JOIN services s ON b.service_id = s.service_id
    WHERE s.provider_id = ? AND rr.status = 'approved'
");
$stmt->execute([$provider_id]);
$refund_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT b.*, s.title as service_title, u.full_name as client_name
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.client_id = u.user_id
    WHERE s.provider_id = ?
    ORDER BY b.created_at DESC 
    LIMIT 4
");
$stmt->execute([$provider_id]);
$recent_bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard – TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; font-family:'Inter',sans-serif; margin:0; padding:0; }
        body { background-color:#F8FAFC; color:#1A202C; min-height:100vh; }

        .navbar { background:#FFFFFF; border-bottom:1px solid #E2E8F0; padding:16px 40px; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
        .nav-brand { font-size:20px; font-weight:700; color:#1B6B3A; text-decoration:none; }
        .nav-user { display:flex; align-items:center; gap:16px; }
        .user-pill { background:#E8F5EE; color:#1B6B3A; padding:6px 14px; border-radius:20px; font-size:13px; font-weight:600; }
        .logout-btn { color:#E53E3E; text-decoration:none; font-size:13px; font-weight:600; }

        .dashboard-container { max-width:1200px; margin:0 auto; padding:40px 24px; }
        .welcome-header { margin-bottom:32px; }
        .welcome-header h1 { font-size:26px; font-weight:700; color:#1A202C; }
        .welcome-header p { font-size:14px; color:#718096; margin-top:4px; }

        .stats-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:20px; margin-bottom:32px; }
        .stat-card { background:#FFFFFF; border:1px solid #E2E8F0; padding:20px; border-radius:12px; }
        .stat-label { font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; letter-spacing:0.5px; }
        .stat-value { font-size:26px; font-weight:700; color:#1A202C; margin-top:8px; }
        .stat-card.earnings  { border-left:4px solid #1B6B3A; }
        .stat-card.pending   { border-left:4px solid #ECC94B; }
        .stat-card.active    { border-left:4px solid #3182CE; }
        .stat-card.completed { border-left:4px solid #38A169; }
        .stat-card.refunds   { border-left:4px solid #EF4444; }

        .main-split { display:grid; grid-template-columns:2fr 1fr; gap:24px; }
        .panel { background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:24px; }
        .panel-title { font-size:16px; font-weight:700; color:#1A202C; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
        .panel-link { font-size:13px; color:#1B6B3A; text-decoration:none; font-weight:600; }

        .job-item { display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #EDF2F7; }
        .job-item:last-child { border-bottom:none; padding-bottom:0; }
        .job-meta h4 { font-size:14px; font-weight:600; color:#2D3748; }
        .job-meta p { font-size:12px; color:#718096; margin-top:2px; }

        .status-badge { padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase; }
        .status-pending   { background:#FEFCBF; color:#B7791F; }
        .status-accepted  { background:#EBF8FF; color:#2B6CB0; }
        .status-completed { background:#C6F6D5; color:#22543D; }
        .status-rejected  { background:#FED7D7; color:#9B2C2C; }

        .menu-list { display:flex; flex-direction:column; gap:12px; }
        .menu-link { display:flex; align-items:center; gap:12px; padding:14px; border:1px solid #E2E8F0; background:#F8FAFC; border-radius:8px; font-size:14px; color:#4A5568; font-weight:600; text-decoration:none; transition:all 0.2s; }
        .menu-link:hover { border-color:#1B6B3A; background:#E8F5EE; color:#1B6B3A; }
        .menu-link.danger { color:#E53E3E; }
        .menu-link.danger:hover { border-color:#E53E3E; background:#FFF5F5; color:#E53E3E; }
        .menu-icon { font-size:18px; }

        @media (max-width:992px) {
            .stats-grid { grid-template-columns:repeat(2,1fr); }
            .main-split { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/index.php" class="nav-brand">
        TaskLink SA — Provider Portal
    </a>
    <div class="nav-user">
        <span class="user-pill">💼 Provider Mode</span>
        <span style="font-size:14px; font-weight:500; color:#4A5568;">
            <?php echo htmlspecialchars($provider_name); ?>
        </span>
        <a href="/logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="dashboard-container">

    <div class="welcome-header">
        <h1>Welcome back, <?php echo htmlspecialchars($provider_name); ?>!</h1>
        <p>Here is an operational overview of your service performance parameters today.</p>
    </div>

    <!-- 5 stat cards now including refunds -->
    <div class="stats-grid">
        <div class="stat-card earnings">
            <div class="stat-label">Net Earnings</div>
            <div class="stat-value">
                R<?php echo number_format($total_earnings, 2); ?>
            </div>
        </div>
        <div class="stat-card pending">
            <div class="stat-label">New Requests</div>
            <div class="stat-value"><?php echo $pending_count; ?></div>
        </div>
        <div class="stat-card active">
            <div class="stat-label">Active Jobs</div>
            <div class="stat-value"><?php echo $active_count; ?></div>
        </div>
        <div class="stat-card completed">
            <div class="stat-label">Jobs Completed</div>
            <div class="stat-value"><?php echo $completed_count; ?></div>
        </div>
        <div class="stat-card refunds">
            <div class="stat-label">Refunds Issued</div>
            <div class="stat-value" style="color:<?php echo $refund_count > 0 ? '#EF4444' : '#1A202C'; ?>">
                <?php echo $refund_count; ?>
            </div>
        </div>
    </div>

    <?php if($refund_count > 0): ?>
    <div style="background:#FEF3C7; border:1px solid #F6C90E; border-radius:10px; 
                padding:14px 20px; margin-bottom:24px; display:flex; 
                align-items:center; gap:12px;">
        <span style="font-size:20px;">⚠️</span>
        <p style="font-size:13px; color:#92400E;">
            <strong>Note:</strong> You have <?php echo $refund_count; ?> 
            approved refund(s) that have been deducted from your earnings. 
            Your net earnings already reflect these deductions.
            <a href="/provider/earnings.php" 
               style="color:#1B6B3A; font-weight:600; margin-left:4px;">
                View Earnings →
            </a>
        </p>
    </div>
    <?php endif; ?>

    <div class="main-split">

        <div class="panel">
            <div class="panel-title">
                <span>Recent Activity Stream</span>
                <a href="/provider/provider-bookings.php" 
                   class="panel-link">
                    Manage All Jobs &rarr;
                </a>
            </div>

            <?php if(empty($recent_bookings)): ?>
                <div style="text-align:center; padding:40px 0; color:#A0AEC0;">
                    <p style="font-size:40px; margin-bottom:12px;">📬</p>
                    <p style="font-size:14px;">No recent booking activities tracked yet.</p>
                </div>
            <?php else: ?>
                <div class="job-list">
                    <?php foreach($recent_bookings as $booking): ?>
                        <div class="job-item">
                            <div class="job-meta">
                                <h4><?php echo htmlspecialchars($booking['service_title']); ?></h4>
                                <p>
                                    Client: <strong><?php echo htmlspecialchars($booking['client_name']); ?></strong>
                                    &bull; Date: <?php echo date('d M Y', strtotime($booking['booking_date'])); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                <?php echo strtoupper($booking['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-title">Provider Toolbar</div>
            <div class="menu-list">
                <a href="/provider/provider-bookings.php" class="menu-link">
                    <span class="menu-icon">📅</span>
                    <span>Manage Incoming Orders</span>
                </a>
                <a href="/messages.php" class="menu-link">
                    <span class="menu-icon">💬</span>
                    <span>Chat Inbox Messages</span>
                </a>
                <a href="/provider/earnings.php" class="menu-link">
                    <span class="menu-icon">📊</span>
                    <span>View Earnings Statements</span>
                </a>
                <a href="/provider/profile.php" class="menu-link">
                    <span class="menu-icon">👤</span>
                    <span>Edit Profile Settings</span>
                </a>
                <a href="/provider/report-user.php" class="menu-link danger">
                    <span class="menu-icon">🚨</span>
                    <span>Report a User</span>
                </a>
            </div>
        </div>

    </div>
</div>

</body>
</html>