<?php
/**
 * Project File Purpose:
 * - artist_application.php
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
    // Stored media path arrays are persisted as JSON strings.
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
    // Converts php.ini shorthand values like 2M / 200M to bytes.
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

function handleSingleVideoUpload(array $file, int $userId, array &$errors): string
{
    // Primary intro video upload with server-aware max-size validation.
    $uploadMaxRaw = (string) ini_get('upload_max_filesize');
    $uploadMax = iniSizeToBytes($uploadMaxRaw);
    $maxSize = min(150 * 1024 * 1024, $uploadMax > 0 ? $uploadMax : 150 * 1024 * 1024);
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxSize) {
        $errors[] = "Primary intro video must be between 1 byte and {$uploadMaxRaw} on this server.";
        return '';
    }

    $originalName = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['mp4', 'mov', 'webm'];
    if (!in_array($ext, $allowed, true)) {
        $errors[] = 'Primary intro video format must be mp4, mov, or webm.';
        return '';
    }

    $uploadDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'artist_videos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Unable to prepare video upload directory.';
        return '';
    }

    $safeFile = sprintf('artist_%d_intro_%s.%s', $userId, bin2hex(random_bytes(8)), $ext);
    $target = $uploadDir . DIRECTORY_SEPARATOR . $safeFile;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        $errors[] = 'Unable to save uploaded primary intro video.';
        return '';
    }

    return 'uploads/artist_videos/' . $safeFile;
}

function handleMultipleUploads(
    array $files,
    int $userId,
    string $type,
    array $allowedExt,
    int $maxSize,
    string $dirName,
    array &$errors
): array {
    // Generic multi-file uploader used for gallery images.
    $saved = [];
    $count = count($files['name'] ?? []);
    if ($count === 0) {
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
            $errors[] = "Each {$type} file must be between 1 byte and " . (int) round($maxSize / (1024 * 1024)) . 'MB.';
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
$artistCategories = fetchArtistCategories($conn);

$userId = (int) $_SESSION['user_id'];
$errors = [];
$success = '';
$existingApplication = fetchArtistApplicationByUser($conn, $userId);

$existingIntroVideo = (string) ($existingApplication['intro_video_path'] ?? '');
$existingImagePaths = decodePathList((string) ($existingApplication['gallery_image_paths'] ?? ''));
$existingVideoPaths = decodePathList((string) ($existingApplication['portfolio_video_paths'] ?? ''));

$stageNameValue = (string) ($existingApplication['stage_name'] ?? '');
$categoryValue = (string) ($existingApplication['artist_category'] ?? '');
$experienceYearsValue = (int) ($existingApplication['experience_years'] ?? 0);
$bioValue = (string) ($existingApplication['bio'] ?? '');
$portfolioValue = (string) ($existingApplication['portfolio_url'] ?? '');
$socialLinksValue = (string) ($existingApplication['social_links'] ?? '');
$contactPreferenceValue = (string) ($existingApplication['contact_preference'] ?? '');
$isEmergingArtistValue = (int) ($existingApplication['is_emerging_artist'] ?? 0) === 1;
$uploadMaxRaw = (string) ini_get('upload_max_filesize');
$postMaxRaw = (string) ini_get('post_max_size');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Detect php.ini payload-drop behavior (POST/FILES empty on oversized request).
    $requestTooLarge = false;
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (
        $contentLength > 0 &&
        empty($_POST) &&
        empty($_FILES)
    ) {
        $errors[] = "Your upload is too large for current server limits (post_max_size={$postMaxRaw}, upload_max_filesize={$uploadMaxRaw}). Please upload a smaller file or increase php.ini limits.";
        $requestTooLarge = true;
    }

    if (!$requestTooLarge) {
        // Read and validate all business fields first.
        $stageName = trim((string) ($_POST['stage_name'] ?? ''));
        $category = trim((string) ($_POST['artist_category'] ?? ''));
        $experienceYears = max(0, (int) ($_POST['experience_years'] ?? 0));
        $bio = trim((string) ($_POST['bio'] ?? ''));
        $portfolio = trim((string) ($_POST['portfolio_url'] ?? ''));
        $socialLinksRaw = trim((string) ($_POST['social_links'] ?? ''));
        $contactPreference = trim((string) ($_POST['contact_preference'] ?? ''));
        $isEmergingArtist = isset($_POST['is_emerging_artist']) ? 1 : 0;

        $stageNameValue = $stageName;
        $categoryValue = $category;
        $experienceYearsValue = $experienceYears;
        $bioValue = $bio;
        $portfolioValue = $portfolio;
        $socialLinksValue = $socialLinksRaw;
        $contactPreferenceValue = $contactPreference;
        $isEmergingArtistValue = $isEmergingArtist === 1;

        if ($stageName === '' || $category === '' || $bio === '') {
            $errors[] = 'Stage name, category selection, and bio are required.';
        }

        if (!in_array($contactPreference, ['email', 'phone', 'whatsapp', 'any'], true)) {
            $errors[] = 'Please select a valid contact preference.';
        }

        if ($portfolio !== '' && !filter_var($portfolio, FILTER_VALIDATE_URL)) {
            $errors[] = 'Portfolio URL must be a valid URL.';
        }

        $socialLinks = [];
        if ($socialLinksRaw !== '') {
            $socialLinks = preg_split('/\r\n|\r|\n/', $socialLinksRaw) ?: [];
            $socialLinks = array_values(array_filter(array_map('trim', $socialLinks)));
            foreach ($socialLinks as $link) {
                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    $errors[] = 'Each social link must be a valid URL.';
                    break;
                }
            }
        }

        // Keep previous intro video on edit, unless a new one is uploaded.
        $introVideoPath = $existingIntroVideo;
        if (isset($_FILES['intro_video']) && is_array($_FILES['intro_video'])) {
            $introVideo = $_FILES['intro_video'];
            $introError = (int) ($introVideo['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($introError !== UPLOAD_ERR_NO_FILE && $introError !== UPLOAD_ERR_OK) {
                $errors[] = 'Failed to upload primary intro video.';
            } elseif ($introError === UPLOAD_ERR_OK) {
                $uploaded = handleSingleVideoUpload($introVideo, $userId, $errors);
                if ($uploaded !== '') {
                    $introVideoPath = $uploaded;
                }
            }
        }

        if ($introVideoPath === '') {
            $errors[] = 'Primary intro video is required.';
        }

        // New gallery images are merged with existing media paths.
        $newImagePaths = [];
        if (isset($_FILES['portfolio_images']) && is_array($_FILES['portfolio_images'])) {
            $newImagePaths = handleMultipleUploads(
                $_FILES['portfolio_images'],
                $userId,
                'image',
                ['jpg', 'jpeg', 'png', 'webp'],
                8 * 1024 * 1024,
                'artist_images',
                $errors
            );
        }

        $galleryPaths = array_values(array_unique(array_merge($existingImagePaths, $newImagePaths)));
        $portfolioVideoPaths = array_values(array_unique($existingVideoPaths));

        if (!$errors) {
            // Persist as a single application row and reset to pending review.
            if (upsertArtistApplication(
                $conn,
                $userId,
                $stageName,
                $category,
                $experienceYears,
                $bio,
                $portfolio,
                $introVideoPath,
                implode("\n", $socialLinks),
                $contactPreference,
                json_encode($galleryPaths, JSON_UNESCAPED_SLASHES),
                json_encode($portfolioVideoPaths, JSON_UNESCAPED_SLASHES),
                $isEmergingArtist
            )) {
                $success = 'Artist application submitted. Admin will review it.';
                $existingApplication = fetchArtistApplicationByUser($conn, $userId);
                $existingIntroVideo = (string) ($existingApplication['intro_video_path'] ?? '');
                $existingImagePaths = decodePathList((string) ($existingApplication['gallery_image_paths'] ?? ''));
                $existingVideoPaths = decodePathList((string) ($existingApplication['portfolio_video_paths'] ?? ''));
                $isEmergingArtistValue = (int) ($existingApplication['is_emerging_artist'] ?? 0) === 1;
            } else {
                $errors[] = 'Unable to submit artist application right now.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - Artist Application</title>
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <section class="auth-card artist-application-card" aria-labelledby="artist-title">
      <h1 id="artist-title" class="auth-title">Artist Application</h1>
      <p class="auth-subtitle">Showcase your talent with a complete profile submission.</p>
      <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="form_submitted" value="1">
        <div class="field"><label>Stage Name</label><input type="text" name="stage_name" value="<?= htmlspecialchars($stageNameValue, ENT_QUOTES, 'UTF-8') ?>" required></div>
        <div class="field">
          <label>Artist Category</label>
          <select name="artist_category" required>
            <option value="">Select category</option>
            <?php foreach ($artistCategories as $cat): ?>
              <option value="<?= htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') ?>" <?= $categoryValue === (string) $cat ? 'selected' : '' ?>><?= htmlspecialchars((string) $cat, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Experience Years</label><input type="number" name="experience_years" min="0" value="<?= (int) $experienceYearsValue ?>"></div>
        <div class="field"><label>Bio</label><textarea name="bio" rows="4" required><?= htmlspecialchars($bioValue, ENT_QUOTES, 'UTF-8') ?></textarea></div>
        <div class="field"><label>Portfolio URL</label><input type="url" name="portfolio_url" value="<?= htmlspecialchars($portfolioValue, ENT_QUOTES, 'UTF-8') ?>"></div>
        <div class="field">
          <label>Social Links (one URL per line)</label>
          <textarea name="social_links" rows="3" placeholder="https://instagram.com/yourname&#10;https://youtube.com/@yourchannel"><?= htmlspecialchars($socialLinksValue, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <div class="field">
          <label>Contact Preference</label>
          <select name="contact_preference" required>
            <option value="">Select preference</option>
            <option value="email" <?= $contactPreferenceValue === 'email' ? 'selected' : '' ?>>Email</option>
            <option value="phone" <?= $contactPreferenceValue === 'phone' ? 'selected' : '' ?>>Phone Call</option>
            <option value="whatsapp" <?= $contactPreferenceValue === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            <option value="any" <?= $contactPreferenceValue === 'any' ? 'selected' : '' ?>>Any</option>
          </select>
        </div>
        <div class="field">
          <label style="display:flex;align-items:center;gap:8px;">
            <input type="checkbox" name="is_emerging_artist" value="1" <?= $isEmergingArtistValue ? 'checked' : '' ?> style="width:auto;">
            I am an Emerging Artist (upcoming individual talent)
          </label>
        </div>
        <div class="field"><label>Primary Intro Video (mp4/mov/webm, max <?= htmlspecialchars($uploadMaxRaw, ENT_QUOTES, 'UTF-8') ?> on this server)</label><input type="file" id="intro_video" name="intro_video" accept=".mp4,.mov,.webm,video/mp4,video/webm,video/quicktime"></div>
        <div class="field"><label>Upload Images (multiple, jpg/png/webp, max 8MB each)</label><input type="file" name="portfolio_images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple></div>
        <?php if ($existingIntroVideo): ?>
          <p><strong>Current Intro Video:</strong> <a href="<?= htmlspecialchars('../' . ltrim($existingIntroVideo, '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View</a></p>
        <?php endif; ?>
        <?php if ($existingImagePaths): ?>
          <p><strong>Uploaded Images:</strong> <?= count($existingImagePaths) ?></p>
        <?php endif; ?>
        <button class="btn" type="submit">Submit Application</button>
      </form>
      <div class="bottom-text"><a class="link" href="userdashboard.php">Back to Dashboard</a></div>
    </section>
  </main>
  <script>
    (function () {
      // Client-side guard: avoid sending files that server will reject by size.
      const form = document.querySelector(".form");
      const intro = document.getElementById("intro_video");
      if (!form || !intro) {
        return;
      }
      const maxBytes = <?= (int) iniSizeToBytes((string) ini_get('upload_max_filesize')) ?>;
      form.addEventListener("submit", function (e) {
        const file = intro.files && intro.files[0] ? intro.files[0] : null;
        if (file && maxBytes > 0 && file.size > maxBytes) {
          e.preventDefault();
          alert("Intro video exceeds server upload limit (<?= htmlspecialchars($uploadMaxRaw, ENT_QUOTES, 'UTF-8') ?>). Please choose a smaller file.");
        }
      });
    })();
  </script>
</body>
</html>


