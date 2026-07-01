<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: /login.php');
    exit();
}

$provider_id = $_SESSION['user_id'];

// ── Total earned from completed bookings ─────────────────────────
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.estimated_hours * s.price), 0)
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    WHERE b.provider_id = ? AND b.status = 'completed'
");
$stmt->execute([$provider_id]);
$gross_earnings = $stmt->fetchColumn() ?? 0;

// ── Total refunded ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount), 0)
    FROM refund_requests rr
    JOIN payments p ON rr.payment_id = p.payment_id
    JOIN bookings b ON rr.booking_id = b.booking_id
    JOIN services s ON b.service_id = s.service_id
    WHERE s.provider_id = ? AND rr.status = 'approved'
");
$stmt->execute([$provider_id]);
$total_refunded = $stmt->fetchColumn() ?? 0;

// ── Net earnings after refunds ────────────────────────────────────
$net_earnings = $gross_earnings - $total_refunded;

// ── Available vs pending ──────────────────────────────────────────
$pending_balance   = $net_earnings * 0.30;
$available_balance = $net_earnings * 0.70;

// ── Completed transactions ────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.booking_id, b.booking_date, b.estimated_hours, b.status,
           s.title as service_title, s.price as hourly_rate,
           u.full_name as client_name,
           p.status as payment_status,
           rr.status as refund_status,
           rr.reason as refund_reason
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.client_id = u.user_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    LEFT JOIN refund_requests rr ON rr.booking_id = b.booking_id
    WHERE b.provider_id = ? AND b.status = 'completed'
    ORDER BY b.booking_date DESC
");
$stmt->execute([$provider_id]);
$transactions = $stmt->fetchAll();

