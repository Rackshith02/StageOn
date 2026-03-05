<?php
/**
 * Project File Purpose:
 * - index.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/auth/portal_workflow_helpers.php';

ensurePortalWorkflowSchema($conn);

$isLoggedIn = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? 'Guest';
$userAvatar = $isLoggedIn ? fetchUserProfileImagePath($conn, (int) ($_SESSION['user_id'] ?? 0)) : 'assets/img/user profile.png';
$searchQuery = trim((string) ($_GET['q'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Home</title>
  <link rel="stylesheet" href="assets/css/home.css">
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

  <section class="hero">
    <div class="container hero-inner">
      <h1>Where Talent Takes the Stage.</h1>
      <p>Discover, follow, and hire creative talent</p>
      <div class="hero-buttons">
        <a class="btn btn-primary" href="explore.php">Explore Talent</a>
        <a class="btn btn-secondary" href="<?= $isLoggedIn ? 'auth/userdashboard.php' : 'auth/register.php' ?>">Join StageOn</a>
      </div>
    </div>
  </section>

  <section class="categories">
    <div class="container">
      <h2>Categories</h2>
      <div class="category-list">
        <a class="category-card" href="categories/singers.php" data-search-item>
          <img src="assets/img/singer.jpg.png" alt="Singers">
          <p>Singers</p>
        </a>
        <a class="category-card" href="categories/bands.php" data-search-item>
          <img src="assets/img/Band.png" alt="Bands">
          <p>Bands</p>
        </a>
        <a class="category-card" href="categories/dancers.php" data-search-item>
          <img src="assets/img/Dance.png" alt="Dancers">
          <p>Dancers</p>
        </a>
        <a class="category-card" href="categories/photographers.php" data-search-item>
          <img src="assets/img/Photography.png" alt="Photographers">
          <p>Photographers</p>
        </a>
        <a class="category-card" href="categories/actors.php" data-search-item>
          <img src="assets/img/Actors.png" alt="Actors">
          <p>Actors</p>
        </a>
        <a class="category-card" href="categories/instrumentalists.php" data-search-item>
          <img src="assets/img/Band.png" alt="Instrumentalists">
          <p>Instrumentalists</p>
        </a>
      </div>
    </div>
  </section>

  <section class="about">
    <div class="container">
      <h2>Step into the Spotlight</h2>
      <p>
        StageOn is a creative talent platform that brings artists, fans, and hirers together in one place.
        It allows singers, bands, dancers, actors, and other creatives to showcase their work through professional profiles,
        while fans can discover and support talent, and hirers can easily find, review, and contact artists for collaborations or events.
      </p>
      <a class="btn btn-primary" href="<?= $isLoggedIn ? 'auth/userdashboard.php' : 'auth/register.php' ?>">Join Us Now</a>
    </div>
  </section>

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


