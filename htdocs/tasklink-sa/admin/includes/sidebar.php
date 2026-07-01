<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 36px; height: 36px; background: #1B6B3A; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <span style="color: white; font-size: 20px; font-weight: 700;">TL</span>
            </div>
            <div>
                <div style="font-size: 18px; font-weight: 700; color: white;">TaskLink SA</div>
                <div style="font-size: 11px; color: rgba(255,255,255,0.6);">Admin Panel</div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php" 
           class="sidebar-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
            <span class="icon">📊</span>
            <span>Dashboard</span>
        </a>

        <a href="/admin/users.php" 
           class="sidebar-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
            <span class="icon">👥</span>
            <span>Manage Users</span>
        </a>

        <a href="/admin/categories.php" 
           class="sidebar-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
            <span class="icon">📂</span>
            <span>Categories</span>
        </a>

        <a href="/admin/services.php" 
           class="sidebar-link <?php echo $current_page === 'services.php' ? 'active' : ''; ?>">
            <span class="icon">🛠️</span>
            <span>Services</span>
        </a>

        <a href="/admin/bookings.php" 
           class="sidebar-link <?php echo $current_page === 'bookings.php' ? 'active' : ''; ?>">
            <span class="icon">📅</span>
            <span>Bookings</span>
        </a>

        <a href="/admin/payments.php" 
           class="sidebar-link <?php echo $current_page === 'payments.php' ? 'active' : ''; ?>">
            <span class="icon">💳</span>
            <span>Payments</span>
        </a>

        <a href="/admin/moderation.php" 
           class="sidebar-link <?php echo $current_page === 'moderation.php' ? 'active' : ''; ?>">
            <span class="icon">🚨</span>
            <span>Moderation</span>
        </a>

        <!-- Messages -->
        <a href="/admin/messages.php" 
           class="sidebar-link <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>">
            <span class="icon">💬</span>
            <span>Messages</span>
        </a>
    </nav>

    <!-- Footer / Logout -->
    <div class="sidebar-footer">
        <a href="/logout.php" class="sidebar-link logout">
            <span class="icon"></span>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
    .admin-sidebar {
        width: 250px;
        min-height: 100vh;
        background: #0F4C2E;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 100;
        box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    }

    .sidebar-brand {
        padding: 28px 24px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-nav {
        padding: 16px 12px;
        flex: 1;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 13px 18px;
        margin: 4px 8px;
        font-size: 14.5px;
        font-weight: 500;
        color: rgba(255,255,255,0.75);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s ease;
    }

    .sidebar-link:hover {
        background: rgba(255,255,255,0.08);
        color: white;
    }

    .sidebar-link.active {
        background: #1B6B3A;
        color: white;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(27, 107, 58, 0.3);
    }

    .sidebar-link .icon {
        font-size: 18px;
        width: 24px;
        display: flex;
        justify-content: center;
    }

    .sidebar-footer {
        padding: 16px 12px;
        border-top: 1px solid rgba(255,255,255,0.1);
        margin-top: auto;
    }

    .sidebar-link.logout {
        color: #FCA5A5;
        font-weight: 500;
    }

    .sidebar-link.logout:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #FECACA;
    }
</style>