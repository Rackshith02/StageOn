<?php
/**
 * Project File Purpose:
 * - ads.php
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
$ads = fetchApprovedAds($conn, 120);

if ($searchQuery !== '') {
    $ads = array_values(array_filter($ads, static function (array $ad) use ($searchQuery): bool {
        $needle = strtolower($searchQuery);
        $haystack = strtolower((string) ($ad['ad_title'] ?? '') . ' ' . (string) ($ad['ad_content'] ?? ''));
        return strpos($haystack, $needle) !== false;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Ads</title>
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
        <input id="page-search" type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search ads" aria-label="Search ads">
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
      <section class="hero-panel">
        <h1>Approved Advertisements</h1>
        <p>All ads below are reviewed and approved by admin before publishing.</p>
      </section>

      <section class="section">
        <h2>Ad List</h2>
        <div class="grid">
          <?php if (!$ads): ?>
            <article class="card"><h3>No approved ads yet</h3><p>Approved ads will appear here automatically.</p></article>
          <?php else: ?>
            <?php foreach ($ads as $ad): ?>
              <article class="card" data-search-item>
                <h3><?= htmlspecialchars((string) $ad['ad_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if (!empty($ad['poster_path'])): ?>
                  <p><img src="<?= htmlspecialchars((string) $ad['poster_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Ad poster" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid rgba(17,24,39,0.12);"></p>
                <?php endif; ?>
                <p><?= nl2br(htmlspecialchars((string) $ad['ad_content'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><strong>Active:</strong> <?= htmlspecialchars((string) ($ad['start_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?> to <?= htmlspecialchars((string) ($ad['end_date'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Posted by:</strong> <?= htmlspecialchars((string) $ad['username'], ENT_QUOTES, 'UTF-8') ?></p>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>
</body>
</html>


