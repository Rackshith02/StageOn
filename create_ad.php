<?php
/**
 * Project File Purpose:
 * - create_ad.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/portal_workflow_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function uploadAdPoster(array $file, int $userId, array &$errors): string
{
    // Poster uploader for public ads listing after approval.
    $size = (int) ($file['size'] ?? 0);
    $maxSize = 10 * 1024 * 1024;
    if ($size <= 0 || $size > $maxSize) {
        $errors[] = 'Poster image must be between 1 byte and 10MB.';
        return '';
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        $errors[] = 'Poster image format must be jpg, jpeg, png, or webp.';
        return '';
    }

    $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ad_posters';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Unable to prepare ad poster upload directory.';
        return '';
    }

    $safeFile = sprintf('ad_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $ext);
    $target = $uploadDir . DIRECTORY_SEPARATOR . $safeFile;
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
        $errors[] = 'Unable to save uploaded ad poster.';
        return '';
    }

    return 'uploads/ad_posters/' . $safeFile;
}

ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

$userId = (int) $_SESSION['user_id'];
$postedByType = isApprovedArtist($conn, $userId) ? 'artist' : 'user';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate posting inputs.
    $title = trim((string) ($_POST['ad_title'] ?? ''));
    $content = trim((string) ($_POST['ad_content'] ?? ''));
    $startDate = trim((string) ($_POST['start_date'] ?? ''));
    $endDate = trim((string) ($_POST['end_date'] ?? ''));
    $posterPath = '';

    if ($title === '' || $content === '') {
        $errors[] = 'Title and content are required.';
    }
    if (!isset($_FILES['poster_image']) || !is_array($_FILES['poster_image'])) {
        $errors[] = 'Poster image is required.';
    } else {
        $poster = $_FILES['poster_image'];
        if ((int) ($poster['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Failed to upload poster image.';
        } else {
            $posterPath = uploadAdPoster($poster, $userId, $errors);
        }
    }

    if (!$errors) {
        // Advertisement is stored as pending until admin review.
        $start = $startDate !== '' ? $startDate : null;
        $end = $endDate !== '' ? $endDate : null;
        if (createAdPost($conn, $userId, $postedByType, $title, $content, $posterPath, $start, $end)) {
            $success = 'Advertisement submitted for admin review.';
        } else {
            $errors[] = 'Unable to submit ad right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Create Advertisement</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card artist-application-card">
      <h1 class="auth-title">Post Advertisement</h1>
      <p class="auth-subtitle">Add ad details and a poster. It will publish after admin approval.</p>
      <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="form" enctype="multipart/form-data">
        <div class="field"><label>Ad Title</label><input type="text" name="ad_title" required></div>
        <div class="field"><label>Ad Content</label><textarea name="ad_content" rows="4" required></textarea></div>
        <div class="field"><label>Ad Poster (jpg/png/webp, max 10MB)</label><input type="file" name="poster_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></div>
        <div class="field"><label>Start Date</label><input type="date" name="start_date"></div>
        <div class="field"><label>End Date</label><input type="date" name="end_date"></div>
        <button class="btn" type="submit">Submit Ad</button>
      </form>
      <div class="bottom-text"><a class="link" href="userdashboard.php">Back to Dashboard</a></div>
    </section>
  </main>
</body>
</html>


