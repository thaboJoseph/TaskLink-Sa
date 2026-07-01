<?php
$page_title = 'Payments';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// Process refund
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_POST['action']) && 
    $_POST['action'] === 'process_refund') {
    $refund_id     = (int)$_POST['refund_id'];
    $flag_provider = isset($_POST['flag_provider']) ? 1 : 0;

    $stmt = $pdo->prepare("
        SELECT rr.*, p.payment_id, p.booking_id
        FROM refund_requests rr
        JOIN payments p ON rr.payment_id = p.payment_id
        WHERE rr.refund_id = ?
    ");
    $stmt->execute([$refund_id]);
    $refund = $stmt->fetch();

    if ($refund) {
        $pdo->prepare("UPDATE payments SET status='refunded' WHERE payment_id=?")->execute([$refund['payment_id']]);
        $pdo->prepare("UPDATE refund_requests SET status='approved', flag_provider=? WHERE refund_id=?")->execute([$flag_provider, $refund_id]);

        if ($flag_provider) {
            $stmt = $pdo->prepare("SELECT provider_id FROM bookings WHERE booking_id=?");
            $stmt->execute([$refund['booking_id']]);
            $bk = $stmt->fetch();
            if ($bk) {
                $pdo->prepare("UPDATE provider_profiles SET rating=0 WHERE provider_id=?")->execute([$bk['provider_id']]);
            }
        }
        $success = 'Refund processed successfully. Payment marked as refunded.';
    }
}

// Reject refund
if (isset($_GET['reject_refund']) && is_numeric($_GET['reject_refund'])) {
    $pdo->prepare("UPDATE refund_requests SET status='rejected' WHERE refund_id=?")->execute([(int)$_GET['reject_refund']]);
    $success = 'Refund request rejected.';
}

// ── CORRECTED FINANCIAL TRACKING (10% commission model) ────────────────────────
// Gross Revenue = Total money clients paid (completed + refunded)
$total_revenue = $pdo->query("
    SELECT COALESCE(SUM(amount),0) 
    FROM payments 
    WHERE status IN ('completed', 'refunded')
")->fetchColumn() ?? 0;

// Total money returned to clients on approved refunds
$total_refunded = $pdo->query("
    SELECT COALESCE(SUM(amount),0) 
    FROM payments 
    WHERE status='refunded'
")->fetchColumn() ?? 0;

// Net Revenue = What TaskLink actually keeps (10% commission on successful transactions only)
$net_revenue = $pdo->query("
    SELECT COALESCE(SUM(commission),0) 
    FROM payments 
    WHERE status='completed'
")->fetchColumn() ?? 0;

// Other stats
$total_commission = $pdo->query("SELECT COALESCE(SUM(commission),0) FROM payments WHERE status='completed'")->fetchColumn();
$pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status='pending'")->fetchColumn();
$pending_refunds  = $pdo->query("SELECT COUNT(*) FROM refund_requests WHERE status='pending'")->fetchColumn();

$search = trim($_GET['search'] ?? '');
$where  = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[]  = "(u1.full_name LIKE ? OR u2.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT p.*,
           b.estimated_hours, b.booking_date, b.booking_id,
           s.title as service_title,
           u1.full_name as client_name,
           u2.full_name as provider_name,
           (SELECT COUNT(*) FROM refund_requests rr 
            WHERE rr.payment_id = p.payment_id 
            AND rr.status='pending') as has_pending_refund,
           (SELECT reason FROM refund_requests rr2
            WHERE rr2.payment_id = p.payment_id
            AND rr2.status='approved' LIMIT 1) as refund_reason
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN services s ON b.service_id = s.service_id
    JOIN users u1 ON b.client_id = u1.user_id
    JOIN users u2 ON b.provider_id = u2.user_id
    WHERE $whereStr
    ORDER BY p.payment_date DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Pending refund requests
$refund_requests = $pdo->query("
    SELECT rr.*,
           u1.full_name as client_name,
           u2.full_name as provider_name,
           s.title as service_title,
           b.booking_date, b.address, b.estimated_hours,
           p.amount, p.commission
    FROM refund_requests rr
    JOIN users u1 ON rr.client_id = u1.user_id
    JOIN payments p ON rr.payment_id = p.payment_id
    JOIN bookings b ON rr.booking_id = b.booking_id
    JOIN services s ON b.service_id = s.service_id
    JOIN users u2 ON b.provider_id = u2.user_id
    WHERE rr.status = 'pending'
    ORDER BY rr.created_at DESC
")->fetchAll();

// Transaction History
$stmt = $pdo->prepare("
    SELECT b.booking_id, b.booking_date, b.estimated_hours, b.status,
           s.title as service_title, s.price as hourly_rate,
           u1.full_name as client_name,
           u2.full_name as provider_name,
           p.status as payment_status,
           rr.status as refund_status,
           rr.reason as refund_reason
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u1 ON b.client_id = u1.user_id
    JOIN users u2 ON b.provider_id = u2.user_id
    LEFT JOIN payments p ON p.booking_id = b.booking_id
    LEFT JOIN refund_requests rr ON rr.booking_id = b.booking_id
    WHERE b.status = 'completed'
    ORDER BY b.booking_date DESC
");
$stmt->execute();
$transactions = $stmt->fetchAll();
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(6,1fr); 
            gap:16px; margin-bottom:32px;">
    <div class="stat-card" style="grid-column:span 2;">
        <div class="stat-label">Gross Revenue</div>
        <div class="stat-value" style="color:#1B6B3A; font-size:22px;">
            R<?php echo number_format($total_revenue, 2); ?>
        </div>
        <div class="stat-accent" style="background:#1B6B3A;"></div>
    </div>
    <div class="stat-card" style="grid-column:span 2;">
        <div class="stat-label">Net Revenue (After Refunds)</div>
        <div class="stat-value" style="color:#1B6B3A; font-size:22px;">
            R<?php echo number_format($net_revenue, 2); ?>
        </div>
        <div class="stat-accent" style="background:#1B6B3A;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Commission</div>
        <div class="stat-value" style="color:#F5A623; font-size:20px;">
            R<?php echo number_format($total_commission, 0); ?>
        </div>
        <div class="stat-accent" style="background:#F5A623;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Refunded</div>
        <div class="stat-value" style="color:#EF4444; font-size:20px;">
            R<?php echo number_format($total_refunded, 0); ?>
        </div>
        <div class="stat-accent" style="background:#EF4444;"></div>
    </div>
</div>

<div style="display:grid; grid-template-columns:repeat(2,1fr); 
            gap:16px; margin-bottom:32px;">
    <div class="stat-card">
        <div class="stat-label">Pending Payments</div>
        <div class="stat-value"><?php echo $pending_payments; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Refund Requests</div>
        <div class="stat-value" style="color:<?php echo $pending_refunds>0?'#EF4444':'#1A202C';?>">
            <?php echo $pending_refunds; ?>
        </div>
    </div>
</div>

<!-- Pending Refund Requests -->
<?php if(!empty($refund_requests)): ?>
<div class="admin-table-wrap" style="margin-bottom:32px; border:2px solid #FEE2E2;">
    <div class="admin-table-header" style="background:#FFF5F5;">
        <h2 style="color:#991B1B;">
            🔄 Pending Refund Requests (<?php echo count($refund_requests); ?>)
        </h2>
    </div>
    <?php foreach($refund_requests as $rr): ?>
    <div style="padding:20px 24px; border-bottom:1px solid #FEE2E2;">
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; 
                    gap:20px; margin-bottom:16px;">
            <div>
                <p style="font-size:11px; font-weight:600; color:#718096; 
                          text-transform:uppercase; margin-bottom:4px;">Client</p>
                <p style="font-size:14px; font-weight:600; color:#1A202C;">
                    <?php echo htmlspecialchars($rr['client_name']); ?>
                </p>
                <p style="font-size:12px; color:#718096; margin-top:2px;">
                    Submitted: <?php echo date('d M Y', strtotime($rr['created_at'])); ?>
                </p>
            </div>
            <div>
                <p style="font-size:11px; font-weight:600; color:#718096; 
                          text-transform:uppercase; margin-bottom:4px;">Booking</p>
                <p style="font-size:13px; font-weight:500;">
                    <?php echo htmlspecialchars($rr['service_title']); ?>
                </p>
                <p style="font-size:12px; color:#718096;">
                    Provider: <?php echo htmlspecialchars($rr['provider_name']); ?>
                </p>
                <p style="font-size:12px; color:#718096;">
                    📅 <?php echo date('d M Y', strtotime($rr['booking_date'])); ?>
                </p>
                <p style="font-size:12px; color:#718096;">
                    📍 <?php echo htmlspecialchars($rr['address']); ?>
                </p>
                <p style="font-size:12px; color:#718096;">
                    ⏱️ <?php echo $rr['estimated_hours']; ?> hrs
                </p>
            </div>
            <div>
                <p style="font-size:11px; font-weight:600; color:#718096; 
                          text-transform:uppercase; margin-bottom:4px;">Refund Amount</p>
                <p style="font-size:24px; font-weight:700; color:#EF4444;">
                    R<?php echo number_format($rr['amount'], 2); ?>
                </p>
                <p style="font-size:12px; color:#718096;">
                    Commission deducted: R<?php echo number_format($rr['commission'], 2); ?>
                </p>
                <p style="font-size:12px; color:#718096; margin-top:4px;">
                    Provider will lose: 
                    <strong style="color:#EF4444;">
                        R<?php echo number_format($rr['amount'] - $rr['commission'], 2); ?>
                    </strong>
                </p>
            </div>
        </div>

        <?php if(!empty($rr['evidence_image'])): ?>
        <div style="margin-bottom:16px;">
            <p style="font-size:12px; font-weight:600; color:#718096; margin-bottom:8px;">
                Evidence Submitted:
            </p>
            <img src="/<?php echo htmlspecialchars($rr['evidence_image']); ?>"
                 alt="Refund evidence"
                 style="max-width:300px; max-height:200px; border-radius:8px; 
                        border:1px solid #E2E8F0; object-fit:cover; cursor:pointer;"
                 onclick="window.open(this.src, '_blank')">
        </div>
        <?php endif; ?>

        <div style="background:#FFF5F5; border-radius:8px; 
                    padding:12px 16px; margin-bottom:16px;">
            <p style="font-size:12px; font-weight:600; color:#991B1B; margin-bottom:4px;">
                Reason: <?php echo htmlspecialchars($rr['reason']); ?>
            </p>
            <p style="font-size:13px; color:#4A5568;">
                <?php echo htmlspecialchars($rr['details']); ?>
            </p>
        </div>

        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <form method="POST" action="" 
                  style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="action" value="process_refund">
                <input type="hidden" name="refund_id" value="<?php echo $rr['refund_id']; ?>">
                <label style="display:flex; align-items:center; gap:6px; 
                              font-size:13px; color:#4A5568; cursor:pointer;">
                    <input type="checkbox" name="flag_provider" value="1"
                           style="accent-color:#EF4444;">
                    Flag & reset provider rating
                </label>
                <button type="submit" class="btn-primary"
                        style="background:#1B6B3A; padding:8px 20px;"
                        onclick="return confirm('Approve refund of R<?php echo number_format($rr['amount'],2); ?>? This will mark the payment as refunded.')">
                    ✓ Approve Refund
                </button>
            </form>
            <a href="?reject_refund=<?php echo $rr['refund_id']; ?>"
               class="btn-sm btn-danger"
               onclick="return confirm('Reject this refund request?')">
                ✕ Reject
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Search -->
<form method="GET" action="" style="display:flex; gap:12px; margin-bottom:24px;">
    <input type="text" name="search" class="search-bar"
           placeholder="Search by client or provider name..."
           value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="btn-primary">Search</button>
    <?php if($search): ?>
        <a href="/admin/payments.php" class="btn-sm btn-green">Clear</a>
    <?php endif; ?>
</form>

<!-- Payments Table -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>All Payments</h2>
        <span style="font-size:13px; color:#718096;">
            <?php echo count($payments); ?> payment(s)
        </span>
    </div>
    <?php if(empty($payments)): ?>
        <div class="empty-state"><p>💳</p><p>No payments yet</p></div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Provider</th>
                <th>Service</th>
                <th>Amount</th>
                <th>Commission</th>
                <th>Provider Earned</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($payments as $p): 
                $provider_earned = $p['amount'] - $p['commission'];
                $is_refunded = $p['status'] === 'refunded';
            ?>
            <tr style="<?php echo $is_refunded ? 'opacity:0.7; background:#FFF5F5;' : ''; ?>">
                <td style="color:#A0AEC0; font-size:12px;">
                    #<?php echo str_pad($p['payment_id'],6,'0',STR_PAD_LEFT); ?>
                </td>
                <td style="font-weight:600;">
                    <?php echo htmlspecialchars($p['client_name']); ?>
                </td>
                <td style="color:#718096;">
                    <?php echo htmlspecialchars($p['provider_name']); ?>
                </td>
                <td style="font-size:12px; color:#718096;">
                    <?php echo htmlspecialchars($p['service_title']); ?>
                </td>
                <td style="font-weight:600; <?php echo $is_refunded ? 'text-decoration:line-through; color:#A0AEC0;' : ''; ?>">
                    R<?php echo number_format($p['amount'], 2); ?>
                </td>
                <td style="color:#F5A623; font-weight:600; <?php echo $is_refunded ? 'text-decoration:line-through; color:#A0AEC0;' : ''; ?>">
                    R<?php echo number_format($p['commission'], 2); ?>
                </td>
                <td style="font-weight:600; <?php echo $is_refunded ? 'color:#EF4444;' : 'color:#1B6B3A;'; ?>">
                    <?php echo $is_refunded ? '−' : ''; ?>
                    R<?php echo number_format($provider_earned, 2); ?>
                </td>
                <td style="font-size:12px; color:#718096;">
                    <?php echo date('d M Y', strtotime($p['payment_date'])); ?>
                </td>
                <td>
                    <?php
                    $sc = match($p['status']) {
                        'completed' => 'status-completed',
                        'refunded'  => 'status-rejected',
                        default     => 'status-pending'
                    };
                    ?>
                    <span class="status-badge <?php echo $sc; ?>">
                        <?php echo ucfirst($p['status']); ?>
                    </span>
                    <?php if($p['has_pending_refund']): ?>
                        <span style="display:block; font-size:10px; 
                                     color:#EF4444; margin-top:3px;">
                            ⏳ Refund pending
                        </span>
                    <?php endif; ?>
                    <?php if($is_refunded && $p['refund_reason']): ?>
                        <span style="display:block; font-size:10px; 
                                     color:#718096; margin-top:3px;">
                            <?php echo htmlspecialchars($p['refund_reason']); ?>
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Transaction History -->
<div class="panel" style="margin-top:32px; background:white; border:1px solid #E2E8F0; border-radius:12px; padding:24px;">
    <div class="panel-title" style="font-size:16px; font-weight:700; color:#1A202C; margin-bottom:20px;">Transaction History</div>

    <?php if(empty($transactions)): ?>
        <div style="text-align:center; padding:48px; color:#A0AEC0;">
            <p style="font-size:44px; margin-bottom:12px;">💳</p>
            <p style="font-size:14px;">No completed transactions yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Date</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Client</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Provider</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Service</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Hours</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Status</th>
                        <th style="font-size:11px; font-weight:600; color:#718096; text-transform:uppercase; padding:12px 16px; border-bottom:2px solid #E2E8F0; background:#F8FAFC; text-align:left;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transactions as $tx):
                        $amount  = $tx['estimated_hours'] * $tx['hourly_rate'];
                        $refunded = $tx['refund_status'] === 'approved';
                    ?>
                    <tr style="<?php echo $refunded ? 'opacity:0.6;' : ''; ?>">
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;"><?php echo date('d M Y', strtotime($tx['booking_date'])); ?></td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;"><strong><?php echo htmlspecialchars($tx['client_name']); ?></strong></td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;"><?php echo htmlspecialchars($tx['provider_name']); ?></td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;"><?php echo htmlspecialchars($tx['service_title']); ?></td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;"><?php echo $tx['estimated_hours']; ?> hrs</td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0;">
                            <?php if($refunded): ?>
                                <span class="badge badge-refunded" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; display:inline-block; background:#FED7D7; color:#9B2C2C;">Refunded</span>
                                <?php if($tx['refund_reason']): ?>
                                    <span style="display:block; font-size:10px; color:#718096; margin-top:2px;">
                                        <?php echo htmlspecialchars($tx['refund_reason']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php elseif($tx['refund_status'] === 'pending'): ?>
                                <span class="badge badge-pending" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; display:inline-block; background:#FEF3C7; color:#B7791F;">Refund Pending</span>
                            <?php else: ?>
                                <span class="badge badge-paid" style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:700; display:inline-block; background:#C6F6D5; color:#22543D;">Paid</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 16px; font-size:13px; color:#4A5568; border-bottom:1px solid #F0F0F0; font-weight:700; <?php echo $refunded ? 'color:#EF4444;' : 'color:#1B6B3A;'; ?>">
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

<?php require_once 'includes/footer.php'; ?>