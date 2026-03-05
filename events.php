<?php
/**
 * Project File Purpose:
 * - events.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/portal_workflow_helpers.php';

ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

$isLoggedIn = isset($_SESSION['user_id']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Guest';
$userAvatar = $isLoggedIn ? fetchUserProfileImagePath($conn, $userId) : 'assets/img/user profile.png';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$bookings = fetchApprovedEventBookings($conn, 120);

if ($searchQuery !== '') {
    $bookings = array_values(array_filter($bookings, static function (array $booking) use ($searchQuery): bool {
        $needle = strtolower($searchQuery);
        $haystack = strtolower(
            (string) ($booking['event_title'] ?? '') . ' ' .
            (string) ($booking['location'] ?? '') . ' ' .
            (string) ($booking['notes'] ?? '')
        );
        return strpos($haystack, $needle) !== false;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Events</title>
  <link rel="stylesheet" href="assets/css/pages.css">
</head>
<body>
  <!-- Section: Header / Navigation -->
  <header>
    <div class="container nav-wrap">
      <img class="logo" src="assets/img/StageOnLogo.png" alt="StageOn">
      <nav>
        <a href="index.php">Home</a>
        <a href="explore.php">Explore</a>
        <a href="artists.php">Artists</a>
        <a href="vacancies.php">Vacancies</a>
        <a href="ads.php">Ads</a>
        <a href="events.php">Events</a>
      </nav>
      <form class="nav-search" method="get">
        <input
          id="page-search"
          type="search"
          name="q"
          value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="Search events"
          aria-label="Search this tab"
        >
        <button class="nav-search-btn" type="submit" aria-label="Search"></button>
      </form>
      <div class="auth-links">
        <?php if ($isLoggedIn): ?>
          <a class="profile-link" href="auth/userdashboard.php" title="Go to your profile">
            <img src="<?= htmlspecialchars($userAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="User profile">
            <span><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <a class="btn btn-primary" href="auth/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-secondary" href="auth/login.php">Login</a>
          <a class="btn btn-primary" href="auth/register.php">Join StageOn</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Section: Main Content -->
  <main>
    <div class="container">
      <section class="section">
        <img
          src="assets/img/Banner.png"
          alt="Event Poster"
          style="width:100%;max-width:920px;display:block;margin:0 auto;border-radius:14px;border:1px solid rgba(17,24,39,0.12);"
        >
      </section>

      <section class="section">
        <h2>Approved Event Bookings</h2>
        <div class="grid">
          <?php if (!$bookings): ?>
            <article class="card"><h3>No approved event bookings yet</h3><p>Approved bookings will appear here automatically.</p></article>
          <?php else: ?>
            <?php foreach ($bookings as $booking): ?>
              <article class="card" data-search-item>
                <h3><?= htmlspecialchars((string) $booking['event_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <p><strong>Date:</strong> <?= htmlspecialchars((string) $booking['event_date'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars((string) $booking['location'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Budget:</strong> <?= htmlspecialchars((string) ($booking['budget'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= nl2br(htmlspecialchars((string) ($booking['notes'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p>
                <p><strong>Posted by:</strong> <?= htmlspecialchars((string) $booking['username'], ENT_QUOTES, 'UTF-8') ?></p>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</body>
</html>


