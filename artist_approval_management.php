<?php
/**
 * Project File Purpose:
 * - artist_approval_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

// Guard admin-only access before handling approvals.
adminRequireLogin();
global $conn;

// Handle approve/reject actions submitted from the table.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
    if ($applicationId > 0 && in_array($decision, ['approved', 'rejected'], true)) {
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        reviewArtistApplication($conn, $applicationId, $adminId, $decision, $reviewNote);
    }
}

// Load pending applications for review queue rendering.
$pending = fetchPendingArtistApplications($conn, 80);
renderAdminPageStart('artist_approvals', 'Artist Status Approval Requests');
?>
<!-- Section: Pending Artist Review Queue -->
<section class="card panel">
  <div class="panel-head">
    <h3>Pending Artist Applications</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Applicant</th>
        <th>Email</th>
        <th>Stage Name</th>
        <th>Category</th>
        <th>Emerging</th>
        <th>Experience</th>
        <th>Social</th>
        <th>Contact</th>
        <th>Images</th>
        <th>Video</th>
        <th>More Videos</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$pending): ?>
        <tr><td colspan="12">No pending artist applications.</td></tr>
      <?php else: ?>
        <?php foreach ($pending as $row): ?>
          <?php
            $socialLinks = preg_split('/\r\n|\r|\n/', (string) ($row['social_links'] ?? '')) ?: [];
            $socialLinks = array_values(array_filter(array_map('trim', $socialLinks)));
            $galleryImages = json_decode((string) ($row['gallery_image_paths'] ?? ''), true);
            $galleryImages = is_array($galleryImages) ? array_values(array_filter(array_map('strval', $galleryImages))) : [];
            $portfolioVideos = json_decode((string) ($row['portfolio_video_paths'] ?? ''), true);
            $portfolioVideos = is_array($portfolioVideos) ? array_values(array_filter(array_map('strval', $portfolioVideos))) : [];
          ?>
          <tr>
            <td><?= htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['stage_name'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['artist_category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) ($row['is_emerging_artist'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
            <td><?= (int) $row['experience_years'] ?> years</td>
            <td>
              <?php if ($socialLinks): ?>
                <a href="<?= htmlspecialchars((string) $socialLinks[0], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open</a>
              <?php else: ?>
                Not provided
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ucfirst((string) ($row['contact_preference'] ?? 'n/a')), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= count($galleryImages) ?></td>
            <td>
              <?php if (!empty($row['intro_video_path'])): ?>
                <a href="<?= htmlspecialchars('../' . ltrim((string) $row['intro_video_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View Video</a>
              <?php else: ?>
                Not uploaded
              <?php endif; ?>
            </td>
            <td><?= count($portfolioVideos) ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <input type="hidden" name="application_id" value="<?= (int) $row['application_id'] ?>">
                <input type="text" name="review_note" placeholder="Optional note" style="max-width:160px;">
                <button type="submit" name="decision" value="approved">Approve</button>
                <button type="submit" name="decision" value="rejected">Reject</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php renderAdminPageEnd(); ?>


