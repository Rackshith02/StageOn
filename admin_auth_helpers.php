<?php
/**
 * Project File Purpose:
 * - admin_auth_helpers.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);

// Checks whether a table exists before running schema or query operations.
function adminTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

// Checks whether a specific table column exists for backward-compatible migrations.
function adminColumnExists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

// Ensures admin/auth schema columns and audit tables are present.
function ensureAdminAuthSchema(mysqli $conn): void
{
    if (!adminTableExists($conn, 'users')) {
        return;
    }

    if (!adminColumnExists($conn, 'users', 'role')) {
        $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user'");
    }
    if (!adminColumnExists($conn, 'users', 'mobile_number')) {
        $conn->query("ALTER TABLE users ADD COLUMN mobile_number VARCHAR(20) NULL AFTER email");
    }

    $conn->query("UPDATE users SET role = 'user' WHERE role IS NULL OR role = ''");
    $conn->query(
        "CREATE TABLE IF NOT EXISTS user_auth_events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            username VARCHAR(120) NOT NULL,
            email VARCHAR(191) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            event_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_auth_events_user_id (user_id),
            KEY idx_user_auth_events_event_type (event_type),
            KEY idx_user_auth_events_event_at (event_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $adminCountResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'");
    $adminCount = (int) ($adminCountResult->fetch_assoc()['total'] ?? 0);

    if ($adminCount === 0) {
        $firstUserResult = $conn->query('SELECT user_id FROM users ORDER BY user_id ASC LIMIT 1');
        $firstUser = $firstUserResult instanceof mysqli_result ? $firstUserResult->fetch_assoc() : null;
        if ($firstUser && isset($firstUser['user_id'])) {
            $firstId = (int) $firstUser['user_id'];
            $promote = $conn->prepare("UPDATE users SET role = 'admin' WHERE user_id = ? LIMIT 1");
            $promote->bind_param('i', $firstId);
            $promote->execute();
            $promote->close();
        }
    }
}

// Persists login/register activity for admin audit tracking.
function logAuthEvent(mysqli $conn, string $eventType, ?int $userId, string $username, string $email): void
{
    if (!adminTableExists($conn, 'user_auth_events')) {
        return;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $uid = $userId !== null ? $userId : null;

    $stmt = $conn->prepare(
        'INSERT INTO user_auth_events (user_id, username, email, event_type, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $uid, $username, $email, $eventType, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}

// Reads the effective role for a user account.
function fetchUserRole(mysqli $conn, int $userId): string
{
    if (!adminColumnExists($conn, 'users', 'role')) {
        return 'user';
    }

    $stmt = $conn->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (string) ($row['role'] ?? 'user');
}

// Enforces authenticated admin access and blocks non-admin users.
function requireAdminAccess(mysqli $conn): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
// Section: Initialize Session/User Context
    }

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    ensureAdminAuthSchema($conn);

    $userId = (int) $_SESSION['user_id'];
    $role = fetchUserRole($conn, $userId);
    $_SESSION['role'] = $role;

    if ($role !== 'admin') {
        http_response_code(403);
        echo '<h1>403 - Admin access only</h1><p>You do not have permission to view this page.</p>';
        exit;
    }
}

// Aggregates high-level counts displayed on the admin dashboard.
function fetchAdminStats(mysqli $conn): array
{
    $stats = [
        'total_users' => 0,
        'verified_users' => 0,
        'pending_verifications' => 0,
        'logins_today' => 0,
        'registrations_today' => 0,
        'admin_users' => 0,
        'pending_artist_applications' => 0,
        'total_bookings' => 0,
        'total_vacancies' => 0,
        'total_ads' => 0,
    ];

    $stats['total_users'] = (int) (($conn->query('SELECT COUNT(*) AS total FROM users')->fetch_assoc()['total']) ?? 0);
    if (adminColumnExists($conn, 'users', 'email_verified_at')) {
        $stats['verified_users'] = (int) (($conn->query('SELECT COUNT(*) AS total FROM users WHERE email_verified_at IS NOT NULL')->fetch_assoc()['total']) ?? 0);
        $stats['pending_verifications'] = (int) (($conn->query('SELECT COUNT(*) AS total FROM users WHERE email_verified_at IS NULL')->fetch_assoc()['total']) ?? 0);
    } else {
        $stats['verified_users'] = $stats['total_users'];
        $stats['pending_verifications'] = 0;
    }
    $stats['admin_users'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'admin'")->fetch_assoc()['total']) ?? 0);

    if (adminTableExists($conn, 'user_auth_events')) {
        $stats['logins_today'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM user_auth_events WHERE event_type = 'login' AND DATE(event_at) = CURDATE()")->fetch_assoc()['total']) ?? 0);
        $stats['registrations_today'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM user_auth_events WHERE event_type = 'register' AND DATE(event_at) = CURDATE()")->fetch_assoc()['total']) ?? 0);
    }

    if (adminTableExists($conn, 'artist_applications')) {
        $stats['pending_artist_applications'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM artist_applications WHERE status = 'pending'")->fetch_assoc()['total']) ?? 0);
    }
    if (adminTableExists($conn, 'event_bookings')) {
        $stats['total_bookings'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM event_bookings")->fetch_assoc()['total']) ?? 0);
    }
    if (adminTableExists($conn, 'vacancy_posts')) {
        $stats['total_vacancies'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM vacancy_posts")->fetch_assoc()['total']) ?? 0);
    }
    if (adminTableExists($conn, 'ad_posts')) {
        $stats['total_ads'] = (int) (($conn->query("SELECT COUNT(*) AS total FROM ad_posts")->fetch_assoc()['total']) ?? 0);
    }

    return $stats;
}

// Returns latest users for admin user-management views.
function fetchUsersForAdmin(mysqli $conn, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $verifiedSelect = adminColumnExists($conn, 'users', 'email_verified_at')
        ? 'email_verified_at'
        : 'NULL AS email_verified_at';
    $result = $conn->query(
        "SELECT user_id, username, email, role, {$verifiedSelect}
         FROM users
         ORDER BY user_id DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Returns recent authentication events for admin monitoring.
function fetchRecentAuthEvents(mysqli $conn, int $limit = 20): array
{
    if (!adminTableExists($conn, 'user_auth_events')) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $result = $conn->query(
        "SELECT event_id, user_id, username, email, event_type, ip_address, event_at
         FROM user_auth_events
         ORDER BY event_id DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Returns candidate users for verification-related admin tools.
function fetchPendingVerificationUsers(mysqli $conn, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $result = $conn->query(
        "SELECT user_id, username, email, role
         FROM users
         ORDER BY user_id DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

// Filters audit events by event type for reporting panels.
function fetchAuthEventsByType(mysqli $conn, string $eventType, int $limit = 50): array
{
    if (!adminTableExists($conn, 'user_auth_events')) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $stmt = $conn->prepare(
        'SELECT event_id, user_id, username, email, event_type, ip_address, event_at
         FROM user_auth_events
         WHERE event_type = ?
         ORDER BY event_id DESC
         LIMIT ?'
    );
    $stmt->bind_param('si', $eventType, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $rows;
}

// Returns role-wise user counts for quick admin analytics.
function fetchRoleDistribution(mysqli $conn): array
{
    $result = $conn->query(
        "SELECT role, COUNT(*) AS total
         FROM users
         GROUP BY role
         ORDER BY total DESC"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}


