<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
    } elseif ($_SESSION['role'] === 'provider') {
        header('Location: /provider/dashboard.php');
    } else {
        header('Location: /index.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: /admin/dashboard.php');
            } elseif ($user['role'] === 'provider') {
                header('Location: /provider/dashboard.php');
            } else {
                header('Location: /index.php');
            }
            exit();
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – TaskLink SA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* LEFT PANEL */
        .login-left {
            background: linear-gradient(135deg, #1B6B3A 0%, #14532D 100%);
            color: white;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-left .logo {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 50px;
        }

        .login-left h1 {
            font-size: 40px;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 18px;
        }

        .login-left p {
            font-size: 16px;
            color: rgba(255,255,255,0.85);
            max-width: 400px;
            line-height: 1.6;
        }

        /* Service Images - Symmetrical & Pretty */
        .services-section {
            margin-top: 60px;
        }

        .services-section h4 {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,0.75);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .service-card {
            text-align: center;
        }

        .service-card img {
            width: 100%;
            height: 130px;
            object-fit: cover;
            border-radius: 14px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .service-card img:hover {
            transform: scale(1.03);
        }

        .service-card span {
            display: block;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
        }

        /* RIGHT PANEL */
        .login-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 30px;
            background: #F8FAFC;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .login-box h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1A202C;
            margin-bottom: 8px;
        }

        .login-box .subtitle {
            color: #64748B;
            margin-bottom: 28px;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 7px;
        }

        .form-group input {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 15px;
        }

        .form-group input:focus {
            border-color: #1B6B3A;
            outline: none;
            box-shadow: 0 0 0 3px rgba(27, 107, 58, 0.1);
        }

        .btn-primary {
            width: 100%;
            padding: 15px;
            background: #1B6B3A;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: #14532D;
        }

        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #FECACA;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 26px 0;
            color: #94A3B8;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #E2E8F0;
        }

        .divider span {
            padding: 0 16px;
        }

        .register-link {
            text-align: center;
            font-size: 14px;
            color: #64748B;
        }

        .register-link a {
            color: #1B6B3A;
            font-weight: 600;
            text-decoration: none;
        }

        @media (max-width: 900px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            .login-left {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">

    <!-- LEFT PANEL -->
    <div class="login-left">
        <div>
            <div class="logo">TaskLink SA</div>
            <h1>Find trusted help<br>near you</h1>
            <p>Connect with verified service providers in your area. Book, pay, and review — all in one place.</p>
        </div>

        <!-- Service Images Section -->
        <div class="services-section">
            <div class="service-grid">
                <div class="service-card">
                    <img src="https://images.unsplash.com/photo-1581578731548-c64695cc6952?w=400&q=80" alt="House Cleaning">
                    <span>House Cleaning</span>
                </div>
                <div class="service-card">
                    <img src="https://images.unsplash.com/photo-1621905251918-48416bd8575a?w=400&q=80" alt="Electricians">
                    <span>Electricians</span>
                </div>
                <div class="service-card">
                    <img src="https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80" alt="Gardening">
                    <span>Gardening</span>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="login-right">
        <div class="login-box">

            <h2>Welcome back 👋</h2>
            <p class="subtitle">Login to your TaskLink SA account</p>

            <?php if ($error): ?>
                <div class="alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>
            </form>

            <div class="divider">
                <span>or</span>
            </div>

            <p class="register-link">
                Don't have an account? 
                <a href="/register.php">Create one here</a>
            </p>

        </div>
    </div>

</div>

</body>
</html>