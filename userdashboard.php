<?php
/**
 * Project File Purpose:
 * - userdashboard.php
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
$username = $_SESSION['username'] ?? 'Creator';
$email = $_SESSION['email'] ?? '';
$avatarPath = '../' . ltrim(fetchUserProfileImagePath($conn, $userId), '/');
$artistApplication = fetchArtistApplicationByUser($conn, $userId);
$isArtistApproved = isApprovedArtist($conn, $userId);
$profileType = $isArtistApproved ? 'artist' : 'user';

$bookingsCount = 0;
$vacanciesCount = 0;
$adsCount = 0;

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM event_bookings WHERE requested_by_user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$bookingsCount = (int) (($stmt->get_result()->fetch_assoc()['total']) ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM vacancy_posts WHERE posted_by_user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$vacanciesCount = (int) (($stmt->get_result()->fetch_assoc()['total']) ?? 0);
$stmt->close();

$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM ad_posts WHERE posted_by_user_id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$adsCount = (int) (($stmt->get_result()->fetch_assoc()['total']) ?? 0);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - User Dashboard</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <img class="brand-logo" src="../assets/img/StageOnLogo.png" alt="StageOn">
      <nav class="menu">
        <a class="menu-item active" href="userdashboard.php">Dashboard</a>
        <a class="menu-item" href="../explore.php">Explore</a>
        <a class="menu-item" href="../artists.php">Artists</a>
        <a class="menu-item" href="../vacancies.php">Vacancies</a>
        <a class="menu-item" href="../index.php">Home</a>
      </nav>
      <a class="logout-btn" href="logout.php">Logout</a>
    </aside>

    <main class="main">
      <header class="topbar">
        <div>
          <p class="label">Welcome back</p>
          <h1>Hello, <?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="label">Profile Type: <?= htmlspecialchars(strtoupper($profileType), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="user-chip">
          <img src="<?= htmlspecialchars($avatarPath, ENT_QUOTES, 'UTF-8') ?>" alt="Profile">
          <div>
            <strong><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </header>

      <section class="stats">
        <article class="card stat-card"><p>Your Bookings</p><h2><?= $bookingsCount ?></h2><small>Total event bookings posted</small></article>
        <article class="card stat-card"><p>Your Vacancies</p><h2><?= $vacanciesCount ?></h2><small>Total vacancy posts</small></article>
        <article class="card stat-card"><p>Your Ads</p><h2><?= $adsCount ?></h2><small>Total advertisement posts</small></article>
      </section>

      <section class="card panel quick-links">
        <div class="panel-head">
          <h3>User Actions</h3>
        </div>
        <div class="actions">
          <a class="action-link" href="manage_profile.php">Manage Profile</a>
          <a class="action-link" href="artist_application.php">Switch to Artist Profile</a>
          <?php if ($isArtistApproved): ?>
            <a class="action-link" href="artist_profile.php">Open Artist Profile</a>
          <?php endif; ?>
          <a class="action-link" href="create_booking.php">Post Event Booking</a>
          <a class="action-link" href="create_vacancy.php">Post Vacancy</a>
          <a class="action-link" href="create_ad.php">Post Advertisement</a>
        </div>
      </section>

      <?php if ($artistApplication): ?>
        <section class="card panel">
          <div class="panel-head">
            <h3>Artist Application Status</h3>
          </div>
          <p><strong>Status:</strong> <?= htmlspecialchars((string) strtoupper((string) $artistApplication['status']), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Stage Name:</strong> <?= htmlspecialchars((string) $artistApplication['stage_name'], ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Category:</strong> <?= htmlspecialchars((string) $artistApplication['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Contact Preference:</strong> <?= htmlspecialchars((string) ucfirst((string) ($artistApplication['contact_preference'] ?? 'Not set')), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Submitted At:</strong> <?= htmlspecialchars((string) $artistApplication['submitted_at'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php if (!empty($artistApplication['review_note'])): ?>
            <p><strong>Admin Note:</strong> <?= htmlspecialchars((string) $artistApplication['review_note'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>


