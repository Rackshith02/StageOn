<?php
/**
 * Project File Purpose:
 * - ads_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

// Guard admin-only access before review actions.
adminRequireLogin();
global $conn;

// Handle ad approval/rejection submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adId = (int) ($_POST['ad_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
    if ($adId > 0 && in_array($decision, ['approved', 'rejected'], true)) {
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        reviewAdPost($conn, $adId, $adminId, $decision, $reviewNote);
    }
}

// Load advertisement posts for moderation.
$ads = fetchAdPostsForAdmin($conn, 120);
renderAdminPageStart('ads', 'Ads Management');
?>
<!-- Section: Advertisement Moderation Table -->
<section class="card panel">
  <div class="panel-head">
    <h3>Advertisement Posts</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>User</th>
        <th>Type</th>
        <th>Title</th>
        <th>Poster</th>
        <th>Status</th>
        <th>Start</th>
        <th>End</th>
        <th>Email</th>
        <th>Time</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$ads): ?>
        <tr><td colspan="10">No ads posted yet.</td></tr>
      <?php else: ?>
        <?php foreach ($ads as $event): ?>
          <tr>
            <td><?= htmlspecialchars((string) $event['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $event['posted_by_type']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['ad_title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if (!empty($event['poster_path'])): ?>
                <a href="<?= htmlspecialchars('../' . ltrim((string) $event['poster_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View Poster</a>
              <?php else: ?>
                Not uploaded
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) ucfirst((string) $event['status']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($event['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($event['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <input type="hidden" name="ad_id" value="<?= (int) $event['ad_id'] ?>">
                <input type="text" name="review_note" placeholder="Note" style="max-width:110px;">
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