// ── Pending refund claims against this provider ───────────────────
$stmt = $pdo->prepare("
    SELECT rr.*, 
           u.full_name as client_name,
           s.title as service_title,
           p.amount,
           b.booking_date
    FROM refund_requests rr
    JOIN bookings b ON rr.booking_id = b.booking_id
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON rr.client_id = u.user_id
    JOIN payments p ON rr.payment_id = p.payment_id
    WHERE b.provider_id = ? AND rr.status = 'pending'
");
$stmt->execute([$provider_id]);
$pending_refunds = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings – TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing:border-box; font-family:'Inter',sans-serif; margin:0; padding:0; }
        body { background:#F8FAFC; color:#1A202C; min-height:100vh; }
        .navbar { background:#FFFFFF; border-bottom:1px solid #E2E8F0; padding:16px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:20px; font-weight:700; color:#1B6B3A; text-decoration:none; }
        .back-link { color:#1B6B3A; text-decoration:none; font-size:14px; font-weight:600; }
        .container { max-width:1100px; margin:0 auto; padding:40px 24px; }
        .page-header { margin-bottom:32px; display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; }
        .page-header h1 { font-size:26px; font-weight:700; color:#1A202C; }
        .page-header p { font-size:14px; color:#718096; margin-top:4px; }
        .btn-payout { background:#1B6B3A; color:white; border:none; padding:12px 24px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-payout:hover { background:#134D2A; }
        .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:32px; }
        .summary-card { background:white; border:1px solid #E2E8F0; border-radius:12px; padding:24px; }
        .summary-card.highlight { background:#E8F5EE; border-color:#C6E8D5; }
        .s-label { font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px; }
        .s-value { font-size:28px; font-weight:700; color:#1A202C; }
        .summary-card.highlight .s-value { color:#1B6B3A; }
        .summary-card.refund .s-value { color:#EF4444; }
        .panel { background:white; border:1px solid #E2E8F0; border-radius:12px; padding:24px; margin-bottom:24px; }
        .panel-title { font-size:16px; font-weight:700; color:#1A202C; margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; }
        th { font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left; }
        td { padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0; }
        tr:last-child td { border-bottom:none; }
        .badge { padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; display:inline-block; }
        .badge-paid { background:#C6F6D5; color:#22543D; }
        .badge-refunded { background:#FED7D7; color:#9B2C2C; }
        .badge-pending { background:#FEF3C7; color:#B7791F; }
        .amount-positive { font-weight:700; color:#1B6B3A; }
        .amount-negative { font-weight:700; color:#EF4444; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/index.php" class="nav-brand">TaskLink SA</a>
    <a href="/provider/dashboard.php" class="back-link">← Back to Dashboard</a>
</nav>

<div class="container">

    <div class="page-header">
        <div>
            <h1>Earnings & Financial Statements</h1>
            <p>Track your payouts, refunds, and transaction history.</p>
        </div>
        <button class="btn-payout" 
                onclick="alert('Payout request submitted! Funds will reflect within 2–3 business days.')">
            Request Payout to Bank
        </button>
    </div>

    <!-- Pending refund warning -->
    <?php if(!empty($pending_refunds)): ?>
    <div style="background:#FEF3C7; border:1px solid #F6C90E; border-radius:10px; 
                padding:16px 20px; margin-bottom:24px;">
        <p style="font-size:13px; color:#92400E; font-weight:600; margin-bottom:8px;">
            ⚠️ You have <?php echo count($pending_refunds); ?> pending refund claim(s)
        </p>
        <?php foreach($pending_refunds as $pr): ?>
        <div style="font-size:12px; color:#92400E; padding:6px 0; 
                    border-top:1px solid rgba(0,0,0,0.06);">
            <strong><?php echo htmlspecialchars($pr['service_title']); ?></strong>
            — R<?php echo number_format($pr['amount'],2); ?>
            — Reason: <?php echo htmlspecialchars($pr['reason']); ?>
            — Submitted by <?php echo htmlspecialchars($pr['client_name']); ?>
            on <?php echo date('d M Y', strtotime($pr['created_at'])); ?>
            <span style="margin-left:8px; background:#FEE2E2; color:#991B1B; 
                         padding:2px 8px; border-radius:10px; font-size:10px; 
                         font-weight:700;">
                UNDER REVIEW
            </span>
        </div>
        <?php endforeach; ?>
        <p style="font-size:11px; color:#92400E; margin-top:8px;">
            If approved by admin, these amounts will be deducted from your earnings.
        </p>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card highlight">
            <div class="s-label">Available Balance</div>
            <div class="s-value">R<?php echo number_format($available_balance, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="s-label">Pending Clearance</div>
            <div class="s-value">R<?php echo number_format($pending_balance, 2); ?></div>
        </div>
        <div class="summary-card">
            <div class="s-label">Gross Earnings</div>
            <div class="s-value">R<?php echo number_format($gross_earnings, 2); ?></div>
        </div>
        <div class="summary-card refund">
            <div class="s-label">Total Refunded</div>
            <div class="s-value">R<?php echo number_format($total_refunded, 2); ?></div>
        </div>
    </div>

    <!-- Net earnings banner -->
    <div style="background:#1B6B3A; border-radius:12px; padding:20px 28px; 
                margin-bottom:32px; display:flex; justify-content:space-between; 
                align-items:center; flex-wrap:wrap; gap:12px;">
        <div>
            <p style="font-size:12px; color:rgba(255,255,255,0.7); 
                      text-transform:uppercase; font-weight:600; margin-bottom:4px;">
                Net Earnings (After Refunds)
            </p>
            <p style="font-size:32px; font-weight:700; color:white;">
                R<?php echo number_format($net_earnings, 2); ?>
            </p>
        </div>
        <?php if($total_refunded > 0): ?>
        <div style="background:rgba(255,255,255,0.1); border-radius:8px; 
                    padding:12px 16px; font-size:13px; color:rgba(255,255,255,0.85);">
            R<?php echo number_format($gross_earnings,2); ?> gross
            − R<?php echo number_format($total_refunded,2); ?> refunded
            = <strong>R<?php echo number_format($net_earnings,2); ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <!-- Transaction History -->
    <div class="panel">
        <div class="panel-title">Transaction History</div>

        <?php if(empty($transactions)): ?>
            <div style="text-align:center; padding:48px; color:#A0AEC0;">
                <p style="font-size:44px; margin-bottom:12px;">💳</p>
                <p style="font-size:14px;">No completed transactions yet.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Hours</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $tx):
                            $amount  = $tx['estimated_hours'] * $tx['hourly_rate'];
                            $refunded = $tx['refund_status'] === 'approved';
                        ?>
                        <tr style="<?php echo $refunded ? 'opacity:0.6;' : ''; ?>">
                            <td><?php echo date('d M Y', strtotime($tx['booking_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($tx['client_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($tx['service_title']); ?></td>
                            <td><?php echo $tx['estimated_hours']; ?> hrs</td>
                            <td>
                                <?php if($refunded): ?>
                                    <span class="badge badge-refunded">Refunded</span>
                                    <?php if($tx['refund_reason']): ?>
                                        <span style="display:block; font-size:10px; 
                                                     color:#718096; margin-top:2px;">
                                            <?php echo htmlspecialchars($tx['refund_reason']); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php elseif($tx['refund_status'] === 'pending'): ?>
                                    <span class="badge badge-pending">Refund Pending</span>
                                <?php else: ?>
                                    <span class="badge badge-paid">Paid</span>
                                <?php endif; ?>
                            </td>
                            <td class="<?php echo $refunded ? 'amount-negative' : 'amount-positive'; ?>">
                                <?php echo $refunded ? '-' : ''; ?>
                                R<?php echo number_format($amount, 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>