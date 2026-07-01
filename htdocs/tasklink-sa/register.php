<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$error   = '';
$success = '';
$step    = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$role    = isset($_POST['role']) ? $_POST['role'] : 'client';

function validateAndUploadProfilePicture($file, $isRequired = false) {
    if (empty($file['name'])) {
        if ($isRequired) {
            return ['success' => false, 'error' => 'Profile picture is required for Service Providers.'];
        }
        return ['success' => true, 'path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'An error occurred during profile picture upload.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Profile picture size maximum allowed is 2MB.'];
    }
    $uploadDir = __DIR__ . '/uploads/profile-pictures/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
    $newName = 'profile_' . uniqid() . '_' . time() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return ['success' => true, 'path' => 'uploads/profile-pictures/' . $newName];
    }
    return ['success' => false, 'error' => 'Failed to save profile picture.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $full_name        = trim($_POST['full_name'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $password         = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $location         = trim($_POST['location'] ?? '');
        $role             = $_POST['role'] ?? 'client';

        if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'That email is already registered. Please login instead.';
            } else {
                $isProvider = ($role === 'provider');
                $uploadResult = validateAndUploadProfilePicture($_FILES['profile_picture'] ?? [], $isProvider);
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'];
                } else {
                    if ($role === 'client') {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, profile_picture) VALUES (?, ?, ?, ?, 'client', ?)");
                        $stmt->execute([$full_name, $email, $hashed, $phone, $uploadResult['path']]);
                        $_SESSION['user_id']   = $pdo->lastInsertId();
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['email']     = $email;
                        $_SESSION['role']      = 'client';
                        header('Location: /index.php');
                        exit();
                    } else {
                        $_SESSION['reg_full_name']       = $full_name;
                        $_SESSION['reg_email']           = $email;
                        $_SESSION['reg_phone']           = $phone;
                        $_SESSION['reg_password']        = $password;
                        $_SESSION['reg_location']        = $location;
                        $_SESSION['reg_role']            = 'provider';
                        $_SESSION['reg_profile_picture'] = $uploadResult['path'];
                        $step = 2;
                    }
                }
            }
        }
    }
    elseif ($step === 2) {
        $category_id   = $_POST['category_id'] ?? '';
        $service_title = trim($_POST['service_title'] ?? '');
        $service_desc  = trim($_POST['service_desc'] ?? '');
        $id_number     = trim($_POST['id_number'] ?? '');

        if (empty($category_id) || empty($service_title) || empty($service_desc) || empty($id_number)) {
            $error = 'Please fill in all fields.';
        } else {
            $_SESSION['reg_category_id']   = $category_id;
            $_SESSION['reg_service_title'] = $service_title;
            $_SESSION['reg_service_desc']  = $service_desc;
            $_SESSION['reg_id_number']     = $id_number;
            $step = 3;
        }
    }
    elseif ($step === 3) {
        $hourly_rate  = trim($_POST['hourly_rate'] ?? '');
        $callout_fee  = trim($_POST['callout_fee'] ?? '');
        $agreed_terms = isset($_POST['agreed_terms']);

        if (empty($hourly_rate)) {
            $error = 'Please enter your hourly rate.';
        } elseif (!$agreed_terms) {
            $error = 'You must agree to the Terms and Conditions.';
        } else {
            // ====================== SERVICE PICTURE ======================
            $servicePicPath = null;
            if (!empty($_FILES['service_picture']['name'])) {
                $f = $_FILES['service_picture'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) && $f['size'] <= 2 * 1024 * 1024) {
                    $dir = __DIR__ . '/uploads/service-pictures/';
                    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
                    $newName = 'service_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $dir . $newName)) {
                        $servicePicPath = 'uploads/service-pictures/' . $newName;
                    }
                }
            }

            // ====================== CREATE USER ======================
            $hashed = password_hash($_SESSION['reg_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, profile_picture) VALUES (?, ?, ?, ?, 'provider', ?)");
            $stmt->execute([$_SESSION['reg_full_name'], $_SESSION['reg_email'], $hashed, $_SESSION['reg_phone'], $_SESSION['reg_profile_picture']]);
            $user_id = $pdo->lastInsertId();

            // Create provider profile
            $stmt = $pdo->prepare("INSERT INTO provider_profiles (provider_id, bio, skills, location) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $_SESSION['reg_service_desc'], $_SESSION['reg_service_title'], $_SESSION['reg_location'] ?? '']);

            // ====================== CREATE SERVICE ======================
            $stmt = $pdo->prepare("INSERT INTO services (provider_id, category_id, title, description, price_type, price, service_picture) VALUES (?, ?, ?, ?, 'hourly', ?, ?)");
            $stmt->execute([$user_id, $_SESSION['reg_category_id'], $_SESSION['reg_service_title'], $_SESSION['reg_service_desc'], $hourly_rate, $servicePicPath]);
            $service_id = $pdo->lastInsertId();

            // ====================== PROOF OF WORK (FIXED) ======================
            $proofPaths = [];

            if (!empty($_FILES['proof_of_work']['name'][0])) {
                $dir = __DIR__ . '/uploads/proof-of-work/';
                if (!is_dir($dir)) { mkdir($dir, 0755, true); }

                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                foreach ($_FILES['proof_of_work']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['proof_of_work']['error'][$index] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['proof_of_work']['name'][$index], PATHINFO_EXTENSION));
                        if (in_array($ext, $allowed) && $_FILES['proof_of_work']['size'][$index] <= 2 * 1024 * 1024) {
                            $newName = 'proof_' . uniqid() . '.' . $ext;
                            if (move_uploaded_file($tmpName, $dir . $newName)) {
                                $proofPaths[] = 'uploads/proof-of-work/' . $newName;
                            }
                        }
                    }
                }

                // Save as JSON in provider_profiles (this is what profile & service detail pages read)
                if (!empty($proofPaths)) {
                    $jsonProof = json_encode($proofPaths);
                    $pdo->prepare("UPDATE provider_profiles SET proof_of_work = ? WHERE provider_id = ?")
                        ->execute([$jsonProof, $user_id]);
                }
            }

            // ====================== CLEANUP & LOGIN ======================
            $final_full_name = $_SESSION['reg_full_name'];
            $final_email     = $_SESSION['reg_email'];

            unset($_SESSION['reg_full_name'], $_SESSION['reg_email'], $_SESSION['reg_phone'], $_SESSION['reg_password'], 
                  $_SESSION['reg_location'], $_SESSION['reg_role'], $_SESSION['reg_profile_picture'], 
                  $_SESSION['reg_category_id'], $_SESSION['reg_service_title'], $_SESSION['reg_service_desc'], 
                  $_SESSION['reg_id_number']);

            $_SESSION['user_id']   = $user_id;
            $_SESSION['full_name'] = $final_full_name;
            $_SESSION['email']     = $final_email;
            $_SESSION['role']      = 'provider';

            header('Location: /provider/dashboard.php');
            exit();
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – TaskLink SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body { margin:0; padding:0; min-height:100vh; background:#F7F8FA; }
        .register-wrapper { display: grid; grid-template-columns: 1fr 1.2fr; min-height: 100vh; }
        .reg-left { background-color: #1B6B3A; padding: 60px 50px; color: white; }
        .reg-right { padding: 60px 50px; overflow-y: auto; }
        .reg-form-box { max-width: 500px; margin: 0 auto; }
        .role-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }
        .role-option { border: 2px solid #E2E8F0; border-radius: 12px; padding: 16px; cursor: pointer; }
        .role-option.selected { border-color: #1B6B3A; background-color: #E8F5EE; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid .full-width { grid-column: 1 / -1; }
    </style>
</head>
<body>
<div class="register-wrapper">
    <div class="reg-left">
        <div>
            <a href="/index.php" class="logo" style="color:white; font-size:26px; font-weight:700;">TaskLink SA</a>
            <h1>Join TaskLink SA today</h1>
            <p>Create an account to start booking trusted services or offer your skills.</p>
        </div>
    </div>
    <div class="reg-right">
        <div class="reg-form-box">
            <?php if ($error): ?>
                <div class="alert-error" style="background-color: #FED7D7; border: 1px solid #E53E3E; color: #C53030; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <h2>Create Account</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($role); ?>">
                <div class="role-selector">
                    <div class="role-option <?php echo $role === 'client' ? 'selected' : ''; ?>" id="role-client" onclick="selectRole('client')">🔍 Find a Service</div>
                    <div class="role-option <?php echo $role === 'provider' ? 'selected' : ''; ?>" id="role-provider" onclick="selectRole('provider')">🛠️ Offer a Service</div>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone *</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Location</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                    </div>
                    <div class="form-group full-width">
                        <label id="avatarLabel">Profile Picture <?php echo $role === 'provider' ? '(Required) *' : '(Optional)'; ?></label>
                        <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" <?php echo $role === 'provider' ? 'required' : ''; ?>>
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; padding:14px; margin-top: 15px;">Create Account</button>
            </form>

            <?php elseif ($step === 2): ?>
            <h2>Service Details</h2>
            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="role" value="provider">
                <div class="form-group">
                    <label>ID Number *</label>
                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($_SESSION['reg_id_number'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Service Category *</label>
                    <select name="category_id" required>
                        <option value="">Select category...</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo (isset($_SESSION['reg_category_id']) && $_SESSION['reg_category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Service Title *</label>
                    <input type="text" name="service_title" value="<?php echo htmlspecialchars($_SESSION['reg_service_title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Service Description *</label>
                    <textarea name="service_desc" rows="4" required><?php echo htmlspecialchars($_SESSION['reg_service_desc'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex; gap:12px; margin-top:20px;">
                    <a href="/register.php?step=1" class="btn-secondary" style="flex:1; text-align:center; padding:14px;">← Back</a>
                    <button type="submit" class="btn-primary" style="flex:2;">Next →</button>
                </div>
            </form>

            <?php elseif ($step === 3): ?>
            <h2>Pricing & Terms</h2>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="step" value="3">
                <input type="hidden" name="role" value="provider">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Hourly Rate (R) *</label>
                        <input type="number" name="hourly_rate" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Callout Fee (R)</label>
                        <input type="number" name="callout_fee" min="0">
                    </div>
                    <div class="form-group full-width">
                        <label>Service Display Picture (Optional)</label>
                        <input type="file" name="service_picture" accept="image/*">
                    </div>
                    <div class="form-group full-width">
                        <label>Proof of Work Photos (Optional)</label>
                        <input type="file" name="proof_of_work[]" accept="image/*" multiple>
                    </div>
                </div>
                <div style="background:#F7F8FA; padding:16px; border-radius:10px; margin:20px 0; font-size:13px;">
                    <strong>Terms:</strong> TaskLink SA takes 10% commission. You receive 90%.
                </div>
                <div style="margin-bottom:12px;">
                    <label><input type="checkbox" name="agreed_terms" required> I agree to the Terms and Conditions</label>
                </div>
                <div style="display:flex; gap:12px;">
                    <a href="/register.php?step=2" class="btn-secondary" style="flex:1; text-align:center; padding:14px;">← Back</a>
                    <button type="submit" class="btn-primary" style="flex:2;">Complete Registration 🎉</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function selectRole(role) {
    document.getElementById('roleInput').value = role;
    document.getElementById('role-client').classList.remove('selected');
    document.getElementById('role-provider').classList.remove('selected');
    document.getElementById('role-' + role).classList.add('selected');
    
    const label = document.getElementById('avatarLabel');
    const input = document.getElementById('profilePictureInput');
    
    if (label && input) {
        if (role === 'provider') {
            label.innerText = "Profile Picture (Required) *";
            input.setAttribute('required', 'required');
        } else {
            label.innerText = "Profile Picture (Optional)";
            input.removeAttribute('required');
        }
    }
}
</script>
</body>
</html>