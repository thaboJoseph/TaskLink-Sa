<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header('Location: /login.php');
    exit();
}

$preselect_provider_id = (int)($_GET['provider_id'] ?? 0);

// Get providers from user's bookings (completed, accepted, pending)
$stmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.full_name,
           s.title as service_title,
           b.status
    FROM bookings b
    JOIN users u ON b.provider_id = u.user_id
    JOIN services s ON b.service_id = s.service_id
    WHERE b.client_id = ?
    AND b.status IN ('accepted','completed','pending')
    ORDER BY b.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$providers = $stmt->fetchAll();

// If a provider_id was passed in the URL (from service detail page), make sure it's included
if ($preselect_provider_id > 0) {
    $already_exists = false;
    foreach ($providers as $p) {
        if ((int)$p['user_id'] === $preselect_provider_id) {
            $already_exists = true;
            break;
        }
    }

    if (!$already_exists) {
        // Fetch the provider the user clicked on
        $prov_stmt = $pdo->prepare("
            SELECT user_id, full_name 
            FROM users 
            WHERE user_id = ? AND role = 'provider'
        ");
        $prov_stmt->execute([$preselect_provider_id]);
        if ($direct = $prov_stmt->fetch()) {
            $direct['service_title'] = 'Viewed from profile';
            $direct['status'] = '';
            $providers[] = $direct;
        }
    }
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reported_user_id = (int)($_POST['reported_user_id'] ?? 0);
    $reason           = trim($_POST['reason'] ?? '');
    $description      = trim($_POST['description'] ?? '');

    if (!$reported_user_id) {
        $error = 'Please select a provider to report.';
    } elseif (empty($reason)) {
        $error = 'Please select a reason for reporting.';
    } elseif (empty($description)) {
        $error = 'Please describe why you are reporting this provider.';
    } elseif (strlen($description) < 20) {
        $error = 'Please provide more detail (at least 20 characters).';
    } else {
        // Handle evidence image upload
        $evidence_image = null;
        if (!empty($_FILES['evidence_image']['name'])) {
            $file = $_FILES['evidence_image'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowed) && $file['size'] <= 2 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/uploads/evidence/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $newName = 'evidence_' . time() . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
                        $evidence_image = 'uploads/evidence/' . $newName;
                    }
                }
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO user_reports 
            (reporter_id, reported_id, reason, description, evidence_image, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $reported_user_id,
            $reason,
            $description,
            $evidence_image
        ]);

        $success = 'Provider reported successfully. Our admin team will review your report.';
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:700px; margin:0 auto; padding:40px 24px;">

    <a href="/my-bookings.php"
       style="color:#1B6B3A; font-size:14px; font-weight:600; text-decoration:none; display:inline-block; margin-bottom:24px;">
        ← Back to My Bookings
    </a>

    <h1 style="font-size:24px; font-weight:700; color:#1A202C; margin-bottom:8px;">
        🚨 Report a Provider
    </h1>
    <p style="font-size:14px; color:#4A5568; margin-bottom:32px;">
        Report inappropriate behaviour or issues with a provider. All reports are reviewed by our admin team.
    </p>

    <?php if($success): ?>
        <div style="background:#D1FAE5; color:#065F46; padding:20px; border-radius:12px; text-align:center; margin-bottom:24px;">
            <p style="font-size:32px; margin-bottom:8px;">✅</p>
            <p style="font-size:16px; font-weight:700; margin-bottom:4px;">Provider Reported</p>
            <p style="font-size:13px;"><?php echo $success; ?></p>
            <a href="/my-bookings.php" class="btn-primary" style="display:inline-block; margin-top:16px; padding:10px 24px; font-size:14px;">
                Back to My Bookings
            </a>
        </div>
    <?php else: ?>

        <?php if($error): ?>
            <div class="alert-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if(empty($providers)): ?>
            <div class="card" style="text-align:center; padding:40px;">
                <p style="font-size:48px;">📋</p>
                <p style="font-size:16px; font-weight:600; color:#1A202C; margin-top:12px;">No providers to report</p>
                <p style="font-size:13px; color:#4A5568; margin-top:8px;">
                    You can only report providers you have booked or the one you are currently viewing.
                </p>
            </div>
        <?php else: ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="card" style="margin-bottom:20px;">

                    <div class="form-group">
                        <label>Select Provider to Report *</label>
                        <select name="reported_user_id" required style="width:100%; padding:14px; background:#F7F8FA; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px;">
                            <option value="">Choose a provider...</option>
                            <?php foreach($providers as $prov): ?>
                                <?php 
                                    $label = htmlspecialchars($prov['full_name']);
                                    if (!empty($prov['service_title'])) {
                                        $label .= " — " . htmlspecialchars($prov['service_title']);
                                        if (!empty($prov['status'])) {
                                            $label .= " (" . ucfirst($prov['status']) . ")";
                                        }
                                    }
                                ?>
                                <option value="<?php echo $prov['user_id']; ?>" 
                                    <?php echo ($preselect_provider_id == $prov['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p style="font-size:12px; color:#718096; margin-top:6px;">
                            Shows providers you have worked with + the profile you clicked Report on.
                        </p>
                    </div>

                    <div class="form-group">
                        <label>Reason for Report *</label>
                        <select name="reason" required style="width:100%; padding:14px; background:#F7F8FA; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px;">
                            <option value="">Select a reason...</option>
                            <option value="Inappropriate Username">Inappropriate Username</option>
                            <option value="False / Misleading Listing">False / Misleading Listing</option>
                            <option value="Inappropriate Behaviour">Inappropriate Behaviour</option>
                            <option value="Harassment">Harassment</option>
                            <option value="Fraudulent Activity">Fraudulent Activity</option>
                            <option value="Safety Concern">Safety Concern</option>
                            <option value="Did Not Complete Job Properly">Did Not Complete Job Properly</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Describe the Issue *</label>
                        <textarea name="description" rows="5" required placeholder="Please provide as much detail as possible..." style="width:100%; padding:14px; background:#F7F8FA; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; resize:vertical;"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <p style="font-size:11px; color:#A0AEC0; margin-top:4px;">Minimum 20 characters required</p>
                    </div>

                    <!-- Evidence Image Upload -->
                    <div class="form-group">
                        <label>Upload Evidence (Optional)</label>
                        <input type="file" name="evidence_image" accept="image/*">
                        <p style="font-size:11px; color:#718096; margin-top:4px;">Upload a photo as proof (JPG, PNG, WEBP — Max 2MB)</p>
                    </div>

                    <div style="background:#FEF3C7; border-radius:10px; padding:14px; margin-bottom:20px;">
                        <p style="font-size:12px; color:#92400E; line-height:1.6;">
                            ⚠️ <strong>Please note:</strong> False reports may result in your account being suspended. Only report genuine issues.
                        </p>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%; padding:14px; font-size:15px; background:#EF4444;">
                        🚨 Submit Report
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>