<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security verification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'provider') {
    header("Location: /login.php");
    exit();
}

require_once '../includes/db.php';

$provider_id = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Process state changes securely via POST variables
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id']) && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id']);
    $action = $_POST['action'];
    
    $verify_stmt = $pdo->prepare("
        SELECT b.booking_id FROM bookings b
        JOIN services s ON b.service_id = s.service_id
        WHERE b.booking_id = :booking_id AND s.provider_id = :provider_id
    ");
    $verify_stmt->execute(['booking_id' => $booking_id, 'provider_id' => $provider_id]);
    
    if ($verify_stmt->fetch()) {
        if ($action === 'accept') {
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'accepted' WHERE booking_id = :booking_id AND status = 'pending'");
            $update_stmt->execute(['booking_id' => $booking_id]);
            header("Location: provider-bookings.php?status=accepted&msg=accepted");
            exit();
        } elseif ($action === 'reject') {
            $update_stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE booking_id = :booking_id AND status = 'pending'");
            $update_stmt->execute(['booking_id' => $booking_id]);
            header("Location: provider-bookings.php?status=rejected&msg=rejected");
            exit();
        }
    }
}

// UPDATED QUERY: Added s.price so we can calculate the correct total
$query_string = "
    SELECT b.*, 
           s.title AS service_title, 
           s.price AS service_price,
           u.full_name AS client_name, 
           u.phone AS client_phone
    FROM bookings b
    JOIN services s ON b.service_id = s.service_id
    JOIN users u ON b.client_id = u.user_id
    WHERE s.provider_id = :provider_id
";

if ($status_filter !== 'all') {
    $query_string .= " AND b.status = :status";
}
$query_string .= " ORDER BY b.booking_date DESC, b.created_at DESC";

