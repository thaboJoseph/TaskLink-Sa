<?php
$page_title = 'Manage Services';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// Delete service
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM services WHERE service_id = ?")
            ->execute([(int)$_GET['delete']]);
        $success = 'Service deleted successfully.';
    } catch(Exception $e) {
        $error = 'Cannot delete — this service has active bookings.';
    }
}

$search   = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['category'] ?? 0);

$where  = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[]  = "(s.title LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($cat_filter > 0) {
    $where[]  = "s.category_id = ?";
    $params[] = $cat_filter;
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.*, c.category_name, u.full_name as provider_name,
           COUNT(b.booking_id) as total_bookings
    FROM services s
    JOIN categories c ON s.category_id = c.category_id
    JOIN users u ON s.provider_id = u.user_id
    LEFT JOIN bookings b ON b.service_id = s.service_id
    WHERE $whereStr
    GROUP BY s.service_id
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$services = $stmt->fetchAll();

$categories = $pdo->query(
    "SELECT * FROM categories ORDER BY category_name"
)->fetchAll();
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="" 
      style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
    <input type="text" name="search" class="search-bar"
           placeholder="Search by title or provider..."
           value="<?php echo htmlspecialchars($search); ?>">
    <select name="category"
            style="padding:8px 14px; border:1px solid #E2E8F0; 
                   border-radius:8px; font-size:13px; background:white;">
        <option value="0">All Categories</option>
        <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['category_id']; ?>"
                <?php echo $cat_filter==$cat['category_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat['category_name']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-primary">Filter</button>
    <?php if($search || $cat_filter): ?>
        <a href="/admin/services.php" 
           class="btn-sm btn-green">Clear</a>
    <?php endif; ?>
    <span style="margin-left:auto; font-size:13px; color:#718096; 
                 align-self:center;">
        <?php echo count($services); ?> service(s)
    </span>
</form>

<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>All Services</h2>
    </div>
    <?php if(empty($services)): ?>
        <div class="empty-state">
            <p>🛠️</p>
            <p>No services found</p>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Provider</th>
                <th>Category</th>
                <th>Price</th>
                <th>Bookings</th>
                <th>Added</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($services as $s): ?>
            <tr>
                <td style="color:#A0AEC0;">#<?php echo $s['service_id']; ?></td>
                <td style="font-weight:600; max-width:180px;">
                    <?php echo htmlspecialchars($s['title']); ?>
                </td>
                <td style="color:#718096;">
                    <?php echo htmlspecialchars($s['provider_name']); ?>
                </td>
                <td>
                    <span style="background:#E8F5EE; color:#1B6B3A; 
                                 padding:3px 10px; border-radius:12px; 
                                 font-size:11px; font-weight:600;">
                        <?php echo htmlspecialchars($s['category_name']); ?>
                    </span>
                </td>
                <td style="font-weight:600; color:#1B6B3A;">
                    R<?php echo number_format($s['price'], 2); ?>
                    <span style="font-size:11px; color:#A0AEC0; font-weight:400;">
                        <?php echo $s['price_type']==='hourly' ? '/hr' : ' fixed'; ?>
                    </span>
                </td>
                <td style="text-align:center;">
                    <?php echo $s['total_bookings']; ?>
                </td>
                <td style="color:#718096; font-size:12px;">
                    <?php echo date('d M Y', strtotime($s['created_at'])); ?>
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <a href="/service-detail.php?id=<?php echo $s['service_id']; ?>"
                           class="btn-sm btn-green"
                           target="_blank">
                            View
                        </a>
                        <a href="?delete=<?php echo $s['service_id']; ?>"
                           class="btn-sm btn-danger"
                           onclick="return confirm('Delete this service?')">
                            Remove
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>