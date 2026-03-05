<?php
/**
 * Project File Purpose:
 * - dancers.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../auth/portal_workflow_helpers.php';

ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

$isLoggedIn = isset($_SESSION['user_id']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Guest';
$userAvatar = $isLoggedIn ? '../' . ltrim(fetchUserProfileImagePath($conn, $userId), '/') : '../assets/img/user profile.png';
$categoryName = 'Dancers';
$artists = fetchApprovedArtists($conn, $categoryName, 120);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Dancers</title>
  <link rel="stylesheet" href="../assets/css/category.css">
</head>
<body>
  <!-- Section: Header / Navigation -->
  <header>
    <div class="container nav-wrap">
      <img class="logo" src="../assets/img/StageOnLogo.png" alt="StageOn">
      <nav>
        <a href="../index.php">Home</a>
        <a href="../explore.php">Explore</a>
        <a href="../artists.php">Artists</a>
        <a href="../vacancies.php">Vacancies</a>
      </nav>
      <div class="auth-links">
        <?php if ($isLoggedIn): ?>
          <a class="profile-link" href="../auth/userdashboard.php" title="Go to your profile">
            <img src="<?= htmlspecialchars($userAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="User profile">
            <span><?= htmlspecialchars((string) $username, ENT_QUOTES, 'UTF-8') ?></span>
          </a>
          <a class="btn btn-primary" href="../auth/logout.php">Logout</a>
        <?php else: ?>
          <a class="btn btn-secondary" href="../auth/login.php">Login</a>
          <a class="btn btn-primary" href="../auth/register.php">Join StageOn</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Section: Main Content -->
  <main>
    <div class="container">
      <section class="category-hero">
        <img src="../assets/img/Dance.png" alt="Dancers">
        <div class="category-content">
          <h1>Dancers</h1>
          <p>Explore solo dancers and troupes for stage productions, concerts, video shoots, and cultural performances.</p>
          <div class="category-actions">
            <a class="btn btn-secondary" href="../index.php">Back to Categories</a>
          </div>
        </div>
      </section>

      <section class="artist-list-section" id="artist-list">
        <h2>Dancers</h2>
        <div class="artist-grid compact-grid">
          <?php if (!$artists): ?>
            <article class="artist-card artist-card-empty">
              <h3>No Dancers found</h3>
              <p>Approved artists in this category will appear here.</p>
            </article>
          <?php else: ?>
            <?php foreach ($artists as $artist): ?>
              <?php $profileThumb = !empty($artist['profile_image_path']) ? '../' . ltrim((string) $artist['profile_image_path'], '/') : '../assets/img/user profile.png'; ?>
              <a class="artist-card compact-card" href="../artist_view.php?id=<?= (int) $artist['user_id'] ?>">
                <img src="<?= htmlspecialchars($profileThumb, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile">
                <div class="artist-card-content">
                  <h3><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                  <p class="artist-card-sub"><?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
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