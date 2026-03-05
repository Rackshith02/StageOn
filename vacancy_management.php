<?php
/**
 * Project File Purpose:
 * - vacancy_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

// Guard admin-only access before review actions.
adminRequireLogin();
global $conn;

// Handle vacancy approval/rejection submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vacancyId = (int) ($_POST['vacancy_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $reviewNote = trim((string) ($_POST['review_note'] ?? ''));
    if ($vacancyId > 0 && in_array($decision, ['approved', 'rejected'], true)) {
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        reviewVacancyPost($conn, $vacancyId, $adminId, $decision, $reviewNote);
    }
}

// Load vacancy posts for admin moderation.
$vacancies = fetchVacancyPostsForAdmin($conn, 120);
renderAdminPageStart('vacancies', 'Vacancy Management');
?>
<!-- Section: Vacancy Moderation Table -->
<section class="card panel">
  <div class="panel-head">
    <h3>Vacancy Posts</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>User</th>
        <th>Type</th>
        <th>Title</th>
        <th>Category</th>
        <th>Poster</th>
        <th>Location</th>
        <th>Status</th>
        <th>Email</th>
        <th>Time</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$vacancies): ?>
        <tr><td colspan="10">No vacancy posts yet.</td></tr>
      <?php else: ?>
        <?php foreach ($vacancies as $row): ?>
          <tr>
            <td><?= htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $row['posted_by_type']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['category'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if (!empty($row['poster_path'])): ?>
                <a href="<?= htmlspecialchars('../' . ltrim((string) $row['poster_path'], '/'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View Poster</a>
              <?php else: ?>
                Not uploaded
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string) $row['location'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $row['status']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <form method="post" style="display:flex; gap:6px; align-items:center;">
                <input type="hidden" name="vacancy_id" value="<?= (int) $row['vacancy_id'] ?>">
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


