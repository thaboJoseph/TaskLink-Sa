<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header('Location: /login.php');
    exit();
}

$provider_id = $_SESSION['user_id'];
$success = '';
$error   = '';

// ====================== DELETE ACCOUNT (FIXED) ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {

    try {
        $pdo->beginTransaction();

        // 1. Delete messages
        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")
            ->execute([$provider_id, $provider_id]);

        // 2. Delete reviews given to this provider
        $pdo->prepare("DELETE FROM reviews WHERE provider_id = ?")
            ->execute([$provider_id]);

        // 3. Delete services
        $pdo->prepare("DELETE FROM services WHERE provider_id = ?")
            ->execute([$provider_id]);

        // 4. Delete provider profile
        $pdo->prepare("DELETE FROM provider_profiles WHERE provider_id = ?")
            ->execute([$provider_id]);

        // 5. Delete bookings where this user is the provider
        $pdo->prepare("DELETE FROM bookings WHERE provider_id = ?")
            ->execute([$provider_id]);

        // 6. Finally delete the user
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")
            ->execute([$provider_id]);

        $pdo->commit();

        session_destroy();
        header('Location: /index.php?deleted=1');
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
    $location  = trim($_POST['location'] ?? '');
    $skills    = trim($_POST['skills'] ?? '');
    $bio       = trim($_POST['bio'] ?? '');

    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } else {
        // Update basic user info
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
        $stmt->execute([$full_name, $email, $phone, $provider_id]);

        // Profile Picture Upload
        if (!empty($_FILES['profile_picture']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/profile-pictures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $file = $_FILES['profile_picture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 2097152) {
                $newName = 'profile_' . $provider_id . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $relPath = 'uploads/profile-pictures/' . $newName;
                    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?")->execute([$relPath, $provider_id]);
                }
            } else {
                $error = 'Profile picture must be JPG, PNG or WEBP and under 2MB.';
            }
        }

        // Service Display Picture Upload
        if (!empty($_FILES['service_picture']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/service-pictures/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $file = $_FILES['service_picture'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 2097152) {
                $newName = 'service_' . $provider_id . '_' . time() . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $relPath = 'uploads/service-pictures/' . $newName;
                    $pdo->prepare("INSERT INTO provider_profiles (provider_id, service_picture) VALUES (?, ?) ON DUPLICATE KEY UPDATE service_picture = ?")->execute([$provider_id, $relPath, $relPath]);
                }
            } else {
                $error = 'Service picture must be JPG, PNG or WEBP and under 2MB.';
            }
        }

        // Proof of Work Uploads
        if (!empty($_FILES['proof_of_work']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/proof-of-work/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            $newPaths = [];
            $stmt = $pdo->prepare("SELECT proof_of_work FROM provider_profiles WHERE provider_id = ?");
            $stmt->execute([$provider_id]);
            $existingJson = $stmt->fetchColumn();
            $existingPaths = $existingJson ? json_decode($existingJson, true) : [];
            $files = $_FILES['proof_of_work'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed) && $files['size'][$i] <= 2097152) {
                        $newName = 'proof_' . $provider_id . '_' . time() . '_' . $i . '.' . $ext;
                        $dest = $uploadDir . $newName;
                        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                            $newPaths[] = 'uploads/proof-of-work/' . $newName;
                        }
                    }
                }
            }
            if (!empty($newPaths)) {
                $allPaths = array_merge($existingPaths ?: [], $newPaths);
                $json = json_encode(array_values($allPaths));
                $pdo->prepare("INSERT INTO provider_profiles (provider_id, proof_of_work) VALUES (?, ?) ON DUPLICATE KEY UPDATE proof_of_work = ?")->execute([$provider_id, $json, $json]);
            }
        }

        // Update provider profile fields
        $pdo->prepare("
            INSERT INTO provider_profiles (provider_id, bio, skills, location) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE bio = ?, skills = ?, location = ?
        ")->execute([
            $provider_id, $bio, $skills, $location,
            $bio, $skills, $location
        ]);

        if (empty($error)) {
            header('Location: /provider/profile.php?updated=1');
            exit();
        }
    }
}

