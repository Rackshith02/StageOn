<?php
/**
 * Project File Purpose:
 * - admin_layout.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/admin_auth_helpers.php';
require_once __DIR__ . '/portal_workflow_helpers.php';

// Bootstraps workflow schema and enforces admin session access.
function adminRequireLogin(): void
{
    global $conn;
    ensurePortalWorkflowSchema($conn);
// Section: Load/Ensure Workflow Data
    requireAdminAccess($conn);
}

// Defines reusable left-sidebar menu links for all admin pages.
function adminMenuItems(): array
{
    return [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'Admindashboard.php'],
        'users' => ['label' => 'User Management', 'href' => 'user_management.php'],
        'artist_approvals' => ['label' => 'Artist Approval Management', 'href' => 'artist_approval_management.php'],
        'bookings' => ['label' => 'Booking Management', 'href' => 'booking_management.php'],
        'vacancies' => ['label' => 'Vacancy Management', 'href' => 'vacancy_management.php'],
        'ads' => ['label' => 'Ads Management', 'href' => 'ads_management.php'],
        'system' => ['label' => 'System Settings', 'href' => 'system_settings.php'],
    ];
}

// Renders shared admin shell: sidebar, topbar, and optional tab search.
function renderAdminPageStart(string $activeKey, string $title): void
{
    $username = (string) ($_SESSION['username'] ?? 'Admin');
    $email = (string) ($_SESSION['email'] ?? '');
    $searchQuery = trim((string) ($_GET['q'] ?? ''));
    $menu = adminMenuItems();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>StageOn - <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
  <div class="dashboard">
    <aside class="sidebar">
      <img class="brand-logo" src="../assets/img/StageOnLogo.png" alt="StageOn">

      <nav class="menu">
        <?php foreach ($menu as $key => $item): ?>
          <a
            class="menu-item <?= $key === $activeKey ? 'active' : '' ?>"
            href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
          >
            <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <a class="logout-btn" href="logout.php">Logout</a>
    </aside>

    <main class="main">
      <header class="topbar">
        <div>
          <p class="label">Admin Panel</p>
          <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        </div>
        <div class="user-chip">
          <img src="../assets/img/user profile.png" alt="Profile">
          <div>
            <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        </div>
      </header>
      <?php if ($activeKey !== 'dashboard'): ?>
        <form class="admin-search" method="get">
          <input
            class="admin-search-input"
            type="search"
            name="q"
            value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Search in this tab"
            aria-label="Search in this tab"
          >
          <button type="submit">Search</button>
        </form>
      <?php endif; ?>
<?php
}

// Closes shared shell and binds client-side search filtering in admin tables.
function renderAdminPageEnd(): void
{
    ?>
    <script>
      (function () {
        const searchInput = document.querySelector(".admin-search-input");
        if (!searchInput) {
          return;
        }

        const items = [
          ...document.querySelectorAll(".admin-table tbody tr"),
          ...document.querySelectorAll(".settings-card"),
          ...document.querySelectorAll(".request-item"),
          ...document.querySelectorAll(".event-list li"),
          ...document.querySelectorAll(".actions .action-link")
        ];

        if (!items.length) {
          return;
        }

        function applyFilter(query) {
          const normalized = query.trim().toLowerCase();
          items.forEach(function (item) {
            const text = (item.textContent || "").toLowerCase();
            const isMatch = normalized === "" || text.includes(normalized);
            item.style.display = isMatch ? "" : "none";
          });
        }

        applyFilter(searchInput.value);
        searchInput.addEventListener("input", function () {
          applyFilter(searchInput.value);
        });
      })();
    </script>
    </main>
  </div>
</body>
</html>
<?php
}


