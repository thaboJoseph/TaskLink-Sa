<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: /
    exit();
}

$client_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// ====================== DELETE ACCOUNT (FIXED) ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {

    try {
        $pdo->beginTransaction();

        // 1. Delete messages where user is sender or receiver
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")
            ->execute([$client_id, $client_id]);

        // 2. Delete reviews made by this client
        $pdo->prepare("DELETE FROM reviews WHERE client_id = ?")
            ->execute([$client_id]);

        // 3. Delete bookings made by this client
        $pdo->prepare("DELETE FROM bookings WHERE client_id = ?")
            ->execute([$client_id]);

        // 4. Finally delete the user
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")
            ->execute([$client_id]);

        $pdo->commit();

        session_destroy();
        header('Location: /leted=1');
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Cannot delete account. You still have active or completed bookings/payments. Please contact support to close your account.";
    }
}

// ====================== HANDLE PROFILE UPDATE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {

    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } else {
        // Update basic user info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $phone, $client_id]);

        // Profile Picture Upload
        if (!empty($_FILES['profile_picture']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/profile-pictures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $file = $_FILES['profile_picture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 2097152) {
                $newName = 'profile_' . $client_id . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $relPath = 'uploads/profile-pictures/' . $newName;
                    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$relPath, $client_id]);
                }
            } else {
                $error = 'Profile picture must be JPG, PNG or WEBP and under 2MB.';
            }
        }

        if (empty($error)) {
            header('Location: /le.php?updated=1');
            exit();
        }
    }
}

// ====================== FETCH CURRENT DATA ======================
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(b.booking_id) as total_bookings,
           SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings
    FROM users u
    LEFT JOIN bookings b ON b.client_id = u.user_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

// Get recent bookings
$stmt = $pdo->prepare("
    SELECT b.*, s.title as service_title, u.full_name as provider_name
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.provider_id = u.user_id
    WHERE b.client_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute([$client_id]);
$recent_bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – TaskLink SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/s">
    <style>
        body { background:#F8FAFC; font-family:'Inter',sans-serif; }
        .navbar { background:#FFFFFF; border-bottom:1px solid #E2E8F0; padding:16px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:18px; font-weight:700; color:#1B6B3A; text-decoration:none; }
        .page-wrap { max-width:900px; margin:0 auto; padding:40px 24px; }
        .section-card { background:white; border-radius:12px; border:1px solid #E2E8F0; padding:24px; margin-bottom:24px; }
        .section-title { font-size:16px; font-weight:700; color:#1A202C; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid #F0F0F0; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:12px; font-weight:600; color:#4A5568; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.04em; }
        .form-group input { width:100%; padding:12px 14px; background:#F7F8FA; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; color:#1A202C; outline:none; }
        .form-group input:focus { border-color:#1B6B3A; background:white; }
        .btn-save { background:#1B6B3A; color:white; padding:12px 28px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-save:hover { background:#134D2A; }
        .alert-success { background:#D1FAE5; color:#065F46; padding:12px 16px; border-radius:8px; border:1px solid #A7F3D0; }
        .alert-error { background:#FEE2E2; color:#991B1B; padding:12px 16px; border-radius:8px; border:1px solid #FECACA; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/lass="nav-brand">← TaskLink SA</a>
    <div>
        <span style="font-size:13px; color:#4A5568;"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="/style="color:#E53E3E; text-decoration:none; font-size:13px; font-weight:600; margin-left:16px;">Logout</a>
    </div>
</nav>

<div class="page-wrap">

    <h1 style="font-size:24px; font-weight:700; color:#1A202C; margin-bottom:24px;">My Profile</h1>

    <?php if(isset($_GET['updated'])): ?>
        <div class="alert-success" style="margin-bottom:20px;">✅ Profile updated successfully!</div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px;">
        <div class="section-card" style="text-align:center;">
            <p style="font-size:28px; margin-bottom:6px;">📅</p>
            <p style="font-size:22px; font-weight:700; color:#1B6B3A;"><?php echo $client['total_bookings'] ?? 0; ?></p>
            <p style="font-size:13px; color:#718096;">Total Bookings</p>
        </div>
        <div class="section-card" style="text-align:center;">
            <p style="font-size:28px; margin-bottom:6px;">✅</p>
            <p style="font-size:22px; font-weight:700; color:#22C55E;"><?php echo $client['completed_bookings'] ?? 0; ?></p>
            <p style="font-size:13px; color:#718096;">Completed</p>
        </div>
        <div class="section-card" style="text-align:center;">
            <p style="font-size:28px; margin-bottom:6px;">⭐</p>
            <p style="font-size:22px; font-weight:700; color:#F5A623;">
                <?php 
                $stmt = $pdo->prepare("SELECT AVG(rating) FROM reviews WHERE client_id = ?");
                $stmt->execute([$client_id]);
                echo number_format($stmt->fetchColumn() ?: 0, 1); 
                ?>
            </p>
            <p style="font-size:13px; color:#718096;">Avg Rating Given</p>
        </div>
    </div>

    <!-- Profile Form -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">

        <div class="section-card">
            <h2 class="section-title">Personal Information</h2>

            <div style="display:flex; align-items:center; gap:24px; margin-bottom:24px;">
                <div style="width:90px; height:90px; border-radius:50%; overflow:hidden; background:#E8F5EE; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:3px solid #1B6B3A;">
                    <?php if (!empty($client['profile_picture']) && file_exists(__DIR__ . '/../' . $client['profile_picture'])): ?>
                        <img src="/tmlspecialchars($client['profile_picture']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:32px; font-weight:700; color:#1B6B3A;"><?php echo strtoupper(substr($client['full_name'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <label style="font-size:13px; font-weight:600; color:#1A202C; display:block; margin-bottom:6px;">Profile Picture</label>
                    <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                    <p style="font-size:11px; color:#718096; margin-top:4px;">JPG, PNG, WEBP — Max 2MB</p>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($client['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:10px;">
                <a href="/tyle="padding:12px 24px; background:#F0F0F0; color:#4A5568; border-radius:8px; text-decoration:none;">Cancel</a>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </div>
    </form>

    <!-- Recent Bookings -->
    <div class="section-card">
        <h2 class="section-title">Recent Bookings</h2>
        <?php if (empty($recent_bookings)): ?>
            <p style="color:#A0AEC0;">You haven't made any bookings yet.</p>
        <?php else: ?>
            <?php foreach($recent_bookings as $booking): ?>
                <div style="padding:14px 0; border-bottom:1px solid #F0F0F0; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <p style="font-weight:600;"><?php echo htmlspecialchars($booking['service_title']); ?></p>
                        <p style="font-size:13px; color:#718096;">with <?php echo htmlspecialchars($booking['provider_name']); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ====================== DELETE ACCOUNT ====================== -->
    <div style="margin-top:30px;">
        <div class="section-card" style="border:2px solid #FEE2E2; background:#FFF5F5;">
            <h2 style="font-size:16px; font-weight:700; color:#991B1B; margin-bottom:8px;">Danger Zone</h2>
            <p style="color:#7F1D1D; font-size:14px; margin-bottom:16px;">
                Deleting your account will permanently remove your profile, bookings, and all associated data. 
                This action cannot be undone.
            </p>

            <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This cannot be undone.');">
                <input type="hidden" name="action" value="delete_account">
                <button type="submit" 
                        style="background:#DC2626; color:white; padding:10px 20px; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
                    Delete My Account
                </button>
            </form>
        </div>
    </div>

</div>

</body>
</html>