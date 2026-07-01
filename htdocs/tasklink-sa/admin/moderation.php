<?php
$page_title = 'Moderation';
require_once '../includes/db.php';
require_once 'includes/header.php';

$success = '';
$error   = '';

// ====================== HANDLE REPORT ACTIONS ======================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $report_id = (int)$_GET['id'];
    $action    = $_GET['action'];

    if ($action === 'dismiss') {
        $pdo->prepare("UPDATE user_reports SET status='dismissed' WHERE report_id=?")->execute([$report_id]);
        $success = 'Report dismissed.';
    } 
    elseif ($action === 'reviewed') {
        $pdo->prepare("UPDATE user_reports SET status='reviewed' WHERE report_id=?")->execute([$report_id]);
        $success = 'Report marked as reviewed.';
    } 
    elseif ($action === 'suspend') {
        $reported_id = (int)$_GET['user_id'];

        try {
            $pdo->beginTransaction();

            // Clean up related data
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")
                ->execute([$reported_id, $reported_id]);

            $pdo->prepare("DELETE FROM reviews WHERE provider_id = ? OR client_id = ?")
                ->execute([$reported_id, $reported_id]);

            $pdo->prepare("DELETE FROM refund_requests WHERE client_id = ?")
                ->execute([$reported_id]);

            $pdo->prepare("DELETE FROM payments WHERE booking_id IN 
                (SELECT booking_id FROM bookings WHERE client_id = ? OR provider_id = ?)")
                ->execute([$reported_id, $reported_id]);

            $pdo->prepare("DELETE FROM bookings WHERE client_id = ? OR provider_id = ?")
                ->execute([$reported_id, $reported_id]);

            $pdo->prepare("DELETE FROM services WHERE provider_id = ?")
                ->execute([$reported_id]);

            $pdo->prepare("DELETE FROM provider_profiles WHERE provider_id = ?")
                ->execute([$reported_id]);

            $pdo->prepare("DELETE FROM user_reports WHERE reporter_id = ? OR reported_id = ?")
                ->execute([$reported_id, $reported_id]);

            // Finally delete the user
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$reported_id]);

            $pdo->commit();
            $success = 'User permanently removed from the system.';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Could not remove user. Error: ' . $e->getMessage();
        }
    }
}

// ====================== HANDLE REFUND ACTIONS ======================
if (isset($_GET['refund_action']) && isset($_GET['refund_id'])) {
    $refund_id = (int)$_GET['refund_id'];
    $action    = $_GET['refund_action'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE refund_requests SET status='approved' WHERE refund_id=?")->execute([$refund_id]);
        $success = 'Refund request approved.';
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE refund_requests SET status='rejected' WHERE refund_id=?")->execute([$refund_id]);
        $success = 'Refund request rejected.';
    }
}

$section = $_GET['section'] ?? 'reports';
$filter = $_GET['filter'] ?? 'pending';

