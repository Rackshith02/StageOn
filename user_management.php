<?php
/**
 * Project File Purpose:
 * - user_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

adminRequireLogin();
global $conn;
$users = fetchUsersForAdmin($conn, 100);
renderAdminPageStart('users', 'User Management');
?>
<section class="card panel">
  <div class="panel-head">
    <h3>All Users</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>User</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="5">No users found.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $user): ?>
          <?php $status = !empty($user['email_verified_at']) ? 'Verified' : 'Unverified'; ?>
          <tr>
            <td><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ucfirst((string) $user['role']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>
            <td><button type="button">User #<?= (int) $user['user_id'] ?></button></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php renderAdminPageEnd(); ?>