// ====================== ADD NEW SERVICE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $category_id = $_POST['category_id'] ?? '';
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = trim($_POST['price'] ?? '');
    $price_type  = $_POST['price_type'] ?? 'hourly';

    if (empty($category_id) || empty($title) || empty($description) || empty($price)) {
        $error = 'Please fill in all fields for the new service.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO services (provider_id, category_id, title, description, price_type, price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $provider_id,
            $category_id,
            $title,
            $description,
            $price_type,
            $price
        ]);
        header('Location: /provider/profile.php?added=1');
        exit();
    }
}

// ====================== FETCH CURRENT DATA ======================
$stmt = $pdo->prepare("
    SELECT u.*, pp.bio, pp.skills, pp.location as provider_location,
           pp.rating, pp.total_reviews, pp.service_picture
    FROM users u
    LEFT JOIN provider_profiles pp ON pp.provider_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$provider_id]);
$provider = $stmt->fetch();

// Get proof of work images
$stmt = $pdo->prepare("SELECT proof_of_work FROM provider_profiles WHERE provider_id = ?");
$stmt->execute([$provider_id]);
$proofJson = $stmt->fetchColumn();
$proof_images = $proofJson ? json_decode($proofJson, true) : [];

// Get stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed
    FROM bookings WHERE provider_id = ?
");
$stmt->execute([$provider_id]);
$stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(b.estimated_hours * s.price), 0) as total_earned
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    WHERE b.provider_id = ? AND b.status = 'completed'
");
$stmt->execute([$provider_id]);
$earnings = $stmt->fetch();

// Get services
$stmt = $pdo->prepare("
    SELECT s.*, c.category_name 
    FROM services s
    JOIN categories c ON s.category_id = c.category_id
    WHERE s.provider_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$provider_id]);
