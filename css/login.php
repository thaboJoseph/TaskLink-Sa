<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {

        // Find user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Set session variables
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($user['role'] === 'provider') {
                header('Location: provider/dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit();

        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="auth-container">
    <div class="card auth-card">

        <h2>Welcome back 👋</h2>
        <p>Login to your TaskLink SA account</p>

        <?php if ($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email"
                       placeholder="you@example.com" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password"
                       placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-primary"
                    style="width:100%">Login</button>

        </form>

        <p>Don't have an account?
           <a href="register.php">Register here</a>
        </p>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>