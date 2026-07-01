<?php
$page_title = 'Dashboard';
require_once '../includes/db.php';
require_once 'includes/header.php';

// Stats
$total_users     = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$total_providers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetchColumn();
$total_clients   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
$total_bookings  = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_services  = $pdo->query("SELECT COUNT(*) FROM services")->fetchColumn();
$total_revenue   = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'")->fetchColumn();
$pending_reports = $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status = 'pending'")->fetchColumn();
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();

// Recent users
$recent_users = $pdo->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 8
")->fetchAll();

// Recent bookings
$recent_bookings = $pdo->query("
    SELECT b.*, 
           u1.full_name as client_name,
           u2.full_name as provider_name,
           s.title as service_title
    FROM bookings b
    JOIN users u1 ON b.client_id = u1.user_id
    JOIN users u2 ON b.provider_id = u2.user_id
    JOIN services s ON b.service_id = s.service_id
    ORDER BY b.created_at DESC
    LIMIT 6
")->fetchAll();
?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo $total_users; ?></div>
        <div class="stat-accent" style="background:#3182CE;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Bookings</div>
        <div class="stat-value"><?php echo $total_bookings; ?></div>
        <div class="stat-accent" style="background:#1B6B3A;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Revenue Collected</div>
        <div class="stat-value">R<?php echo number_format($total_revenue, 0); ?></div>
        <div class="stat-accent" style="background:#1B6B3A;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Services</div>
        <div class="stat-value"><?php echo $total_services; ?></div>
        <div class="stat-accent" style="background:#F5A623;"></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Reports</div>
        <div class="stat-value" style="color:<?php echo $pending_reports > 0 ? '#EF4444' : '#1A202C'; ?>">
            <?php echo $pending_reports; ?>
        </div>
        <div class="stat-accent" style="background:#EF4444;"></div>
    </div>
</div>

<!-- Quick Stats Row -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); 
            gap:16px; margin-bottom:32px;">
    <div style="background:#E8F5EE; border-radius:10px; 
                padding:16px; display:flex; 
                justify-content:space-between; 
                align-items:center;">
        <div>
            <p style="font-size:11px; font-weight:600; 
                      color:#1B6B3A; text-transform:uppercase;">
                Clients
            </p>
            <p style="font-size:22px; font-weight:700; 
                      color:#1B6B3A;">
                <?php echo $total_clients; ?>
            </p>
        </div>
        <span style="font-size:32px;">👤</span>
    </div>
    <div style="background:#EBF8FF; border-radius:10px; 
                padding:16px; display:flex; 
                justify-content:space-between; 
                align-items:center;">
        <div>
            <p style="font-size:11px; font-weight:600; 
                      color:#2B6CB0; text-transform:uppercase;">
                Providers
            </p>
            <p style="font-size:22px; font-weight:700; 
                      color:#2B6CB0;">
                <?php echo $total_providers; ?>
            </p>
        </div>
        <span style="font-size:32px;">🛠️</span>
    </div>
    <div style="background:#FEF3C7; border-radius:10px; 
                padding:16px; display:flex; 
                justify-content:space-between; 
                align-items:center;">
        <div>
            <p style="font-size:11px; font-weight:600; 
                      color:#D97706; text-transform:uppercase;">
                Pending Bookings
            </p>
            <p style="font-size:22px; font-weight:700; 
                      color:#D97706;">
                <?php echo $pending_bookings; ?>
            </p>
        </div>
        <span style="font-size:32px;">⏳</span>
    </div>
</div>

<!-- Two column tables -->
<div style="display:grid; grid-template-columns:1.2fr 1fr; 
            gap:24px;">

    <!-- Recent Users -->
    <div class="admin-table-wrap">
        <div class="admin-table-header">
            <h2>Recent Registrations</h2>
            <a href="/admin/users.php" 
               class="btn-green btn-sm">
                View All →
            </a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_users as $user): ?>
                <tr>
                    <td style="font-weight:600;">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </td>
                    <td style="color:#718096;">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </td>
                    <td>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td style="color:#718096;">
                        <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Bookings -->
    <div class="admin-table-wrap">
        <div class="admin-table-header">
            <h2>Recent Bookings</h2>
            <a href="/admin/bookings.php" 
               class="btn-green btn-sm">
                View All →
            </a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Service</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_bookings as $b): ?>
                <tr>
                    <td style="font-weight:600;">
                        <?php echo htmlspecialchars($b['client_name']); ?>
                    </td>
                    <td style="color:#718096; font-size:12px;">
                        <?php echo htmlspecialchars($b['service_title']); ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo $b['status']; ?>">
                            <?php echo ucfirst($b['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>