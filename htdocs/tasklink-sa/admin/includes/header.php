<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect all admin pages
if (!isset($_SESSION['user_id']) || 
    $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" 
          content="width=device-width, initial-scale=1.0">
    <title>Admin Panel – TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" 
          rel="stylesheet">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; font-family:'Inter',sans-serif; }
        body { background:#F7F8FA; color:#1A202C; min-height:100vh; display:flex; }

        /* Sidebar */
        .admin-sidebar {
            width: 240px;
            min-height: 100vh;
            background: #0b6013;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
            font-weight: 700;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-nav {
            padding: 16px 0;
            flex: 1;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.6);
            text-decoration: none;
            transition: all 0.2s;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.06);
            color: white;
        }
        .sidebar-link.active {
            background: #1B6B3A;
            color: white;
            font-weight: 600;
        }
        .sidebar-footer {
            padding: 16px 0;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        /* Main content */
        .admin-main {
            margin-left: 240px;
            flex: 1;
            min-height: 100vh;
        }

        /* Top bar */
        .admin-topbar {
            background: white;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #E2E8F0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .admin-topbar h1 {
            font-size: 18px;
            font-weight: 700;
            color: #1A202C;
        }
        .admin-topbar .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #4A5568;
        }
        .admin-avatar {
            width: 34px;
            height: 34px;
            background: #1B6B3A;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 13px;
        }

        /* Content area */
        .admin-content {
            padding: 32px;
        }

        /* Stat cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .stat-card .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #A0AEC0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1A202C;
        }
        .stat-card .stat-accent {
            width: 4px;
            height: 40px;
            border-radius: 2px;
            float: right;
            margin-top: -36px;
        }

        /* Tables */
        .admin-table-wrap {
            background: white;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .admin-table-header {
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #E2E8F0;
        }
        .admin-table-header h2 {
            font-size: 15px;
            font-weight: 700;
            color: #1A202C;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #F7F8FA;
        }
        th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }
        td {
            padding: 14px 16px;
            font-size: 13px;
            color: #2D3748;
            border-bottom: 1px solid #F0F0F0;
            vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #FAFAFA; }

        /* Badges */
        .role-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .role-client   { background:#EBF8FF; color:#2B6CB0; }
        .role-provider { background:#E8F5EE; color:#1B6B3A; }
        .role-admin    { background:#EDF2F7; color:#4A5568; }

        .status-badge {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending   { background:#FEF3C7; color:#D97706; }
        .status-accepted  { background:#D1FAE5; color:#065F46; }
        .status-completed { background:#DBEAFE; color:#1E40AF; }
        .status-rejected  { background:#FEE2E2; color:#991B1B; }
        .status-paid      { background:#D1FAE5; color:#065F46; }

        /* Buttons */
        .btn-sm {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            border: none;
        }
        .btn-danger { background:#FEE2E2; color:#991B1B; }
        .btn-danger:hover { background:#FECACA; }
        .btn-green { background:#E8F5EE; color:#1B6B3A; }
        .btn-green:hover { background:#C6F6D5; }
        .btn-primary {
            background:#1B6B3A; color:white;
            padding:10px 20px; border-radius:8px;
            font-size:13px; font-weight:600;
            text-decoration:none; display:inline-block;
            border:none; cursor:pointer;
        }
        .btn-primary:hover { background:#134D2A; }

        /* Search bar */
        .search-bar {
            padding: 8px 14px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            width: 240px;
        }
        .search-bar:focus { border-color: #1B6B3A; }

        /* Form groups */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4A5568;
            margin-bottom: 6px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 13px;
            background: #F7F8FA;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: #1B6B3A;
            background: white;
        }

        .alert-success {
            background:#D1FAE5; color:#065F46;
            padding:12px 16px; border-radius:8px;
            font-size:13px; margin-bottom:16px;
        }
        .alert-error {
            background:#FEE2E2; color:#991B1B;
            padding:12px 16px; border-radius:8px;
            font-size:13px; margin-bottom:16px;
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #A0AEC0;
        }
        .empty-state p:first-child { font-size: 40px; }
        .empty-state p:last-child { font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/sidebar.php'; ?>
<div class="admin-main">
<div class="admin-topbar">
    <h1><?php echo $page_title ?? 'Admin Panel'; ?></h1>
    <div class="admin-info">
        <span>Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['full_name'])[0]); ?></span>
        <div class="admin-avatar">
            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
        </div>
    </div>
</div>
<div class="admin-content">