<?php
$page_title = 'Manage Users';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// ====================== HANDLE DELETE USER ======================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];

    if ($del_id === $_SESSION['user_id']) {
        $error = 'You cannot delete your own admin account.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Delete refund_requests (references payments)
            $pdo->prepare("
                DELETE FROM refund_requests 
                WHERE payment_id IN (
                    SELECT p.payment_id FROM payments p
                    JOIN bookings b ON p.booking_id = b.booking_id
                    WHERE b.client_id = ? OR b.provider_id = ?
                )
            ")->execute([$del_id, $del_id]);

            // 2. Delete reviews (references bookings) ← Moved up
            $pdo->prepare("DELETE FROM reviews WHERE provider_id = ? OR client_id = ?")
                ->execute([$del_id, $del_id]);

            // 3. Delete payments
            $pdo->prepare("
                DELETE FROM payments 
                WHERE booking_id IN (
                    SELECT booking_id FROM bookings 
                    WHERE client_id = ? OR provider_id = ?
                )
            ")->execute([$del_id, $del_id]);

            // 4. Delete bookings
            $pdo->prepare("DELETE FROM bookings WHERE client_id = ? OR provider_id = ?")
                ->execute([$del_id, $del_id]);

            // 5. Delete messages
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")
                ->execute([$del_id, $del_id]);

            // 6. Delete services
            $pdo->prepare("DELETE FROM services WHERE provider_id = ?")
                ->execute([$del_id]);

            // 7. Delete provider profile
            $pdo->prepare("DELETE FROM provider_profiles WHERE provider_id = ?")
                ->execute([$del_id]);

            // 8. Delete user reports
            $pdo->prepare("DELETE FROM user_reports WHERE reporter_id = ? OR reported_id = ?")
                ->execute([$del_id, $del_id]);

            // 9. Finally delete the user
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$del_id]);

            $pdo->commit();
            $success = 'User permanently deleted from the system.';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to delete user. Error: ' . $e->getMessage();
        }
    }
}

// ====================== SEARCH & FILTER ======================
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

$where = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($role_filter)) {
    $where[] = "u.role = ?";
    $params[] = $role_filter;
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT u.*,
           COUNT(DISTINCT b.booking_id) as total_bookings,
           COUNT(DISTINCT s.service_id) as total_services
    FROM users u
    LEFT JOIN bookings b ON (b.client_id = u.user_id OR b.provider_id = u.user_id)
    LEFT JOIN services s ON s.provider_id = u.user_id
    WHERE $whereStr
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Filters -->
<div style="display:flex; gap:12px; margin-bottom:24px; flex-wrap:wrap; align-items:center;">
    <form method="GET" action="" style="display:flex; gap:12px; flex-wrap:wrap;">
        <input type="text" name="search" class="search-bar"
               placeholder="Search by name or email..."
               value="<?php echo htmlspecialchars($search); ?>">
        
        <select name="role" style="padding:8px 14px; border:1px solid #E2E8F0; border-radius:8px; font-size:13px; background:white; outline:none;">
            <option value="">All Roles</option>
            <option value="client" <?php echo $role_filter==='client' ? 'selected' : ''; ?>>Clients</option>
            <option value="provider" <?php echo $role_filter==='provider' ? 'selected' : ''; ?>>Providers</option>
            <option value="admin" <?php echo $role_filter==='admin' ? 'selected' : ''; ?>>Admins</option>
        </select>
        
        <button type="submit" class="btn-primary">Search</button>
        
        <?php if($search || $role_filter): ?>
            <a href="/admin/users.php" class="btn-sm btn-green">Clear</a>
        <?php endif; ?>
    </form>
    
    <span style="margin-left:auto; font-size:13px; color:#718096;">
        <?php echo count($users); ?> user(s) found
    </span>
</div>

<!-- Users Table -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>All Users</h2>
    </div>
    
    <?php if(empty($users)): ?>
        <div class="empty-state">
            <p>👥</p>
            <p>No users found</p>
        </div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Bookings</th>
                <th>Services</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td style="color:#A0AEC0;">#<?php echo $user['user_id']; ?></td>
                <td style="font-weight:600;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div style="width:28px; height:28px; background:#1B6B3A; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:11px; font-weight:700;">
                            <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
                        </div>
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </div>
                </td>
                <td style="color:#718096;"><?php echo htmlspecialchars($user['email']); ?></td>
                <td style="color:#718096;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                <td>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </td>
                <td style="text-align:center;"><?php echo $user['total_bookings']; ?></td>
                <td style="text-align:center;"><?php echo $user['total_services']; ?></td>
                <td style="color:#718096; font-size:12px;">
                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                </td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <?php if($user['user_id'] !== $_SESSION['user_id']): ?>
                            <a href="/admin/messages.php?chat_with=<?php echo $user['user_id']; ?>" class="btn-sm btn-green">💬 Message</a>
                            
                            <a href="?delete=<?php echo $user['user_id']; ?>" 
                               class="btn-sm btn-danger"
                               onclick="return confirm('Permanently delete <?php echo htmlspecialchars($user['full_name']); ?> and all their data? This cannot be undone.')">
                                Remove
                            </a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#A0AEC0;">You</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>