// ====================== FETCH DATA ======================
$stmt = $pdo->prepare("
    SELECT ur.*,
           u1.full_name as reporter_name,
           u1.email as reporter_email,
           u2.full_name as reported_name,
           u2.email as reported_email,
           u2.role as reported_role,
           u2.user_id as reported_user_id
    FROM user_reports ur
    JOIN users u1 ON ur.reporter_id = u1.user_id
    JOIN users u2 ON ur.reported_id = u2.user_id
    WHERE ur.status = ?
    ORDER BY ur.created_at DESC
");
$stmt->execute([$filter]);
$reports = $stmt->fetchAll();

$refund_filter = $_GET['refund_filter'] ?? 'pending';
$refund_requests = $pdo->prepare("
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
    WHERE rr.status = ?
    ORDER BY rr.created_at DESC
");
$refund_requests->execute([$refund_filter]);
$refunds = $refund_requests->fetchAll();

$pending_reports  = $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status='pending'")->fetchColumn();
$reviewed_count   = $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status='reviewed'")->fetchColumn();
$dismissed_count  = $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status='dismissed'")->fetchColumn();
$pending_refunds  = $pdo->query("SELECT COUNT(*) FROM refund_requests WHERE status='pending'")->fetchColumn();
$low_reviews      = $pdo->query("SELECT COUNT(*) FROM reviews WHERE rating <= 2")->fetchColumn();
?>

<?php if($success): ?>
    <div class="alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:32px;">
    <div class="stat-card" style="border-left:4px solid #EF4444;">
        <div class="stat-label">Pending Reports</div>
        <div class="stat-value" style="color:#EF4444;"><?php echo $pending_reports; ?></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #1B6B3A;">
        <div class="stat-label">Reviewed</div>
        <div class="stat-value" style="color:#1B6B3A;"><?php echo $reviewed_count; ?></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #A0AEC0;">
        <div class="stat-label">Dismissed</div>
        <div class="stat-value" style="color:#A0AEC0;"><?php echo $dismissed_count; ?></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #F5A623;">
        <div class="stat-label">Refund Requests</div>
        <div class="stat-value" style="color:<?php echo $pending_refunds > 0 ? '#F5A623' : '#A0AEC0'; ?>;"><?php echo $pending_refunds; ?></div>
    </div>
    <div class="stat-card" style="border-left:4px solid #718096;">
        <div class="stat-label">Low Reviews</div>
        <div class="stat-value" style="color:#718096;"><?php echo $low_reviews; ?></div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex; gap:8px; margin-bottom:24px; border-bottom:2px solid #E2E8F0; padding-bottom:0;">
    <a href="?section=reports" style="padding:10px 20px; font-size:13px; font-weight:600; text-decoration:none; border-radius:8px 8px 0 0; <?php echo $section==='reports' ? 'background:#1B6B3A;color:white;' : 'color:#4A5568;background:#F0F0F0;'; ?>">🚨 User Reports (<?php echo $pending_reports; ?>)</a>
    <a href="?section=refunds" style="padding:10px 20px; font-size:13px; font-weight:600; text-decoration:none; border-radius:8px 8px 0 0; <?php echo $section==='refunds' ? 'background:#1B6B3A;color:white;' : 'color:#4A5568;background:#F0F0F0;'; ?>">🔄 Refund Requests (<?php echo $pending_refunds; ?>)</a>
    <a href="?section=reviews" style="padding:10px 20px; font-size:13px; font-weight:600; text-decoration:none; border-radius:8px 8px 0 0; <?php echo $section==='reviews' ? 'background:#1B6B3A;color:white;' : 'color:#4A5568;background:#F0F0F0;'; ?>">⭐ Low Reviews (<?php echo $low_reviews; ?>)</a>
</div>

<!-- REPORTS SECTION -->
<?php if($section === 'reports'): ?>
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>Reported Users</h2>
        <div style="display:flex; gap:8px;">
            <a href="?section=reports&filter=pending" class="btn-sm" style="<?php echo $filter==='pending' ? 'background:#1B6B3A;color:white;' : ''; ?>">Pending</a>
            <a href="?section=reports&filter=reviewed" class="btn-sm" style="<?php echo $filter==='reviewed' ? 'background:#1B6B3A;color:white;' : ''; ?>">Reviewed</a>
            <a href="?section=reports&filter=dismissed" class="btn-sm" style="<?php echo $filter==='dismissed' ? 'background:#1B6B3A;color:white;' : ''; ?>">Dismissed</a>
        </div>
    </div>

    <?php if(empty($reports)): ?>
        <div class="empty-state"><p>🚨</p><p>No reports found</p></div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reported User</th>
                <th>Reported By</th>
                <th>Reason</th>
                <th>Description</th>
                <th>Evidence</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reports as $r): ?>
            <tr>
                <td style="font-size:12px; color:#718096;"><?php echo date('d M Y', strtotime($r['created_at'])); ?></td>
                <td>
                    <p style="font-weight:600; font-size:13px;"><?php echo htmlspecialchars($r['reported_name']); ?></p>
                    <p style="font-size:11px; color:#718096;"><?php echo htmlspecialchars($r['reported_email']); ?></p>
                    <span class="role-badge role-<?php echo $r['reported_role']; ?>"><?php echo ucfirst($r['reported_role']); ?></span>
                </td>
                <td style="color:#718096; font-size:13px;"><?php echo htmlspecialchars($r['reporter_name']); ?></td>
                <td>
                    <span style="background:#FEE2E2; color:#991B1B; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:600;">
                        <?php echo htmlspecialchars($r['reason']); ?>
                    </span>
                </td>
                <td style="font-size:12px; color:#718096; max-width:200px;">
                    <?php echo htmlspecialchars(substr($r['description'], 0, 80)); ?><?php echo strlen($r['description']) > 80 ? '...' : ''; ?>
                </td>
                <td>
                    <?php if (!empty($r['evidence_image'])): ?>
                        <img src="/<?php echo htmlspecialchars($r['evidence_image']); ?>" 
                             onclick="showEvidence('/<?php echo htmlspecialchars($r['evidence_image']); ?>')"
                             style="max-width:70px; max-height:50px; border-radius:6px; border:1px solid #ddd; object-fit:cover; cursor:pointer;">
                    <?php else: ?>
                        <span style="color:#A0AEC0; font-size:12px;">No image</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <?php if($r['status'] === 'pending'): ?>
                            <a href="?section=reports&action=reviewed&id=<?php echo $r['report_id']; ?>&filter=pending" class="btn-sm btn-green">✓ Reviewed</a>
                            <a href="?section=reports&action=suspend&id=<?php echo $r['report_id']; ?>&user_id=<?php echo $r['reported_user_id']; ?>&filter=pending" 
                               class="btn-sm btn-danger" 
                               onclick="return confirm('Permanently remove this user and all their data? This cannot be undone.')">
                                Remove User
                            </a>
                            <a href="?section=reports&action=dismiss&id=<?php echo $r['report_id']; ?>&filter=pending" class="btn-sm" style="background:#F0F0F0; color:#718096;">Dismiss</a>
                        <?php else: ?>
                            <span style="font-size:11px; color:#A0AEC0;"><?php echo ucfirst($r['status']); ?></span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif($section === 'refunds'): ?>
<!-- REFUND REQUESTS SECTION (same as before, unchanged) -->
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>Refund Requests</h2>
    </div>
    <!-- ... (keep your existing refund table code here) -->
</div>

<?php elseif($section === 'reviews'): ?>
<div class="admin-table-wrap">
    <div class="admin-table-header">
        <h2>Low Rated Reviews (1–2 stars)</h2>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox Modal -->
<div id="evidenceModal" onclick="closeEvidenceModal()" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; align-items:center; justify-content:center;">
    <div onclick="event.stopImmediatePropagation()" style="max-width:90%; max-height:90%; position:relative;">
        <img id="evidenceImage" style="max-width:100%; max-height:85vh; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.5);">
        <button onclick="closeEvidenceModal()" style="position:absolute; top:-15px; right:-15px; background:#1B6B3A; color:white; border:none; width:40px; height:40px; border-radius:50%; font-size:22px; cursor:pointer;">×</button>
    </div>
</div>

<script>
function showEvidence(imageSrc) {
    const modal = document.getElementById('evidenceModal');
    const img = document.getElementById('evidenceImage');
    img.src = imageSrc;
    modal.style.display = 'flex';
}

function closeEvidenceModal() {
    document.getElementById('evidenceModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === "Escape") closeEvidenceModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>