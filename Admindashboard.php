<?php
/**
 * Project File Purpose:
 * - Admindashboard.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/admin_layout.php';

// Guard admin-only access to the dashboard.
adminRequireLogin();
global $conn;
// Aggregate top-level platform metrics for KPI cards.
$stats = fetchAdminStats($conn);
// Fetch recent authentication audit events for admin visibility.
$recentEvents = fetchRecentAuthEvents($conn, 8);
renderAdminPageStart('dashboard', 'Dashboard');
?>
<!-- Section: Dashboard KPI Cards -->
<section class="stats admin-stats">
  <article class="card stat-card">
    <p>Total Platform Users</p>
    <h2><?= (int) $stats['total_users'] ?></h2>
    <small>Registered accounts</small>
  </article>
  <article class="card stat-card">
    <p>Verified Users</p>
    <h2><?= (int) $stats['verified_users'] ?></h2>
    <small>Email verified accounts</small>
  </article>
  <article class="card stat-card">
    <p>Pending Artist Applications</p>
    <h2><?= (int) $stats['pending_artist_applications'] ?></h2>
    <small>Need admin review</small>
  </article>
  <article class="card stat-card">
    <p>Total Event Bookings</p>
    <h2><?= (int) $stats['total_bookings'] ?></h2>
    <small>User and artist bookings</small>
  </article>
  <article class="card stat-card">
    <p>Total Vacancies</p>
    <h2><?= (int) $stats['total_vacancies'] ?></h2>
    <small>Posted vacancies</small>
  </article>
  <article class="card stat-card">
    <p>Total Ads</p>
    <h2><?= (int) $stats['total_ads'] ?></h2>
    <small>Posted ads</small>
  </article>
</section>

<!-- Section: Quick Admin Navigation -->
<section class="card panel quick-links">
  <div class="panel-head">
    <h3>Quick Navigation</h3>
  </div>
  <div class="actions">
    <a class="action-link" href="user_management.php">Open User Management</a>
    <a class="action-link" href="artist_approval_management.php">Open Artist Approvals</a>
    <a class="action-link" href="booking_management.php">Open Bookings</a>
    <a class="action-link" href="vacancy_management.php">Open Vacancies</a>
    <a class="action-link" href="ads_management.php">Open Ads</a>
    <a class="action-link" href="system_settings.php">Open System Settings</a>
  </div>
</section>

<!-- Section: Recent Authentication Activity -->
<section class="card panel">
  <div class="panel-head">
    <h3>Recent Auth Activity</h3>
  </div>
  <table class="admin-table">
    <thead>
      <tr>
        <th>Type</th>
        <th>User</th>
        <th>Email</th>
        <th>IP</th>
        <th>Time</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$recentEvents): ?>
        <tr><td colspan="5">No auth events recorded yet.</td></tr>
      <?php else: ?>
        <?php foreach ($recentEvents as $event): ?>
          <tr>
            <td><?= htmlspecialchars((string) $event['event_type'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['username'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['email'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) $event['event_at'], ENT_QUOTES, 'UTF-8') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php renderAdminPageEnd(); ?>


