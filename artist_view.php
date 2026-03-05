<?php
/**
 * Project File Purpose:
 * - artist_view.php
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
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$userAvatar = $isLoggedIn ? fetchUserProfileImagePath($conn, $currentUserId) : 'assets/img/user profile.png';
$artistUserId = (int) ($_GET['id'] ?? 0);
$errors = [];
$success = '';
$lastAction = '';

if ($artistUserId <= 0) {
    http_response_code(404);
    echo 'Artist not found.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn) {
        $errors[] = 'Please login to perform this action.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $lastAction = $action;
        if ($currentUserId === $artistUserId) {
            $errors[] = 'You cannot perform this action on your own artist profile.';
        } elseif ($action === 'become_fan') {
            if (createArtistFollow($conn, $currentUserId, $artistUserId)) {
                $success = 'You are now a fan of this artist.';
            } else {
                $errors[] = 'Unable to follow artist right now.';
            }
        } elseif ($action === 'unfollow_fan') {
            if (deleteArtistFollow($conn, $currentUserId, $artistUserId)) {
                $success = 'You are no longer following this artist.';
            } else {
                $errors[] = 'Unable to unfollow artist right now.';
            }
        } elseif ($action === 'add_review') {
            $rating = (int) ($_POST['rating'] ?? 0);
            $reviewText = trim((string) ($_POST['review_text'] ?? ''));
            if ($rating < 1 || $rating > 5 || $reviewText === '') {
                $errors[] = 'Review rating and message are required.';
            } elseif (createArtistReview($conn, $artistUserId, $currentUserId, $rating, $reviewText)) {
                $success = 'Review added.';
            } else {
                $errors[] = 'Unable to add review right now.';
            }
        } elseif ($action === 'delete_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            if ($reviewId > 0 && deleteArtistReview($conn, $reviewId, $currentUserId)) {
                $success = 'Review deleted.';
            } else {
                $errors[] = 'Unable to delete review.';
            }
        } elseif ($action === 'send_hire_request') {
            $message = trim((string) ($_POST['hire_message'] ?? ''));
            if ($message === '') {
                $errors[] = 'Hire request message is required.';
            } elseif (createHireRequest($conn, $artistUserId, $currentUserId, $message)) {
                $success = 'Hire request sent.';
            } else {
                $errors[] = 'Unable to send hire request.';
            }
        }
    }
}

$artist = fetchArtistPublicProfile($conn, $artistUserId);
if (!$artist) {
    http_response_code(404);
    echo 'Artist profile not found or not approved.';
    exit;
}

$username = $_SESSION['username'] ?? 'Guest';
$isFollowing = $isLoggedIn ? isFollowingArtist($conn, $currentUserId, $artistUserId) : false;
$canInteractWithArtist = $isLoggedIn && $currentUserId !== $artistUserId;
$reviews = fetchArtistReviews($conn, $artistUserId, 100);
$socialLinks = preg_split('/\r\n|\r|\n/', (string) ($artist['social_links'] ?? '')) ?: [];
$socialLinks = array_values(array_filter(array_map('trim', $socialLinks)));
$galleryImages = json_decode((string) ($artist['gallery_image_paths'] ?? ''), true);
$galleryImages = is_array($galleryImages) ? array_values(array_filter(array_map('strval', $galleryImages))) : [];
$portfolioVideos = json_decode((string) ($artist['portfolio_video_paths'] ?? ''), true);
$portfolioVideos = is_array($portfolioVideos) ? array_values(array_filter(array_map('strval', $portfolioVideos))) : [];
$profileImageSrc = !empty($artist['profile_image_path'])
    ? (string) $artist['profile_image_path']
    : 'assets/img/user profile.png';
$uploadedVideos = [];
if (!empty($artist['intro_video_path'])) {
    $uploadedVideos[] = (string) $artist['intro_video_path'];
}
foreach ($portfolioVideos as $videoPath) {
    $uploadedVideos[] = (string) $videoPath;
}
$uploadedVideos = array_values(array_unique(array_filter($uploadedVideos)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - <?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="assets/css/pages.css">
</head>
<body>
  <!-- Section: Header / Navigation -->
  <header>
    <div class="container nav-wrap">
      <img class="logo" src="assets/img/StageOnLogo.png" alt="StageOn">
      <nav>
        <a href="index.php">Home</a>
        <a href="artists.php">Artists</a>
        <a href="vacancies.php">Vacancies</a>
        <a href="ads.php">Ads</a>
        <a href="events.php">Events</a>
      </nav>
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
      <section class="artist-page-box">
        <section class="hero-panel artist-hero-panel">
          <p><img src="<?= htmlspecialchars($profileImageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture" style="width:110px;height:110px;object-fit:cover;border-radius:50%;border:1px solid rgba(17,24,39,0.12);"></p>
          <div class="artist-hero-head">
            <h1><?= htmlspecialchars((string) $artist['stage_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <?php if ($canInteractWithArtist): ?>
              <?php if ($isFollowing): ?>
                <form method="post">
                  <input type="hidden" name="action" value="unfollow_fan">
                  <button class="btn btn-secondary" type="submit">Unfollow</button>
                </form>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="action" value="become_fan">
                  <button class="btn btn-primary" type="submit">Become Fan</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <p><?= htmlspecialchars((string) $artist['artist_category'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int) $artist['experience_years'] ?> years experience</p>
          <?php if ((int) ($artist['is_emerging_artist'] ?? 0) === 1): ?>
            <p><strong>Emerging Artist</strong></p>
          <?php endif; ?>
          <div class="artist-bio-inline">
            <strong>Bio:</strong> <?= nl2br(htmlspecialchars((string) $artist['bio'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
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
          <h2>Artist Profile</h2>
          <article class="card">
            <p><strong>Contact Preference:</strong> <?= htmlspecialchars((string) ucfirst((string) ($artist['contact_preference'] ?? 'Not set')), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Portfolio:</strong>
              <?php if (!empty($artist['portfolio_url'])): ?>
                <a href="<?= htmlspecialchars((string) $artist['portfolio_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open Portfolio</a>
              <?php else: ?>
                Not provided
              <?php endif; ?>
            </p>
            <p><strong>Intro Video:</strong>
              <?php if (!empty($artist['intro_video_path'])): ?>
                <a href="<?= htmlspecialchars((string) $artist['intro_video_path'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Watch Video</a>
              <?php else: ?>
                Not available
              <?php endif; ?>
            </p>
            <p><strong>Social Links:</strong>
              <?php if ($socialLinks): ?>
                <?php foreach ($socialLinks as $idx => $link): ?>
                  <a href="<?= htmlspecialchars($link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Link <?= $idx + 1 ?></a><?= $idx < count($socialLinks) - 1 ? ', ' : '' ?>
                <?php endforeach; ?>
              <?php else: ?>
                Not provided
              <?php endif; ?>
            </p>
            <p><strong>Images:</strong> <?= count($galleryImages) ?> uploaded</p>
            <p><strong>Extra Videos:</strong> <?= count($portfolioVideos) ?> uploaded</p>
          </article>
        </section>

        <section class="section">
          <h2>Uploaded Videos</h2>
          <div class="grid">
            <?php if (!$uploadedVideos): ?>
              <article class="card"><p>No videos uploaded yet.</p></article>
            <?php else: ?>
              <?php foreach ($uploadedVideos as $idx => $videoPath): ?>
                <article class="card">
                  <h3><?= $idx === 0 ? 'Intro Video' : 'Portfolio Video ' . $idx ?></h3>
                  <video class="artist-video" controls preload="metadata">
                    <source src="<?= htmlspecialchars($videoPath, ENT_QUOTES, 'UTF-8') ?>">
                    Your browser does not support the video tag.
                  </video>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="section">
          <h2>Uploaded Images</h2>
          <div class="grid">
            <?php if (!$galleryImages): ?>
              <article class="card"><p>No images uploaded yet.</p></article>
            <?php else: ?>
              <?php foreach ($galleryImages as $idx => $imagePath): ?>
                <article class="card">
                  <h3>Photo <?= $idx + 1 ?></h3>
                  <img
                    class="artist-image"
                    src="<?= htmlspecialchars((string) $imagePath, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Artist photo <?= $idx + 1 ?>"
                  >
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section class="section">
          <h2>Actions</h2>
          <?php if (!$isLoggedIn): ?>
            <p>Please <a href="auth/login.php">login</a> to hire or review this artist.</p>
          <?php elseif (!$canInteractWithArtist): ?>
            <p>You are viewing your own artist profile.</p>
          <?php else: ?>
            <div class="action-buttons">
              <button class="btn btn-primary" type="button" data-modal-open="hire-modal">Hire Artist</button>
              <button class="btn btn-primary" type="button" data-modal-open="review-modal">Add Review</button>
            </div>
          <?php endif; ?>
        </section>

      <div id="hire-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="hire-modal-title">
          <div class="modal-head">
            <h3 id="hire-modal-title">Hire Artist</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
          </div>
          <form method="post" class="modal-form">
            <input type="hidden" name="action" value="send_hire_request">
            <textarea name="hire_message" rows="5" placeholder="Describe your event and requirement" required></textarea>
            <button class="btn btn-primary" type="submit">Send Request</button>
          </form>
        </div>
      </div>

      <div id="review-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
          <div class="modal-head">
            <h3 id="review-modal-title">Add Review</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
          </div>
          <form method="post" class="modal-form">
            <input type="hidden" name="action" value="add_review">
            <label for="rating">Rating</label>
            <select id="rating" name="rating">
              <option value="5">5</option>
              <option value="4">4</option>
              <option value="3">3</option>
              <option value="2">2</option>
              <option value="1">1</option>
            </select>
            <textarea name="review_text" rows="5" placeholder="Write your review" required></textarea>
            <button class="btn btn-primary" type="submit">Add Review</button>
          </form>
        </div>
      </div>

        <section class="section">
          <h2>Reviews</h2>
          <div class="grid">
            <?php if (!$reviews): ?>
              <article class="card"><p>No reviews yet.</p></article>
            <?php else: ?>
              <?php foreach ($reviews as $review): ?>
                <article class="card">
                  <h3><?= htmlspecialchars((string) $review['reviewer_name'], ENT_QUOTES, 'UTF-8') ?> &bull; <?= (int) $review['rating'] ?>/5</h3>
                  <p><?= nl2br(htmlspecialchars((string) $review['review_text'], ENT_QUOTES, 'UTF-8')) ?></p>
                  <p><?= htmlspecialchars((string) $review['created_at'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php if ($isLoggedIn && (int) $review['reviewer_user_id'] === $currentUserId): ?>
                    <form method="post">
                      <input type="hidden" name="action" value="delete_review">
                      <input type="hidden" name="review_id" value="<?= (int) $review['review_id'] ?>">
                      <button class="btn btn-secondary" type="submit">Delete Review</button>
                    </form>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>
      </section>
    </div>
  </main>
  <script>
    (function () {
      const body = document.body;
      const overlays = document.querySelectorAll(".modal-overlay");
      const openers = document.querySelectorAll("[data-modal-open]");
      const closers = document.querySelectorAll("[data-modal-close]");

      function closeAll() {
        overlays.forEach(function (overlay) {
          overlay.classList.remove("open");
          overlay.setAttribute("aria-hidden", "true");
        });
        body.classList.remove("modal-open");
      }

      function openModal(id) {
        const target = document.getElementById(id);
        if (!target) return;
        closeAll();
        target.classList.add("open");
        target.setAttribute("aria-hidden", "false");
        body.classList.add("modal-open");
      }

      openers.forEach(function (button) {
        button.addEventListener("click", function () {
          openModal(button.getAttribute("data-modal-open"));
        });
      });

      closers.forEach(function (button) {
        button.addEventListener("click", closeAll);
      });

      overlays.forEach(function (overlay) {
        overlay.addEventListener("click", function (event) {
          if (event.target === overlay) {
            closeAll();
          }
        });
      });

      document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
          closeAll();
        }
      });

      <?php if ($errors && $lastAction === 'add_review'): ?>
      openModal("review-modal");
      <?php elseif ($errors && $lastAction === 'send_hire_request'): ?>
      openModal("hire-modal");
      <?php endif; ?>
    })();
  </script>
</body>
</html>
