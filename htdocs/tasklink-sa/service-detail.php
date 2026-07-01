<?php
session_start();
require_once 'includes/db.php';

$service_id = (int)($_GET['id'] ?? 0);

if (!$service_id) {
    header('Location: /browse.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT s.*, c.category_name,
           u.full_name as provider_name,
           u.user_id as provider_user_id,
           u.profile_picture as provider_profile_picture,
           COALESCE(p.bio, '') as provider_bio,
           COALESCE(p.location, '') as provider_location,
           COALESCE(p.rating, 0) as provider_rating,
           COALESCE(p.total_reviews, 0) as total_reviews,
           COALESCE(s.service_picture, p.service_picture, '') as service_picture
    FROM services s
    JOIN categories c ON s.category_id = c.category_id
    JOIN users u ON s.provider_id = u.user_id
    LEFT JOIN provider_profiles p 
        ON s.provider_id = p.provider_id
    WHERE s.service_id = ?
");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: /browse.php');
    exit();
}

$stmt = $pdo->prepare("
    SELECT r.*, u.full_name as client_name
    FROM reviews r
    JOIN users u ON r.client_id = u.user_id
    WHERE r.provider_id = ?
    ORDER BY r.review_date DESC
    LIMIT 5
");
$stmt->execute([$service['provider_user_id']]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT proof_of_work FROM provider_profiles WHERE provider_id = ?");
$stmt->execute([$service['provider_user_id']]);
$proofJson = $stmt->fetchColumn();
$proof_images = $proofJson ? json_decode($proofJson, true) : [];
?>
<?php require_once 'includes/header.php'; ?>

<div style="max-width:1100px; margin:0 auto; padding:40px 24px;">

    <a href="/browse.php"
       style="color:#1B6B3A; font-size:14px; font-weight:600; text-decoration:none; display:inline-block; margin-bottom:24px;">
        ← Back to Browse
    </a>

    <div style="display:grid; grid-template-columns:1fr 340px; gap:32px; align-items:start;">

        <!-- LEFT COLUMN -->
        <div>

            <!-- HERO IMAGE -->
            <div style="width:100%; height:320px; background:#E8F5EE; border-radius:16px; overflow:hidden; margin-bottom:24px; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <?php if (!empty($service['service_picture'])): 
                    $fullPath = __DIR__ . '/' . $service['service_picture'];
                ?>
                    <?php if (file_exists($fullPath)): ?>
                        <img src="/<?php echo htmlspecialchars($service['service_picture']); ?>" 
                             style="width:100%; height:100%; object-fit:cover; display:block;"
                             alt="<?php echo htmlspecialchars($service['title']); ?>">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #E8F5EE, #C6E8D5);">
                            <span style="font-size:80px; color:#1B6B3A;">🛠️</span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #E8F5EE, #C6E8D5);">
                        <span style="font-size:80px; color:#1B6B3A;">🛠️</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Provider Info Card -->
            <div class="card" style="margin-bottom:24px; display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                
                <div style="width:64px; height:64px; border-radius:50%; overflow:hidden; background:#1B6B3A; flex-shrink:0; border:2px solid #1B6B3A;">
                    <?php if (!empty($service['provider_profile_picture']) && file_exists(__DIR__ . '/' . $service['provider_profile_picture'])): ?>
                        <img src="/<?php echo htmlspecialchars($service['provider_profile_picture']); ?>" 
                             style="width:100%; height:100%; object-fit:cover; display:block;">
                    <?php else: ?>
                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:24px; color:white; font-weight:700;">
                            <?php echo strtoupper(substr($service['provider_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="flex:1;">
                    <h3 style="font-size:17px; font-weight:700; color:#1A202C; margin-bottom:4px;">
                        <?php echo htmlspecialchars($service['provider_name']); ?>
                    </h3>
                    <p style="font-size:13px; color:#4A5568; margin-bottom:4px;">
                        <?php echo htmlspecialchars($service['category_name']); ?> specialist
                        <?php if($service['provider_location']): ?>
                            • <?php echo htmlspecialchars($service['provider_location']); ?>
                        <?php endif; ?>
                    </p>
                    <p style="font-size:13px; color:#F5A623; font-weight:600;">
                        ⭐ <?php echo number_format($service['provider_rating'], 1); ?>
                        <span style="color:#A0AEC0; font-weight:400;">
                            (<?php echo $service['total_reviews']; ?> reviews)
                        </span>
                    </p>
                </div>
                
                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'client'): ?>
                    <a href="/messages.php?chat_with=<?php echo $service['provider_user_id']; ?>"
                       class="btn-secondary"
                       style="padding:10px 20px;">
                    Message
                    </a>
                    <a href="/report-provider.php?provider_id=<?php echo $service['provider_user_id']; ?>"
                       style="padding:10px 20px; background:#FEE2E2; color:#991B1B; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; display:inline-block;">
                       Report
                    </a>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div class="card" style="margin-bottom:24px;">
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px; flex-wrap:wrap;">
                    <h2 style="font-size:22px; font-weight:700; color:#1A202C;">
                        <?php echo htmlspecialchars($service['title']); ?>
                    </h2>
                    <span class="badge">
                        <?php echo htmlspecialchars($service['category_name']); ?>
                    </span>
                </div>
                <p style="font-size:14px; color:#4A5568; line-height:1.7; margin-bottom:16px;">
                    <?php echo htmlspecialchars($service['description']); ?>
                </p>
                <p style="font-size:24px; font-weight:700; color:#1B6B3A;">
                    R<?php echo number_format($service['price'], 2); ?>
                    <span style="font-size:14px; font-weight:400; color:#4A5568;">
                        <?php echo $service['price_type'] === 'hourly' ? '/ hour' : ' fixed price'; ?>
                    </span>
                </p>
            </div>

            <!-- Reviews -->
            <div class="card" style="margin-bottom:24px;">
                <h3 style="font-size:17px; font-weight:700; color:#1A202C; margin-bottom:20px;">
                    Client Reviews
                    <span style="font-size:13px; font-weight:400; color:#A0AEC0;">
                        (<?php echo $service['total_reviews']; ?>)
                    </span>
                </h3>

                <?php if(empty($reviews)): ?>
                    <p style="color:#A0AEC0; font-size:14px; text-align:center; padding:20px 0;">
                        No reviews yet — be the first to book!
                    </p>
                <?php else: ?>
                    <?php foreach($reviews as $review): ?>
                    <div style="padding:16px 0; border-bottom:1px solid #F0F0F0;">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
                            <div style="width:36px; height:36px; background:#E8F5EE; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#1B6B3A;">
                                <?php echo strtoupper(substr($review['client_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <p style="font-size:13px; font-weight:600; color:#1A202C;">
                                    <?php echo htmlspecialchars($review['client_name']); ?>
                                </p>
                                <p style="font-size:12px; color:#F5A623;">
                                    <?php echo str_repeat('⭐', $review['rating']); ?>
                                </p>
                            </div>
                        </div>
                        <p style="font-size:13px; color:#4A5568; line-height:1.6;">
                            <?php echo htmlspecialchars($review['comment']); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- PREVIOUS WORK -->
            <div class="card">
                <h3 style="font-size:17px; font-weight:700; color:#1A202C; margin-bottom:16px;">
                    Previous Work
                </h3>
                
                <?php if (!empty($proof_images)): ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px, 1fr)); gap:12px;">
                        <?php foreach($proof_images as $img): ?>
                            <div onclick="openLightbox('/<?php echo htmlspecialchars($img); ?>')" 
                                 style="height:130px; border-radius:12px; overflow:hidden; border:1px solid #E2E8F0; cursor:pointer; position:relative; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
                                <img src="/<?php echo htmlspecialchars($img); ?>" 
                                     style="width:100%; height:100%; object-fit:cover; display:block;"
                                     alt="Previous work">
                                <div style="position:absolute; bottom:6px; right:6px; background:rgba(0,0,0,0.6); color:white; font-size:10px; padding:2px 8px; border-radius:6px;">
                                    Click to enlarge
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="font-size:12px; color:#718096; margin-top:10px; text-align:center;">
                        Click any photo to view it larger
                    </p>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:12px;">
                        <?php for($i = 0; $i < 3; $i++): ?>
                        <div style="height:130px; background:linear-gradient(135deg, #E8F5EE, #C6E8D5); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:40px; border:1px solid #E2E8F0;">
                            🛠️
                        </div>
                        <?php endfor; ?>
                    </div>
                    <p style="font-size:13px; color:#A0AEC0; margin-top:12px; text-align:center;">
                        This provider hasn't uploaded any previous work photos yet.
                    </p>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT SIDEBAR -->
        <div style="position:sticky; top:84px;">
            <div class="card">
                <h3 style="font-size:18px; font-weight:700; color:#1A202C; margin-bottom:4px;">
                    <?php echo htmlspecialchars($service['title']); ?>
                </h3>
                <span class="badge" style="margin-bottom:16px; display:inline-block;">
                    <?php echo htmlspecialchars($service['category_name']); ?>
                </span>

                <p style="font-size:28px; font-weight:700; color:#1B6B3A; margin:12px 0 4px;">
                    R<?php echo number_format($service['price'], 2); ?>
                </p>
                <p style="font-size:13px; color:#4A5568; margin-bottom:20px;">
                    <?php echo $service['price_type'] === 'hourly' ? 'per hour' : 'fixed price'; ?>
                </p>

                <div class="form-group">
                    <label>Est. Hours</label>
                    <input type="number" id="estimatedHours" placeholder="e.g. 3" min="1" max="24">
                    <span id="serviceRate" data-rate="<?php echo $service['price']; ?>" style="display:none;"></span>
                </div>

                <div style="background:#F7F8FA; border-radius:10px; padding:14px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:#4A5568; margin-bottom:6px;">
                        <span>Rate:</span>
                        <span>
                            R<?php echo number_format($service['price'], 2); ?>
                            <?php echo $service['price_type'] === 'hourly' ? '/hr' : ' fixed'; ?>
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px; color:#4A5568; margin-bottom:6px;">
                        <span>Hours:</span>
                        <span id="hoursDisplay">-</span>
                    </div>
                    <div style="border-top:1px solid #E2E8F0; padding-top:10px; margin-top:6px; display:flex; justify-content:space-between;">
                        <span style="font-weight:700; color:#1A202C;">
                            Total Estimate:
                        </span>
                        <span id="totalEstimate" style="font-weight:700; color:#1B6B3A;">
                            R0
                        </span>
                    </div>
                </div>

                <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="/login.php" class="btn-primary" style="width:100%; padding:16px; text-align:center; font-size:16px; display:block; margin-bottom:10px;">
                        Login to Book
                    </a>
                    <p style="text-align:center; font-size:12px; color:#A0AEC0;">
                        Don't have an account?
                        <a href="/register.php" style="color:#1B6B3A;">
                            Register here
                        </a>
                    </p>

                <?php elseif($_SESSION['role'] === 'client'): ?>
                    <a href="/book.php?id=<?php echo $service['service_id']; ?>" class="btn-primary" style="width:100%; padding:16px; text-align:center; font-size:16px; display:block; margin-bottom:10px;">
                        Book Now 🚀
                    </a>

                <?php elseif($_SESSION['role'] === 'provider'): ?>
                    <div style="background:#FEF3C7; border-radius:10px; padding:14px; text-align:center; font-size:13px; color:#92400E;">
                        ⚠️ Providers cannot book services. Switch to a client account to book.
                    </div>

                <?php elseif($_SESSION['role'] === 'admin'): ?>
                    <div style="background:#EEF2FF; border-radius:10px; padding:14px; text-align:center; font-size:13px; color:#3730A3;">
                        ℹ️ Admins cannot book services.
                    </div>
                <?php endif; ?>

                <p style="text-align:center; font-size:11px; color:#A0AEC0; margin-top:12px;">
                    💳 Payment processed only after job completion
                </p>

            </div>
        </div>

    </div>
</div>

<!-- LIGHTBOX MODAL -->
<div id="lightboxModal" onclick="closeLightbox()" 
     style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center;">
    <div onclick="event.stopImmediatePropagation()" style="max-width:90%; max-height:90%; position:relative;">
        <img id="lightboxImage" style="max-width:100%; max-height:85vh; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
        <button onclick="closeLightbox()" 
                style="position:absolute; top:-15px; right:-15px; background:#1B6B3A; color:white; border:none; width:40px; height:40px; border-radius:50%; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
            ×
        </button>
    </div>
</div>

<script>
const hoursInput = document.getElementById('estimatedHours');
const totalDisplay = document.getElementById('totalEstimate');
const hoursDisplay = document.getElementById('hoursDisplay');
const rate = parseFloat(
    document.getElementById('serviceRate').getAttribute('data-rate')
) || 0;

if (hoursInput) {
    hoursInput.addEventListener('input', function() {
        const hours = parseFloat(this.value) || 0;
        const total = hours * rate;
        totalDisplay.textContent = 'R' + total.toLocaleString();
        hoursDisplay.textContent = hours + ' hrs';
    });
}

function openLightbox(imageSrc) {
    const modal = document.getElementById('lightboxModal');
    const img = document.getElementById('lightboxImage');
    img.src = imageSrc;
    modal.style.display = 'flex';
}

function closeLightbox() {
    const modal = document.getElementById('lightboxModal');
    modal.style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") {
        closeLightbox();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>