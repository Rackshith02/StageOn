<?php
/**
 * Project File Purpose:
 * - manage_profile.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_auth_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ensureAdminAuthSchema($conn);

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';

$stmt = $conn->prepare('SELECT username, email, mobile_number FROM users WHERE user_id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: ['username' => '', 'email' => '', 'mobile_number' => ''];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $mobileNumber = trim((string) ($_POST['mobile_number'] ?? ''));

    if (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }
    if ($mobileNumber !== '' && !preg_match('/^\+?[0-9]{10,15}$/', $mobileNumber)) {
        $errors[] = 'Mobile number must be 10 to 15 digits (optional + at start).';
    }

    if (!$errors) {
        $update = $conn->prepare('UPDATE users SET username = ?, mobile_number = ? WHERE user_id = ?');
        $update->bind_param('ssi', $username, $mobileNumber, $userId);
        if ($update->execute()) {
            $_SESSION['username'] = $username;
            $success = 'Profile updated successfully.';
            $profile['username'] = $username;
            $profile['mobile_number'] = $mobileNumber;
        } else {
            $errors[] = 'Unable to update profile.';
        }
        $update->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Manage Profile</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card">
      <h1 class="auth-title">Manage Profile</h1>
      <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="form">
        <div class="field"><label>Username</label><input type="text" name="username" value="<?= htmlspecialchars((string) ($profile['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field"><label>Email (read-only)</label><input type="email" value="<?= htmlspecialchars((string) ($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly></div>
        <div class="field"><label>Mobile Number</label><input type="tel" name="mobile_number" value="<?= htmlspecialchars((string) ($profile['mobile_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="+94771234567"></div>
        <button class="btn" type="submit">Save Changes</button>
      </form>
      <div class="bottom-text"><a class="link" href="userdashboard.php">Back to Dashboard</a></div>
    </section>
  </main>
</body>
</html>


