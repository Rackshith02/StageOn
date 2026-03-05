<?php
/**
 * Project File Purpose:
 * - artist_profile.php
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

function decodePathList(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    return array_values(array_filter(array_map('strval', $decoded)));
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $num = (float) $value;
    if ($unit === 'g') {
        return (int) ($num * 1024 * 1024 * 1024);
    }
    if ($unit === 'm') {
        return (int) ($num * 1024 * 1024);
    }
    if ($unit === 'k') {
        return (int) ($num * 1024);
    }
    return (int) $num;
}

function handleMultipleUploads(
    array $files,
    int $userId,
    string $type,
    array $allowedExt,
    int $maxSize,
    string $dirName,
    int $maxFiles,
    array &$errors
): array {
    $saved = [];
    $count = count($files['name'] ?? []);
    if ($count === 0) {
        return $saved;
    }

    if ($count > $maxFiles) {
        $errors[] = "You can upload up to {$maxFiles} {$type} files at once.";
        return $saved;
    }

    $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $dirName;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = "Unable to prepare {$type} upload directory.";
        return $saved;
    }

    for ($i = 0; $i < $count; $i++) {
        $error = (int) (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE));
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "Failed to upload one {$type} file.";
            continue;
        }

        $size = (int) (($files['size'][$i] ?? 0));
        if ($size <= 0 || $size > $maxSize) {
            $maxMb = (int) round($maxSize / (1024 * 1024));
            $errors[] = "Each {$type} file must be between 1 byte and {$maxMb}MB.";
            continue;
        }

        $name = (string) (($files['name'][$i] ?? ''));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $errors[] = "Invalid {$type} format: {$name}.";
            continue;
        }

        $safeFile = sprintf('artist_%d_%s_%s.%s', $userId, $type, bin2hex(random_bytes(8)), $ext);
        $target = $uploadDir . DIRECTORY_SEPARATOR . $safeFile;
        if (!move_uploaded_file((string) ($files['tmp_name'][$i] ?? ''), $target)) {
            $errors[] = "Unable to save one {$type} file.";
            continue;
        }

        $saved[] = 'uploads/' . $dirName . '/' . $safeFile;
    }

    return $saved;
}

ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';
$application = fetchArtistApplicationByUser($conn, $userId);

if (!$application || (string) ($application['status'] ?? '') !== 'approved') {
    http_response_code(403);
    echo '<h1>403 - Artist profile unavailable</h1><p>Your artist application is not approved yet.</p><p><a href="userdashboard.php">Back to dashboard</a></p>';
    exit;
}

$existingImagePaths = decodePathList((string) ($application['gallery_image_paths'] ?? ''));
$existingVideoPaths = decodePathList((string) ($application['portfolio_video_paths'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile_image') {
        if (!isset($_FILES['profile_image']) || !is_array($_FILES['profile_image'])) {
            $errors[] = 'Profile picture file is required.';
        } else {
            $image = $_FILES['profile_image'];
            if ((int) ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload profile picture.';
            } else {
                $size = (int) ($image['size'] ?? 0);
                if ($size <= 0 || $size > 5 * 1024 * 1024) {
                    $errors[] = 'Profile picture must be between 1 byte and 5MB.';
                } else {
                    $ext = strtolower(pathinfo((string) ($image['name'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $errors[] = 'Profile picture format must be jpg, jpeg, png, or webp.';
                    } else {
                        $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'artist_profile_images';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                            $errors[] = 'Unable to prepare profile picture upload directory.';
                        } else {
                            $safeFile = sprintf('artist_profile_%d_%s.%s', $userId, bin2hex(random_bytes(8)), $ext);
                            $target = $uploadDir . DIRECTORY_SEPARATOR . $safeFile;
                            if (!move_uploaded_file((string) ($image['tmp_name'] ?? ''), $target)) {
                                $errors[] = 'Unable to save uploaded profile picture.';
                            } else {
                                $profileImagePath = 'uploads/artist_profile_images/' . $safeFile;
                                $update = $conn->prepare('UPDATE artist_applications SET profile_image_path = ? WHERE user_id = ? LIMIT 1');
                                $update->bind_param('si', $profileImagePath, $userId);
                                if ($update->execute()) {
                                    $success = 'Profile picture updated successfully.';
                                    $application['profile_image_path'] = $profileImagePath;
                                } else {
                                    $errors[] = 'Unable to update profile picture.';
                                }
                                $update->close();
                            }
                        }
                    }
                }
            }
        }
    }

    if ($action === 'upload_media') {
        $videoUploadMaxRaw = (string) ini_get('upload_max_filesize');
        $videoUploadMax = iniSizeToBytes($videoUploadMaxRaw);
        $videoMaxSize = min(150 * 1024 * 1024, $videoUploadMax > 0 ? $videoUploadMax : 150 * 1024 * 1024);

        $newImages = [];
        if (isset($_FILES['post_images']) && is_array($_FILES['post_images'])) {
            $newImages = handleMultipleUploads(
                $_FILES['post_images'],
                $userId,
                'image',
                ['jpg', 'jpeg', 'png', 'webp'],
                8 * 1024 * 1024,
                'artist_images',
                8,
                $errors
            );
        }

        $newVideos = [];
        if (isset($_FILES['post_videos']) && is_array($_FILES['post_videos'])) {
            $newVideos = handleMultipleUploads(
                $_FILES['post_videos'],
                $userId,
                'video',
                ['mp4', 'mov', 'webm'],
                $videoMaxSize,
                'artist_videos',
                4,
                $errors
            );
        }

        if (!$newImages && !$newVideos && !$errors) {
            $errors[] = 'Please select at least one image or video to upload.';
        }

        if (!$errors) {
            $mergedImages = array_values(array_unique(array_merge($existingImagePaths, $newImages)));
            $mergedVideos = array_values(array_unique(array_merge($existingVideoPaths, $newVideos)));
            $imagesJson = json_encode($mergedImages, JSON_UNESCAPED_SLASHES);
            $videosJson = json_encode($mergedVideos, JSON_UNESCAPED_SLASHES);

            $update = $conn->prepare('UPDATE artist_applications SET gallery_image_paths = ?, portfolio_video_paths = ? WHERE user_id = ? LIMIT 1');
            $update->bind_param('ssi', $imagesJson, $videosJson, $userId);
            if ($update->execute()) {
                $existingImagePaths = $mergedImages;
                $existingVideoPaths = $mergedVideos;
                $application['gallery_image_paths'] = $imagesJson;
                $application['portfolio_video_paths'] = $videosJson;
                $success = 'New media uploaded successfully.';
            } else {
                $errors[] = 'Unable to update artist media posts.';
            }
            $update->close();
        }
    }
}

$username = (string) ($_SESSION['username'] ?? 'Artist');
$email = (string) ($_SESSION['email'] ?? '');
$socialLinks = preg_split('/\r\n|\r|\n/', (string) ($application['social_links'] ?? '')) ?: [];
$socialLinks = array_values(array_filter(array_map('trim', $socialLinks)));
$galleryImages = $existingImagePaths;
$portfolioVideos = $existingVideoPaths;
$profileImageSrc = !empty($application['profile_image_path'])
    ? '../' . ltrim((string) $application['profile_image_path'], '/')
    : '../assets/img/user profile.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Artist Profile</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <img class="brand-logo" src="../assets/img/StageOnLogo.png" alt="StageOn">
      <nav class="menu">
        <a class="menu-item" href="userdashboard.php">Dashboard</a>
        <a class="menu-item active" href="artist_profile.php">Artist Profile</a>
        <a class="menu-item" href="create_booking.php">Bookings</a>
        <a class="menu-item" href="create_vacancy.php">Vacancies</a>
      </nav>
      <a class="logout-btn" href="logout.php">Logout</a>
    </aside>

    <main class="main">
      <header class="topbar">
        <div>
          <p class="label">Approved Artist</p>
          <h1><?= htmlspecialchars($application['stage_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="user-chip">
          <img src="<?= htmlspecialchars($profileImageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Profile">
          <div>
            <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </header>

      <?php if ($errors): ?>
        <section class="card panel">
          <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>
      <?php if ($success): ?>
        <section class="card panel"><p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p></section>
      <?php endif; ?>

      <section class="card panel">
        <div class="panel-head">
          <h3>Artist Details</h3>
        </div>
        <p><strong>Profile Picture:</strong></p>
        <p><img src="<?= htmlspecialchars($profileImageSrc, ENT_QUOTES, 'UTF-8') ?>" alt="Artist profile picture" style="width:120px;height:120px;object-fit:cover;border-radius:50%;border:1px solid rgba(17,24,39,0.12);"></p>
        <form method="post" enctype="multipart/form-data" class="upload-controls">
          <input type="hidden" name="action" value="update_profile_image">
          <input type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
          <button type="submit">Change Picture</button>
        </form>
        <p><strong>Category:</strong> <?= htmlspecialchars((string) $application['artist_category'], ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Emerging Artist:</strong> <?= (int) ($application['is_emerging_artist'] ?? 0) === 1 ? 'Yes' : 'No' ?></p>
        <p><strong>Experience:</strong> <?= (int) $application['experience_years'] ?> years</p>
        <p><strong>Bio:</strong> <?= nl2br(htmlspecialchars((string) $application['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
        <p><strong>Contact Preference:</strong> <?= htmlspecialchars((string) ucfirst((string) ($application['contact_preference'] ?? 'Not set')), ENT_QUOTES, 'UTF-8') ?></p>
        <p><strong>Portfolio:</strong>
          <?php if (!empty($application['portfolio_url'])): ?>
            <a href="<?= htmlspecialchars((string) $application['portfolio_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open Portfolio</a>
          <?php else: ?>
            Not provided
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
      </section>

      <section class="card panel">
        <div class="panel-head">
          <h3>Upload New Media Post</h3>
        </div>
        <p class="upload-note">Add photos and videos to your artist profile feed (Instagram-style updates).</p>
        <form method="post" enctype="multipart/form-data" class="upload-form">
          <input type="hidden" name="action" value="upload_media">
          <div class="insta-upload-grid">
            <article class="insta-upload-card">
              <button type="button" class="upload-plus-trigger" data-target="post-images-input" aria-label="Upload images">+</button>
              <h4>Photos</h4>
              <p>jpg, jpeg, png, webp</p>
              <small>Up to 8 files (8MB each)</small>
              <span id="images-selected" class="upload-selected">No photos selected</span>
              <input id="post-images-input" class="upload-hidden-input" type="file" name="post_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
            </article>
            <article class="insta-upload-card">
              <button type="button" class="upload-plus-trigger" data-target="post-videos-input" aria-label="Upload videos">+</button>
              <h4>Videos</h4>
              <p>mp4, mov, webm</p>
              <small>Up to 4 files (max 150MB each, server limits apply)</small>
              <span id="videos-selected" class="upload-selected">No videos selected</span>
              <input id="post-videos-input" class="upload-hidden-input" type="file" name="post_videos[]" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm" multiple>
            </article>
          </div>
          <button type="submit">Upload Post Media</button>
        </form>
      </section>

      <section class="card panel">
        <div class="panel-head">
          <h3>Your Photo Feed</h3>
        </div>
        <div class="media-grid">
          <?php if (!$galleryImages): ?>
            <p>No photos uploaded yet.</p>
          <?php else: ?>
            <?php foreach ($galleryImages as $path): ?>
              <article class="media-card">
                <img src="<?= htmlspecialchars('../' . ltrim((string) $path, '/'), ENT_QUOTES, 'UTF-8') ?>" alt="Artist upload">
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>

      <section class="card panel">
        <div class="panel-head">
          <h3>Your Video Feed</h3>
        </div>
        <div class="media-grid">
          <?php if (!$portfolioVideos && empty($application['intro_video_path'])): ?>
            <p>No videos uploaded yet.</p>
          <?php else: ?>
            <?php if (!empty($application['intro_video_path'])): ?>
              <article class="media-card">
                <video controls preload="metadata">
                  <source src="<?= htmlspecialchars('../' . ltrim((string) $application['intro_video_path'], '/'), ENT_QUOTES, 'UTF-8') ?>">
                  Your browser does not support the video tag.
                </video>
              </article>
            <?php endif; ?>
            <?php foreach ($portfolioVideos as $path): ?>
              <article class="media-card">
                <video controls preload="metadata">
                  <source src="<?= htmlspecialchars('../' . ltrim((string) $path, '/'), ENT_QUOTES, 'UTF-8') ?>">
                  Your browser does not support the video tag.
                </video>
              </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </section>
    </main>
  </div>
  <script>
    (function () {
      const triggers = document.querySelectorAll(".upload-plus-trigger");
      const imagesInput = document.getElementById("post-images-input");
      const videosInput = document.getElementById("post-videos-input");
      const imagesSelected = document.getElementById("images-selected");
      const videosSelected = document.getElementById("videos-selected");

      triggers.forEach(function (trigger) {
        trigger.addEventListener("click", function () {
          const targetId = trigger.getAttribute("data-target");
          const input = document.getElementById(targetId);
          if (input) {
            input.click();
          }
        });
      });

      if (imagesInput && imagesSelected) {
        imagesInput.addEventListener("change", function () {
          const count = imagesInput.files ? imagesInput.files.length : 0;
          imagesSelected.textContent = count > 0 ? count + " photo(s) selected" : "No photos selected";
        });
      }

      if (videosInput && videosSelected) {
        videosInput.addEventListener("change", function () {
          const count = videosInput.files ? videosInput.files.length : 0;
          videosSelected.textContent = count > 0 ? count + " video(s) selected" : "No videos selected";
        });
      }
    })();
  </script>
</body>
</html>