$services = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – TaskLink SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body { background:#F8FAFC; font-family:'Inter',sans-serif; }
        .navbar { background:#FFFFFF; border-bottom:1px solid #E2E8F0; padding:16px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:18px; font-weight:700; color:#1B6B3A; text-decoration:none; }
        .page-wrap { max-width:1000px; margin:0 auto; padding:40px 24px; }
        .section-card { background:white; border-radius:12px; border:1px solid #E2E8F0; padding:24px; margin-bottom:24px; }
        .section-title { font-size:16px; font-weight:700; color:#1A202C; margin-bottom:20px; padding-bottom:12px; border-bottom:1px solid #F0F0F0; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:12px; font-weight:600; color:#4A5568; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.04em; }
        .form-group input, .form-group textarea, .form-group select { width:100%; padding:12px 14px; background:#F7F8FA; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; color:#1A202C; outline:none; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color:#1B6B3A; background:white; }
        .btn-save { background:#1B6B3A; color:white; padding:12px 28px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-save:hover { background:#134D2A; }
        .alert-success { background:#D1FAE5; color:#065F46; padding:12px 16px; border-radius:8px; border:1px solid #A7F3D0; }
        .alert-error { background:#FEE2E2; color:#991B1B; padding:12px 16px; border-radius:8px; border:1px solid #FECACA; }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="/provider/dashboard.php" class="nav-brand">← TaskLink SA</a>
    <div>
        <span style="font-size:13px; color:#4A5568;"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        <a href="/logout.php" style="color:#E53E3E; text-decoration:none; font-size:13px; font-weight:600; margin-left:16px;">Logout</a>
    </div>
</nav>

<div class="page-wrap">

    <h1 style="font-size:24px; font-weight:700; color:#1A202C; margin-bottom:24px;">My Provider Profile</h1>

    <?php if(isset($_GET['updated'])): ?>
        <div class="alert-success" style="margin-bottom:20px;">✅ Profile updated successfully!</div>
    <?php endif; ?>
    <?php if(isset($_GET['added'])): ?>
        <div class="alert-success" style="margin-bottom:20px;">✅ Service added successfully!</div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert-success" style="margin-bottom:20px;">✅ <?php echo $success; ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
        <div class="section-card" style="text-align:center; padding:16px;">
            <p style="font-size:28px; margin-bottom:6px;">📅</p>
            <p style="font-size:20px; font-weight:700; color:#1B6B3A;"><?php echo $stats['total_bookings'] ?? 0; ?></p>
            <p style="font-size:11px; color:#718096;">Total Bookings</p>
        </div>
        <div class="section-card" style="text-align:center; padding:16px;">
            <p style="font-size:28px; margin-bottom:6px;">✅</p>
            <p style="font-size:20px; font-weight:700; color:#22C55E;"><?php echo $stats['completed'] ?? 0; ?></p>
            <p style="font-size:11px; color:#718096;">Completed</p>
        </div>
        <div class="section-card" style="text-align:center; padding:16px;">
            <p style="font-size:28px; margin-bottom:6px;">⭐</p>
            <p style="font-size:20px; font-weight:700; color:#F5A623;"><?php echo number_format($provider['rating'] ?? 0, 1); ?></p>
            <p style="font-size:11px; color:#718096;">Avg Rating</p>
        </div>
        <div class="section-card" style="text-align:center; padding:16px;">
            <p style="font-size:28px; margin-bottom:6px;">💰</p>
            <p style="font-size:20px; font-weight:700; color:#1B6B3A;">R<?php echo number_format($earnings['total_earned'] ?? 0, 0); ?></p>
            <p style="font-size:11px; color:#718096;">Total Earned</p>
        </div>
    </div>

    <!-- Profile Form -->
    <form method="POST" action="" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">

        <!-- Personal Information -->
        <div class="section-card">
            <h2 class="section-title">Personal Information</h2>

            <div style="display:flex; align-items:center; gap:24px; margin-bottom:24px;">
                <div style="width:90px; height:90px; border-radius:50%; overflow:hidden; background:#E8F5EE; flex-shrink:0; display:flex; align-items:center; justify-content:center; border:3px solid #1B6B3A;">
                    <?php if (!empty($provider['profile_picture']) && file_exists(__DIR__ . '/../' . $provider['profile_picture'])): ?>
                        <img src="/<?php echo htmlspecialchars($provider['profile_picture']); ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:32px; font-weight:700; color:#1B6B3A;"><?php echo strtoupper(substr($provider['full_name'], 0, 1)); ?></span>
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
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($provider['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($provider['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($provider['phone'] ?? ''); ?>"></div>
            </div>
        </div>

        <!-- Provider Profile -->
        <div class="section-card">
            <h2 class="section-title">Provider Profile</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" value="<?php echo htmlspecialchars($provider['provider_location'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Skills / Specialisations</label>
                    <input type="text" name="skills" value="<?php echo htmlspecialchars($provider['skills'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Bio</label>
                <textarea name="bio" rows="4"><?php echo htmlspecialchars($provider['bio'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Service Display Picture -->
        <div class="section-card">
            <h2 class="section-title">Service Display Picture</h2>
            <p style="font-size:13px; color:#4A5568; margin-bottom:12px;">This image will appear on the homepage, browse page and service detail page.</p>
            
            <?php if (!empty($provider['service_picture'])): ?>
                <div style="margin-bottom:12px; border-radius:8px; overflow:hidden; max-width:220px; border:1px solid #E2E8F0;">
                    <img src="/<?php echo htmlspecialchars($provider['service_picture']); ?>" style="width:100%; display:block;">
                </div>
            <?php endif; ?>
            
            <input type="file" name="service_picture" accept="image/jpeg,image/png,image/webp">
            <p style="font-size:11px; color:#718096; margin-top:4px;">JPG, PNG, WEBP — Max 2MB</p>
        </div>

        <!-- Proof of Work -->
        <div class="section-card">
            <h2 class="section-title">Proof of Work / Previous Work</h2>
            <p style="font-size:13px; color:#4A5568; margin-bottom:16px;">These photos will appear in the "Previous Work" section on your service detail page.</p>

            <?php if (!empty($proof_images)): ?>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(110px,1fr)); gap:12px; margin-bottom:20px;">
                    <?php foreach($proof_images as $img): ?>
                        <div style="border-radius:8px; overflow:hidden; height:100px; border:1px solid #E2E8F0;">
                            <img src="/<?php echo htmlspecialchars($img); ?>" style="width:100%; height:100%; object-fit:cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>Upload New Proof of Work Photos</label>
                <input type="file" name="proof_of_work[]" accept="image/jpeg,image/png,image/webp" multiple>
                <p style="font-size:11px; color:#718096; margin-top:4px;">You can upload multiple images (JPG, PNG, WEBP — Max 2MB each).</p>
            </div>
        </div>

        <!-- Save Button -->
        <div style="display:flex; justify-content:flex-end; gap:12px; margin-bottom:32px;">
            <a href="/provider/dashboard.php" style="padding:12px 24px; background:#F0F0F0; color:#4A5568; border-radius:8px; text-decoration:none;">Cancel</a>
            <button type="submit" class="btn-save">Save Profile</button>
        </div>
    </form>

    <!-- MY SERVICES SECTION -->
    <div class="section-card" style="margin-top:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 style="font-size:16px; font-weight:700; color:#1A202C;">
                My Services (<?php echo count($services); ?>)
            </h2>
            <button type="button" onclick="document.getElementById('addServiceForm').style.display = (document.getElementById('addServiceForm').style.display === 'block' ? 'none' : 'block')" 
                    class="btn-save" style="font-size:13px; padding:8px 16px;">
                + Add New Service
            </button>
        </div>

        <!-- Add New Service Form -->
        <div id="addServiceForm" style="display:none; background:#F7F8FA; padding:20px; border-radius:10px; margin-bottom:24px;">
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_service">

                <div class="form-group">
                    <label>Category *</label>
                    <select name="category_id" required>
                        <option value="">Select category...</option>
                        <?php 
                        $cats = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
                        foreach($cats as $cat): 
                        ?>
                            <option value="<?php echo $cat['category_id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Service Title *</label>
                    <input type="text" name="title" required>
                </div>

                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" rows="4" required></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label>Price (R) *</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Price Type *</label>
                        <select name="price_type" required>
                            <option value="hourly">Per Hour</option>
                            <option value="fixed">Fixed Price</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:16px;">
                    <button type="button" onclick="document.getElementById('addServiceForm').style.display='none'" 
                            style="flex:1; padding:10px; background:#F0F0F0; border:none; border-radius:8px; font-weight:600;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-save" style="flex:2;">Add Service</button>
                </div>
            </form>
        </div>

        <!-- Existing Services List -->
        <?php if(empty($services)): ?>
            <p style="color:#A0AEC0; text-align:center; padding:20px;">You haven't added any services yet.</p>
        <?php else: ?>
            <?php foreach($services as $svc): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid #F0F0F0;">
                    <div>
                        <p style="font-weight:600;"><?php echo htmlspecialchars($svc['title']); ?></p>
                        <p style="font-size:12px; color:#718096;">
                            <?php echo htmlspecialchars($svc['category_name']); ?> • 
                            R<?php echo number_format($svc['price'], 2); ?>
                            <?php echo $svc['price_type'] === 'hourly' ? '/hr' : ''; ?>
                        </p>
                    </div>
                    <div>
                        <a href="/provider/edit-service.php?id=<?php echo $svc['service_id']; ?>" 
                           style="padding:6px 14px; background:#E8F5EE; color:#1B6B3A; border-radius:6px; font-size:12px; text-decoration:none;">Edit</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ====================== DELETE ACCOUNT ====================== -->
    <div style="margin-top:40px;">
        <div class="section-card" style="border:2px solid #FEE2E2; background:#FFF5F5;">
            <h2 style="font-size:16px; font-weight:700; color:#991B1B; margin-bottom:8px;">Danger Zone</h2>
            <p style="color:#7F1D1D; font-size:14px; margin-bottom:16px;">
                Deleting your account will permanently remove your profile, services, bookings, and all associated data. 
                This action cannot be undone.
            </p>

            <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your provider account? This cannot be undone.');">
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