$stmt = $pdo->prepare($query_string);
$params = ['provider_id' => $provider_id];
if ($status_filter !== 'all') {
    $params['status'] = $status_filter;
}
$stmt->execute($params);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings — TaskLink SA Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F7F8FA;
            color: #1A202C;
            margin: 0;
            padding: 0;
        }
        .provider-nav {
            background-color: #1B6B3A;
            color: #FFFFFF;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .provider-nav a {
            color: #FFFFFF;
            text-decoration: none;
            font-weight: 500;
        }
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #E2E8F0;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            color: #4A5568;
            font-weight: 500;
            background-color: transparent;
            transition: all 0.2s;
        }
        .tab-btn.active {
            background-color: #1B6B3A;
            color: #FFFFFF;
        }
        .tab-btn:hover:not(.active) {
            background-color: #E8F5EE;
            color: #1B6B3A;
        }

        .cards {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .booking-card {
            background: #FFFFFF;
            border-radius: 10px;
            box-shadow: 0 6px 18px rgba(20, 40, 60, 0.06);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 1px solid #EEF2F4;
        }

        .booking-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .client {
            display:flex;
            flex-direction: column;
        }
        .client-name { font-size: 16px; color: #0b3b2e; }
        .client-contact { font-size: 13px; color: #6b7280; }

        .status-badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
            color: #fff;
            text-transform: capitalize;
        }
        .status-badge.pending { background: #f59e0b; }
        .status-badge.accepted { background: #10b981; }
        .status-badge.completed { background: #0ea5a4; }
        .status-badge.rejected { background: #ef4444; }

        .booking-body {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .service-info { flex: 1 1 60%; }
        .service-title { font-weight: 600; color: #111827; font-size: 15px; }
        .meta { margin-top: 6px; color: #6b7280; font-size: 13px; display:flex; gap:12px; flex-wrap:wrap; }

        .financial { flex: 0 0 220px; text-align: right; color: #111827; }
        .financial .amount { font-weight: 700; font-size: 16px; color: #0b3b2e; }
        .financial .payout { font-size: 12px; color: #22C55E; display:block; margin-top:6px; }

        .booking-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .action-flex { display:flex; gap:8px; align-items:center; }

        .btn {
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-accept { background: #10b981; color: #fff; }
        .btn-reject { background: #fff; color: #ef4444; border: 1px solid #ef4444; }
        .btn-more { background: #f3f4f6; color: #111827; border: 1px solid #e5e7eb; padding: 8px 10px; font-size:13px; }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #FFFFFF;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
        }
    </style>
</head>
<body>

    <nav class="provider-nav">
        <div style="display: flex; align-items: center; gap: 15px;">
            <span style="font-weight: 700; font-size: 18px; letter-spacing: -0.5px;">TaskLink SA</span>
            <span style="background-color: #134D2A; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">Provider Portal</span>
        </div>
        <div style="display: flex; gap: 20px; align-items: center;">
            <a href="/provider/dashboard.php">Dashboard</a>
            <a href="/messages.php">Messages</a>
            <a href="/logout.php" style="background-color: #EF4444; padding: 6px 12px; border-radius: 4px; font-size: 13px;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 style="margin: 0; font-size: 28px; color: #1B6B3A;">Incoming Job Orders</h1>
                <p style="margin: 5px 0 0 0; color: #4A5568;">Track, accept, and manage your local service requests.</p>
            </div>
        </div>

        <div class="tabs">
            <a href="provider-bookings.php?status=all" class="tab-btn <?php echo $status_filter === 'all' ? 'active' : ''; ?>">All Orders</a>
            <a href="provider-bookings.php?status=pending" class="tab-btn <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="provider-bookings.php?status=accepted" class="tab-btn <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>">Accepted</a>
            <a href="provider-bookings.php?status=completed" class="tab-btn <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">Completed</a>
            <a href="provider-bookings.php?status=rejected" class="tab-btn <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">Rejected</a>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <p style="font-size: 18px; color: #4A5568; margin-bottom: 10px;">No job records found.</p>
                <p style="color: #4A5568; margin-bottom: 20px;">When customers book your services from the main site, their orders will appear here.</p>
                <a href="/provider/dashboard.php" class="btn-more" style="text-decoration: none; display: inline-block;">Return to Dashboard</a>
            </div>
        <?php else: ?>
            <div class="cards" role="list">
                <?php foreach ($bookings as $job): 
                    $booking_id = htmlspecialchars($job['booking_id'] ?? '');
                    $client_name = htmlspecialchars($job['client_name'] ?? 'Unknown');
                    $client_phone = htmlspecialchars($job['client_phone'] ?? '');
                    $service_title = htmlspecialchars($job['service_title'] ?? '');
                    $booking_date = !empty($job['booking_date']) ? htmlspecialchars(date('d M Y', strtotime($job['booking_date']))) : '—';
                    $address = htmlspecialchars($job['address'] ?? '');
                    $estimated_hours = htmlspecialchars($job['estimated_hours'] ?? '0');
                    $status = htmlspecialchars($job['status'] ?? 'pending');

                    // === FIXED: Calculate total correctly ===
                    $service_price = floatval($job['service_price'] ?? 0);
                    $hours = floatval($job['estimated_hours'] ?? 0);
                    $total = $hours * $service_price;

                    $formattedTotal = 'R ' . number_format($total, 2, '.', ',');
                    $payout = $total * 0.90;
                    $formattedPayout = 'R ' . number_format($payout, 2, '.', ',');
                ?>
                    <article class="booking-card" data-id="<?php echo $booking_id; ?>" role="listitem">
                        <header class="booking-card-header">
                            <div class="client">
                                <strong class="client-name"><?php echo $client_name; ?></strong>
                                <div class="client-contact"><?php echo $client_phone; ?></div>
                            </div>
                            <div class="status-badge <?php echo strtolower($status); ?>">
                                <?php echo ucfirst($status); ?>
                            </div>
                        </header>

                        <div class="booking-body">
                            <div class="service-info">
                                <div class="service-title"><?php echo $service_title; ?></div>
                                <div class="meta">
                                    <span class="date"><?php echo $booking_date; ?></span>
                                    <span class="location"><?php echo $address; ?></span>
                                    <span class="hours"><?php echo $estimated_hours; ?> Hours</span>
                                </div>
                            </div>

                            <div class="financial" aria-label="Financial summary">
                                <div class="amount"><?php echo htmlspecialchars($formattedTotal, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="payout">Payout (90%): <?php echo htmlspecialchars($formattedPayout, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>

                        <footer class="booking-actions">
                            <div class="action-flex">
                                <?php if ($status === 'pending'): ?>
                                    <form action="provider-bookings.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-accept">Accept</button>
                                    </form>
                                    <form action="provider-bookings.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject</button>
                                    </form>
                                <?php elseif ($status === 'accepted'): ?>
                                    <span style="font-size: 13px; color: #4A5568; font-style: italic;">Awaiting customer payment</span>
                                <?php elseif ($status === 'completed'): ?>
                                    <span style="font-size: 13px; color: #22C55E; font-weight: 600;">Job Closed & Paid</span>
                                <?php else: ?>
                                    <span style="font-size: 13px; color: #4A5568;">No Actions Available</span>
                                <?php endif; ?>
                            </div>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form) return;
            const actionInput = form.querySelector('input[name="action"]');
            if (!actionInput) return;
            if (actionInput.value === 'reject') {
                if (!confirm('Reject this job order? This action can be reversed from Rejected tab.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>