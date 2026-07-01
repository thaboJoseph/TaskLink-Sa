<?php
$page_title = 'Manage Bookings';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// Cancel pending booking
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = (int)$_GET['cancel'];
    $stmt = $pdo->prepare(
        "SELECT status FROM bookings WHERE booking_id = ?"
    );
    $stmt->execute([$cancel_id]);
    $bk = $stmt->fetch();
    if ($bk && $bk['status'] === 'pending') {
        $pdo->prepare(
            "UPDATE bookings SET status='rejected' WHERE booking_id=?"
        )->execute([$cancel_id]);
        $success = 'Booking #' . str_pad($cancel_id,5,'0',STR_PAD_LEFT) . ' has been cancelled.';
    } else {
        $error = 'Only pending bookings can be cancelled.';
    }
}

$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['search'] ?? '');

$where  = ['1=1'];
$params = [];

if (!empty($status_filter)) {
    $where[]  = "b.status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $where[]  = "(u1.full_name LIKE ? OR u2.full_name LIKE ? OR s.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT b.*,
           u1.full_name as client_name,
           u1.email as client_email,
           u1.phone as client_phone,
           u2.full_name as provider_name,
           u2.email as provider_email,
           s.title as service_title,
           s.price as service_price,
           c.category_name
    FROM bookings b
    JOIN users u1 ON b.client_id = u1.user_id
    JOIN users u2 ON b.provider_id = u2.user_id
    JOIN services s ON b.service_id = s.service_id
    JOIN categories c ON s.category_id = c.category_id
    WHERE $whereStr
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$counts = [];
foreach(['pending','accepted','completed','rejected'] as $st) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE status=?");
    $c->execute([$st]);
    $counts[$st] = $c->fetchColumn();
}
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex; gap:8px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="?" style="padding:8px 16px; border-radius:20px; font-size:13px; 
              font-weight:600; text-decoration:none;
              <?php echo empty($status_filter) ? 
              'background:#1B6B3A;color:white;' : 
              'background:#F0F0F0;color:#4A5568;'; ?>">
        All
    </a>
    <?php foreach(['pending','accepted','completed','rejected'] as $st): ?>
        <a href="?status=<?php echo $st; ?>"
           style="padding:8px 16px; border-radius:20px; font-size:13px; 
                  font-weight:600; text-decoration:none;
                  <?php echo $status_filter===$st ? 
                  'background:#1B6B3A;color:white;' : 
                  'background:#F0F0F0;color:#4A5568;'; ?>">
            <?php echo ucfirst($st); ?>
            <span style="font-size:11px; opacity:0.8;">
                (<?php echo $counts[$st]; ?>)
            </span>
        </a>
    <?php endforeach; ?>

    <form method="GET" action="" 
          style="margin-left:auto; display:flex; gap:8px;">
        <?php if($status_filter): ?>
            <input type="hidden" name="status" 
                   value="<?php echo $status_filter; ?>">
        <?php endif; ?>
        <input type="text" name="search" class="search-bar"
               placeholder="Search client, provider, service..."
               value="<?php echo htmlspecialchars($search); ?>"
               style="width:280px;">
        <button type="submit" class="btn-primary">Search</button>
    </form>
</div>

<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>
            <?php echo $status_filter ? 
            ucfirst($status_filter).' Bookings' : 'All Bookings'; ?>
        </h2>
        <span style="font-size:13px; color:#718096;">
            <?php echo count($bookings); ?> booking(s)
        </span>
    </div>
    <?php if(empty($bookings)): ?>
        <div class="empty-state">
            <p>📅</p>
            <p>No bookings found</p>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Provider</th>
                <th>Service</th>
                <th>Date</th>
                <th>Location</th>
                <th>Hours</th>
                <th>Total</th>
                <th>Note</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($bookings as $b):
                $total = $b['estimated_hours'] * $b['service_price'];
            ?>
            <tr>
                <td style="color:#A0AEC0; font-size:12px;">
                    #<?php echo str_pad($b['booking_id'],5,'0',STR_PAD_LEFT); ?>
                </td>
                <td>
                    <p style="font-weight:600; font-size:13px;">
                        <?php echo htmlspecialchars($b['client_name']); ?>
                    </p>
                    <p style="font-size:11px; color:#718096;">
                        <?php echo htmlspecialchars($b['client_email']); ?>
                    </p>
                    <?php if($b['client_phone']): ?>
                    <p style="font-size:11px; color:#718096;">
                        <?php echo htmlspecialchars($b['client_phone']); ?>
                    </p>
                    <?php endif; ?>
                </td>
                <td>
                    <p style="font-size:13px; color:#4A5568; font-weight:500;">
                        <?php echo htmlspecialchars($b['provider_name']); ?>
                    </p>
                    <p style="font-size:11px; color:#718096;">
                        <?php echo htmlspecialchars($b['provider_email']); ?>
                    </p>
                </td>
                <td>
                    <p style="font-size:13px; color:#4A5568;">
                        <?php echo htmlspecialchars($b['service_title']); ?>
                    </p>
                    <span style="background:#E8F5EE; color:#1B6B3A; 
                                 padding:2px 8px; border-radius:10px; 
                                 font-size:10px; font-weight:600;">
                        <?php echo htmlspecialchars($b['category_name']); ?>
                    </span>
                </td>
                <td style="font-size:12px; color:#718096;">
                    <?php echo date('d M Y', strtotime($b['booking_date'])); ?>
                </td>
                <td style="font-size:12px; color:#718096; max-width:140px;">
                    📍 <?php echo htmlspecialchars($b['address']); ?>
                </td>
                <td style="text-align:center;">
                    <?php echo $b['estimated_hours']; ?>h
                </td>
                <td style="font-weight:600; color:#1B6B3A;">
                    R<?php echo number_format($total, 2); ?>
                </td>
                <td style="font-size:12px; color:#718096; max-width:120px;">
                    <?php echo !empty($b['note']) ? 
                    htmlspecialchars(substr($b['note'],0,60)).'...' : 
                    '<span style="color:#CBD5E0;">—</span>'; ?>
                </td>
                <td>
                    <span class="status-badge status-<?php echo $b['status']; ?>">
                        <?php echo ucfirst($b['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if($b['status'] === 'pending'): ?>
                        <a href="?cancel=<?php echo $b['booking_id']; ?><?php echo $status_filter ? '&status='.$status_filter : ''; ?>"
                           class="btn-sm btn-danger"
                           onclick="return confirm('Cancel this booking?')">
                            ✕ Cancel
                        </a>
                    <?php else: ?>
                        <span style="font-size:11px; color:#CBD5E0;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>