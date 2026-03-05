<?php
/**
 * Project File Purpose:
 * - login.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_auth_helpers.php';
require_once __DIR__ . '/portal_workflow_helpers.php';

$errors = [];
$success = '';
ensureAdminAuthSchema($conn);
ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Account created successfully. You can now login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Invalid password length.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT user_id, username, password_hash, role FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id'] = (int) $row['user_id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['email'] = $email;
                $_SESSION['role'] = (string) ($row['role'] ?? 'user');
                logAuthEvent($conn, 'login', (int) $row['user_id'], (string) $row['username'], $email);

                if ($_SESSION['role'] === 'admin') {
                    header('Location: Admindashboard.php');
                } else {
                    header('Location: ../index.php');
                }
                exit;
            }
        }

        $errors[] = 'Login failed. Check your email and password.';
        $stmt->close();
    }
}
end_login_post:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Login</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card" aria-labelledby="login-title">
      <img class="brand-logo" src="../assets/img/StageOnLogo.png" alt="StageOn">

      <h1 id="login-title" class="auth-title">Welcome Back</h1>
      <p class="auth-subtitle">Enter your details to access your creative space</p>

      <?php if ($errors): ?>
        <div class="alert error" role="alert">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert success" role="status">
          <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form class="form" method="POST" onsubmit="return validateLogin(this)">
        <div class="field">
          <label for="email">E-mail</label>
          <input
            id="email"
            type="email"
            name="email"
            placeholder="Enter your email"
            required
            value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="password-wrap">
            <input id="password" type="password" name="password" placeholder="Enter your password" required>
            <button class="toggle-pass" type="button" onclick="togglePassword('password', this)">Show</button>
          </div>
        </div>

        <div class="row">
          <a class="link" href="#" title="Implement later">Forgot Password?</a>
        </div>

        <button class="btn" type="submit">Login</button>

        <div class="divider">or continue with</div>

        <div class="oauth">
          <button type="button" title="Google UI only">
            <span class="oauth-mark">G</span>
            Google
          </button>
        </div>

        <div class="bottom-text">
          Don&apos;t have an account?
          <a class="link" href="register.php">Sign up</a>
        </div>
      </form>
    </section>
  </main>

  <script src="../assets/js/auth.js"></script>
</body>
</html>


