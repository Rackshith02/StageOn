<?php
/**
 * Project File Purpose:
 * - register.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_auth_helpers.php';
require_once __DIR__ . '/portal_workflow_helpers.php';

$errors = [];
ensureAdminAuthSchema($conn);
ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobileNumber = trim($_POST['mobile_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email.';
    }
    if (!preg_match('/^\+?[0-9]{10,15}$/', $mobileNumber)) {
        $errors[] = 'Mobile number must be 10 to 15 digits (optional + at the beginning).';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if (!$errors) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = 'Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $insert = $conn->prepare(
                "INSERT INTO users (username, email, mobile_number, password_hash, role)
                 VALUES (?, ?, ?, ?, 'user')"
            );
            $insert->bind_param('ssss', $username, $email, $mobileNumber, $hash);
            $insert->execute();
            $userId = (int) $insert->insert_id;
            logAuthEvent($conn, 'register', $userId, $username, $email);

            header('Location: login.php?registered=1');
            exit;
        }
    }
}
end_post:
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Register</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card" aria-labelledby="register-title">
      <img class="brand-logo" src="../assets/img/StageOnLogo.png" alt="StageOn">
      

      <h1 id="register-title" class="auth-title">Create Account</h1>
      <p class="auth-subtitle">Start your creative journey with StageOn</p>

      <?php if ($errors): ?>
        <div class="alert error" role="alert">
          <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="form" method="POST" onsubmit="return validateRegister(this)">
        <div class="field">
          <label for="username">Username</label>
          <input
            id="username"
            type="text"
            name="username"
            placeholder="Enter your username"
            required
            value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

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
          <label for="mobile_number">Mobile Number</label>
          <input
            id="mobile_number"
            type="tel"
            name="mobile_number"
            placeholder="Enter your mobile number"
            required
            pattern="^\+?[0-9]{10,15}$"
            value="<?= htmlspecialchars($_POST['mobile_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
          >
        </div>

        <div class="field">
          <label for="password">Password</label>
          <div class="password-wrap">
            <input id="password" type="password" name="password" placeholder="Create a password" required>
            <button class="toggle-pass" type="button" onclick="togglePassword('password', this)">Show</button>
          </div>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <div class="password-wrap">
            <input id="confirm_password" type="password" name="confirm_password" placeholder="Confirm your password" required>
            <button class="toggle-pass" type="button" onclick="togglePassword('confirm_password', this)">Show</button>
          </div>
        </div>

        <button class="btn" type="submit">Sign Up</button>

        <div class="divider">or continue with</div>

        <div class="oauth">
          <button type="button" title="Google UI only">
            <span class="oauth-mark">G</span>
            Google
          </button>
        </div>

        <div class="bottom-text">
          Already have an account?
          <a class="link" href="login.php">Sign in</a>
        </div>
      </form>
    </section>
  </main>

  <script src="../assets/js/auth.js"></script>
</body>
</html>


