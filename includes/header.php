<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskLink SA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/tasklink-sa/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="/tasklink-sa/index.php" class="logo">TaskLink SA</a>

    <div class="nav-links">
        <a href="/tasklink-sa/index.php">Home</a>
        <a href="/tasklink-sa/browse.php">Browse</a>
        <a href="/tasklink-sa/about.php">How it works</a>
    </div>

    <div class="nav-actions">
        <?php if(isset($_SESSION['user_id'])): ?>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <a href="/tasklink-sa/logout.php" class="btn-secondary">Logout</a>
        <?php else: ?>
            <a href="/tasklink-sa/register.php" class="btn-secondary">Register</a>
            <a href="/tasklink-sa/login.php" class="btn-primary">Login</a>
        <?php endif; ?>
    </div>
</nav>