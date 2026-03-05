<?php
/**
 * Project File Purpose:
 * - system_settings.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

adminRequireLogin();
global $conn;
$stats = fetchAdminStats($conn);
renderAdminPageStart('system', 'System Settings');
?>
<section class="settings-grid">
  <article class="card settings-card">
    <h3>Platform Settings</h3>
    <label>Platform Name<input type="text" value="StageOn"></label>
    <label>Support Email<input type="email" value="support@stageon.lk"></label>
    <label>Default Timezone<input type="text" value="Asia/Colombo"></label>
  </article>

  <article class="card settings-card">
    <h3>Authentication & Security</h3>
    <label>Minimum Password Length<input type="number" value="8"></label>
    <label>Session Timeout (minutes)<input type="number" value="60"></label>
    <label>Require Email Verification
      <select><option selected>Enabled</option><option>Disabled</option></select>
    </label>
  </article>

  <article class="card settings-card">
    <h3>Content Moderation</h3>
    <label>Auto-approve Artist Profiles
      <select><option>Enabled</option><option selected>Disabled</option></select>
    </label>
    <label>Auto-approve Advertisements
      <select><option>Enabled</option><option selected>Disabled</option></select>
    </label>
    <label>Report Threshold for Auto-Hide<input type="number" value="5"></label>
  </article>

  <article class="card settings-card">
    <h3>Maintenance</h3>
    <label>Maintenance Mode
      <select><option selected>Disabled</option><option>Enabled</option></select>
    </label>
    <label>Maintenance Message<input type="text" value="We are upgrading StageOn. Please check back shortly."></label>
    <button type="button">Run Backup</button>
  </article>

  <article class="card settings-card">
    <h3>System Actions</h3>
    <button type="button">Save All Settings</button>
    <button type="button">Run Health Check</button>
    <button type="button">Clear Application Cache</button>
    <p><strong>Total Users:</strong> <?= (int) $stats['total_users'] ?></p>
    <p><strong>Verified Users:</strong> <?= (int) $stats['verified_users'] ?></p>
    <p><strong>Admin Users:</strong> <?= (int) $stats['admin_users'] ?></p>
  </article>
</section>
<?php renderAdminPageEnd(); ?>


