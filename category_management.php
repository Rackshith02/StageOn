<?php
/**
 * Project File Purpose:
 * - category_management.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

adminRequireLogin();
global $conn;
$roles = fetchRoleDistribution($conn);
renderAdminPageStart('categories', 'Category Management');
?>
<section class="card panel">
  <div class="panel-head">
    <h3>User Role Distribution</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Role</th>
        <th>Total Users</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$roles): ?>
        <tr><td colspan="2">No role distribution data available.</td></tr>
      <?php else: ?>
        <?php foreach ($roles as $row): ?>
          <tr>
            <td><?= htmlspecialchars((string) ucfirst((string) $row['role']), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $row['total'] ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php renderAdminPageEnd(); ?>


