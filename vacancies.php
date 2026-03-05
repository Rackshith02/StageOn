<?php
/**
 * Project File Purpose:
 * - vacancies.php
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
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) {
        $errors[] = 'Please login to apply for vacancies.';
    } else {
        $vacancyId = (int) ($_POST['vacancy_id'] ?? 0);
        $coverNote = trim((string) ($_POST['cover_note'] ?? ''));
        if ($vacancyId <= 0) {
            $errors[] = 'Invalid vacancy selection.';
        } elseif (applyVacancy($conn, $vacancyId, $userId, $coverNote)) {
            $success = 'Application submitted.';
        } else {
            $errors[] = 'Unable to apply right now.';
        }
    }
}

$vacancies = fetchApprovedVacancies($conn, 120);
if ($searchQuery !== '') {
    $vacancies = array_values(array_filter($vacancies, static function (array $vacancy) use ($searchQuery): bool {
        $needle = strtolower($searchQuery);
        $haystack = strtolower(
            (string) ($vacancy['title'] ?? '') . ' ' .
            (string) ($vacancy['category'] ?? '') . ' ' .
            (string) ($vacancy['location'] ?? '') . ' ' .
            (string) ($vacancy['description'] ?? '')
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
  <title>StageOn - Vacancies</title>
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
        <h1>Vacancies</h1>
        <p>View approved vacancies, open details, and apply directly.</p>
      </section>

      <?php if ($errors): ?>
        <section class="section">
          <article class="card">
            <?php foreach ($errors as $error): ?>
              <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </article>
        </section>
      <?php endif; ?>
      <?php if ($success): ?>
        <section class="section">
          <article class="card"><p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p></article>
        </section>
      <?php endif; ?>

      <section class="section">
        <h2>View Vacancy</h2>
        <div class="grid">
          <?php if (!$vacancies): ?>
            <article class="card"><h3>No vacancies found</h3><p>Try another search term.</p></article>
          <?php else: ?>
            <?php foreach ($vacancies as $vacancy): ?>
              <?php $alreadyApplied = $isLoggedIn ? hasAppliedVacancy($conn, (int) $vacancy['vacancy_id'], $userId) : false; ?>
              <article class="card" data-search-item>
                <h3><?= htmlspecialchars((string) $vacancy['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if (!empty($vacancy['poster_path'])): ?>
                  <p><img src="<?= htmlspecialchars((string) $vacancy['poster_path'], ENT_QUOTES, 'UTF-8') ?>" alt="Vacancy poster" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;border:1px solid rgba(17,24,39,0.12);"></p>
                <?php endif; ?>
                <p><strong>Category:</strong> <?= htmlspecialchars((string) $vacancy['category'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars((string) $vacancy['location'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><?= nl2br(htmlspecialchars((string) $vacancy['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                <p><strong>Posted by:</strong> <?= htmlspecialchars((string) $vacancy['username'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) $vacancy['email'], ENT_QUOTES, 'UTF-8') ?>)</p>
                <?php if ($isLoggedIn): ?>
                  <?php if ($alreadyApplied): ?>
                    <p><strong>Status:</strong> Applied</p>
                  <?php else: ?>
                    <form method="post">
                      <input type="hidden" name="vacancy_id" value="<?= (int) $vacancy['vacancy_id'] ?>">
                      <textarea name="cover_note" rows="2" placeholder="Optional note for your application"></textarea>
                      <p></p>
                      <button class="btn btn-primary" type="submit">Apply Vacancy</button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <p><a href="auth/login.php">Login to apply</a></p>
                <?php endif; ?>
              </article>
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


