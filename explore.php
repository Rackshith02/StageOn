<?php
/**
 * Project File Purpose:
 * - explore.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/portal_workflow_helpers.php';

$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Guest';
$userAvatar = $isLoggedIn ? fetchUserProfileImagePath($conn, (int) ($_SESSION['user_id'] ?? 0)) : 'assets/img/user profile.png';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data
$featuredArtists = fetchApprovedArtists($conn, '', 6);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Explore</title>
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
          placeholder="Search"
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
      <section class="hero-panel">
        <h1>Explore Talent</h1>
        <p>Browse creative professionals by category, style, and performance type to find the right talent for your next project or event.</p>
      </section>

      <section class="section">
        <h2>Popular Categories</h2>
        <div class="grid">
          <a class="card" href="categories/singers.php" data-search-item>
            <h3>Singers</h3>
            <p>Solo vocalists and duet performers for live and studio bookings.</p>
          </a>
          <a class="card" href="categories/bands.php" data-search-item>
            <h3>Bands</h3>
            <p>Live bands for festivals, private functions, and commercial events.</p>
          </a>
          <a class="card" href="categories/dancers.php" data-search-item>
            <h3>Dancers</h3>
            <p>Dancers and teams for stage productions and visual media.</p>
          </a>
          <a class="card" href="categories/instrumentalists.php" data-search-item>
            <h3>Instrumentalists</h3>
            <p>Solo and ensemble instrumental performers for live events and recordings.</p>
          </a>
        </div>
      </section>

      <section class="section">
        <h2>Featured Artist List</h2>
        <div class="grid">
          <?php if (!$featuredArtists): ?>
            <article class="card"><h3>No approved artists yet</h3><p>Once admin approves applications, artists will appear here.</p></article>
          <?php else: ?>
            <?php foreach ($featuredArtists as $artist): ?>
              <a class="card" href="artist_view.php?id=<?= (int) $artist['user_id'] ?>" data-search-item>
                <?php $profileThumb = !empty($artist['profile_image_path']) ? (string) $artist['profile_image_path'] : 'assets/img/user profile.png'; ?>
                <div class="artist-card-head">
                  <img class="artist-card-avatar" src="<?= htmlspecialchars($profileThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture">
                  <div class="artist-card-meta">
                    <h3><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
                  </div>
                </div>
                <p><strong>Experience:</strong> <?= (int) ($artist['experience_years'] ?? 0) ?> years</p>
                <?php if ((int) ($artist['is_emerging_artist'] ?? 0) === 1): ?>
                  <p><strong>Emerging Artist</strong></p>
                <?php endif; ?>
                <p>Access profile, become fan, add review, or hire.</p>
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
  <script>
    (function () {
      const searchInput = document.getElementById("page-search");
      if (!searchInput) {
        return;
      }

      const items = document.querySelectorAll("[data-search-item]");
      function applyFilter(query) {
        const normalized = query.trim().toLowerCase();
        items.forEach(function (item) {
          const text = (item.textContent || "").toLowerCase();
          item.style.display = normalized === "" || text.includes(normalized) ? "" : "none";
        });
      }

      applyFilter(searchInput.value);
      searchInput.addEventListener("input", function () {
        applyFilter(searchInput.value);
      });
    })();
  </script>
</body>
</html>


