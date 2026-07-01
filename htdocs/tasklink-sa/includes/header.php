<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/index.php" class="logo">
        TaskLink SA
    </a>

    <div class="nav-links">
        <a href="/index.php">Home</a>
        <a href="/browse.php">Browse</a>
    </div>

    <div class="nav-actions">
        <?php if(isset($_SESSION['user_id'])): ?>

            <?php if($_SESSION['role'] === 'client'): ?>
                <a href="/my-bookings.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px;">
                    📅 My Bookings
                </a>
                <a href="/client/profile.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px;">
                    👤 Profile
                </a>
                <a href="/messages.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px;">
                    💬 Messages
                </a>

            <?php elseif($_SESSION['role'] === 'provider'): ?>
                <a href="/provider/dashboard.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px;">
                    🏠 Dashboard
                </a>
                <a href="/provider/report-user.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px; 
                          color:#EF4444;">
                    🚨 Report User
                </a>

            <?php elseif($_SESSION['role'] === 'admin'): ?>
                <a href="/admin/dashboard.php"
                   class="btn-secondary"
                   style="padding:8px 16px; font-size:13px;">
                    ⚙️ Admin Panel
                </a>
            <?php endif; ?>

            <span style="font-size:13px; color:#4A5568; 
                         padding:0 4px;">
                Hi, <?php echo htmlspecialchars(
                    explode(' ', $_SESSION['full_name'])[0]
                ); ?>!
            </span>

            <a href="/logout.php" 
               class="btn-primary"
               style="padding:8px 16px; font-size:13px;">
                Logout
            </a>

        <?php else: ?>
            <a href="/register.php" 
               class="btn-secondary"
               style="padding:8px 16px; font-size:13px;">
                Register
            </a>
            <a href="/login.php" 
               class="btn-primary"
               style="padding:8px 16px; font-size:13px;">
                Login
            </a>
        <?php endif; ?>
    </div>
</nav>