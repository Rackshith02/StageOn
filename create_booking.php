<?php
/**
 * Project File Purpose:
 * - create_booking.php
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

ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

$userId = (int) $_SESSION['user_id'];
$isArtist = isApprovedArtist($conn, $userId);
$postedByType = $isArtist ? 'artist' : 'user';
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate event booking request inputs.
    $title = trim((string) ($_POST['event_title'] ?? ''));
    $date = trim((string) ($_POST['event_date'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $budgetRaw = trim((string) ($_POST['budget'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $budget = $budgetRaw === '' ? null : (float) $budgetRaw;

    if ($title === '' || $date === '' || $location === '') {
        $errors[] = 'Title, date and location are required.';
    }

    if (!$errors) {
        // Event booking is queued for admin review.
        if (createEventBooking($conn, $userId, $postedByType, $title, $date, $location, $budget, $notes)) {
            $success = 'Booking request posted.';
        } else {
            $errors[] = 'Unable to post booking right now.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Create Booking</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card artist-application-card">
      <h1 class="auth-title">Create Event Booking</h1>
      <p class="auth-subtitle">Submit your event request. It will appear publicly once approved by admin.</p>
      <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="form">
        <div class="field"><label>Event Title</label><input type="text" name="event_title" required></div>
        <div class="field"><label>Event Date</label><input type="date" name="event_date" required></div>
        <div class="field"><label>Location</label><input type="text" name="location" required></div>
        <div class="field"><label>Budget (optional)</label><input type="number" step="0.01" name="budget"></div>
        <div class="field"><label>Notes</label><textarea name="notes" rows="3"></textarea></div>
        <button class="btn" type="submit">Submit Booking</button>
      </form>
      <div class="bottom-text"><a class="link" href="userdashboard.php">Back to Dashboard</a></div>
    </section>
  </main>
</body>
</html>


