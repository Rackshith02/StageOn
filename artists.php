<?php
/**
 * Project File Purpose:
 * - artists.php
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
$username = $_SESSION['username'] ?? 'Guest';
$userAvatar = $isLoggedIn ? fetchUserProfileImagePath($conn, (int) ($_SESSION['user_id'] ?? 0)) : 'assets/img/user profile.png';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$artistCategories = fetchArtistCategories($conn);

$artists = fetchApprovedArtists($conn, $categoryFilter, 120);
if ($searchQuery !== '') {
    $artists = array_values(array_filter($artists, static function (array $artist) use ($searchQuery): bool {
        $needle = strtolower($searchQuery);
        $haystack = strtolower(
            (string) ($artist['stage_name'] ?? '') . ' ' .
            (string) ($artist['artist_category'] ?? '') . ' ' .
            (string) ($artist['bio'] ?? '')
        );
        return strpos($haystack, $needle) !== false;
    }));
}
$emergingArtists = array_values(array_filter($artists, static function (array $artist): bool {
    return (int) ($artist['is_emerging_artist'] ?? 0) === 1;
}));

$trendingArtists = fetchTrendingArtists($conn, 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Artists</title>
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
        <input id="page-search" type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search artists" aria-label="Search artists">
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
        <h1>Browse Artists</h1>
        <p>Find the right artist, access profiles, become a fan, add reviews, and send hiring requests.</p>
      </section>

      <section class="section">
        <form method="get" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <input type="search" name="q" placeholder="Find artist by name or bio" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" style="flex:1;min-width:220px;">
          <select name="category" style="min-width:180px;">
            <option value="">All categories</option>
            <?php foreach ($artistCategories as $cat): ?>
              <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit">Search</button>
        </form>
      </section>

      <section class="section">
        <h2>Artist List</h2>
        <div class="grid">
          <?php if (!$artists): ?>
            <article class="card"><h3>No artists found</h3><p>Try a different name or category.</p></article>
          <?php else: ?>
            <?php foreach ($artists as $artist): ?>
              <?php $profileThumb = !empty($artist['profile_image_path']) ? (string) $artist['profile_image_path'] : 'assets/img/user profile.png'; ?>
              <a class="card" href="artist_view.php?id=<?= (int) $artist['user_id'] ?>" data-search-item>
                <div class="artist-card-head">
                  <img class="artist-card-avatar" src="<?= htmlspecialchars($profileThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture">
                  <div class="artist-card-meta">
                    <h3><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><strong>Category:</strong> <?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
                <?php if ((int) ($artist['is_emerging_artist'] ?? 0) === 1): ?>
                  <p><strong>Emerging Artist</strong></p>
                <?php endif; ?>
                <p><strong>Experience:</strong> <?= (int) $artist['experience_years'] ?> years</p>
                <p><?= htmlspecialchars(substr((string) $artist['bio'], 0, 120), ENT_QUOTES, 'UTF-8') ?>...</p>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section">
        <h2>Emerging Artists</h2>
        <div class="grid">
          <?php if (!$emergingArtists): ?>
            <article class="card"><h3>No emerging artists yet</h3><p>Artists who mark themselves as emerging and get approved will appear here.</p></article>
          <?php else: ?>
            <?php foreach ($emergingArtists as $artist): ?>
              <?php $profileThumb = !empty($artist['profile_image_path']) ? (string) $artist['profile_image_path'] : 'assets/img/user profile.png'; ?>
              <a class="card" href="artist_view.php?id=<?= (int) $artist['user_id'] ?>">
                <div class="artist-card-head">
                  <img class="artist-card-avatar" src="<?= htmlspecialchars($profileThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture">
                  <div class="artist-card-meta">
                    <h3><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
                <p>Upcoming individual talent</p>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="section">
        <h2>Trending Profiles</h2>
        <div class="grid">
          <?php if (!$trendingArtists): ?>
            <article class="card"><h3>No trending data yet</h3><p>As users follow and review artists, trending will appear here.</p></article>
          <?php else: ?>
            <?php foreach ($trendingArtists as $artist): ?>
              <?php $profileThumb = !empty($artist['profile_image_path']) ? (string) $artist['profile_image_path'] : 'assets/img/user profile.png'; ?>
              <a class="card" href="artist_view.php?id=<?= (int) $artist['user_id'] ?>">
                <div class="artist-card-head">
                  <img class="artist-card-avatar" src="<?= htmlspecialchars($profileThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture">
                  <div class="artist-card-meta">
                    <h3><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
                <p><?= (int) $artist['follows_count'] ?> fans &bull; <?= (int) $artist['reviews_count'] ?> reviews</p>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </div>
  </main>

  <!-- Section: Footer -->
  <footer>
    <div class="container">
      <p>PRIVACY POLICY | COOKIE POLICY | LEGAL NOTE</p>
      <p>&copy; <?= date('Y') ?> StageOn.lk</p>
    </div>
  </footer>
</body>
</html>
