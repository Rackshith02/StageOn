<?php
/**
 * Project File Purpose:
 * - portal_workflow_helpers.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);

// Shared workflow/data-layer helpers for user/admin flows:
// - schema migration
// - create/update actions
// - admin review actions
// - public listing/read APIs

function workflowTableExists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function workflowColumnExists(mysqli $conn, string $table, string $column): bool
{
    if (!workflowTableExists($conn, $table)) {
        return false;
    }
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function ensurePortalWorkflowSchema(mysqli $conn): void
{
    // Core artist application table (single application per user).
    $conn->query(
        "CREATE TABLE IF NOT EXISTS artist_applications (
            application_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            stage_name VARCHAR(120) NOT NULL,
            artist_category VARCHAR(80) NOT NULL,
            experience_years INT NOT NULL DEFAULT 0,
            bio TEXT NOT NULL,
            portfolio_url VARCHAR(255) NULL,
            intro_video_path VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL DEFAULT NULL,
            reviewed_by INT NULL DEFAULT NULL,
            review_note VARCHAR(255) NULL,
            UNIQUE KEY uniq_artist_application_user_id (user_id),
            KEY idx_artist_application_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // User -> admin reviewed event booking requests.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS event_bookings (
            booking_id INT AUTO_INCREMENT PRIMARY KEY,
            requested_by_user_id INT NOT NULL,
            requested_by_type VARCHAR(20) NOT NULL DEFAULT 'user',
            event_title VARCHAR(150) NOT NULL,
            event_date DATE NOT NULL,
            location VARCHAR(150) NOT NULL,
            budget DECIMAL(10,2) NULL,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL DEFAULT NULL,
            reviewed_by INT NULL DEFAULT NULL,
            review_note VARCHAR(255) NULL,
            KEY idx_event_bookings_status (status),
            KEY idx_event_bookings_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // User/artist -> admin reviewed vacancy posts.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS vacancy_posts (
            vacancy_id INT AUTO_INCREMENT PRIMARY KEY,
            posted_by_user_id INT NOT NULL,
            posted_by_type VARCHAR(20) NOT NULL DEFAULT 'user',
            title VARCHAR(150) NOT NULL,
            category VARCHAR(80) NOT NULL,
            location VARCHAR(150) NOT NULL,
            description TEXT NOT NULL,
            poster_path VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL DEFAULT NULL,
            reviewed_by INT NULL DEFAULT NULL,
            review_note VARCHAR(255) NULL,
            KEY idx_vacancy_posts_status (status),
            KEY idx_vacancy_posts_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // User/artist -> admin reviewed advertisement posts.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS ad_posts (
            ad_id INT AUTO_INCREMENT PRIMARY KEY,
            posted_by_user_id INT NOT NULL,
            posted_by_type VARCHAR(20) NOT NULL DEFAULT 'user',
            ad_title VARCHAR(150) NOT NULL,
            ad_content TEXT NOT NULL,
            poster_path VARCHAR(255) NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL DEFAULT NULL,
            reviewed_by INT NULL DEFAULT NULL,
            review_note VARCHAR(255) NULL,
            KEY idx_ad_posts_status (status),
            KEY idx_ad_posts_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Social/fan workflow tables.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS artist_follows (
            follow_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            artist_user_id INT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_artist_follow (user_id, artist_user_id),
            KEY idx_artist_follows_artist_user_id (artist_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS artist_reviews (
            review_id INT AUTO_INCREMENT PRIMARY KEY,
            artist_user_id INT NOT NULL,
            reviewer_user_id INT NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            review_text TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_artist_reviews_artist_user_id (artist_user_id),
            KEY idx_artist_reviews_reviewer_user_id (reviewer_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $conn->query(
        "CREATE TABLE IF NOT EXISTS artist_categories (
            category_id INT AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(80) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    // Vacancy applications submitted by users.
    $conn->query(
        "INSERT IGNORE INTO artist_categories (category_name, is_active, sort_order) VALUES
         ('Singers', 1, 10),
         ('Bands', 1, 20),
         ('Actors', 1, 30),
         ('Dancers', 1, 40),
         ('Photographers', 1, 50),
         ('Instrumentalists', 1, 60)"
    );

    // Central category master used by artist application/filter UI.
    $conn->query(
        "CREATE TABLE IF NOT EXISTS hire_requests (
            request_id INT AUTO_INCREMENT PRIMARY KEY,
            artist_user_id INT NOT NULL,
            requester_user_id INT NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_hire_requests_artist_user_id (artist_user_id),
            KEY idx_hire_requests_requester_user_id (requester_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Seed default categories (idempotent).
    $conn->query(
        "CREATE TABLE IF NOT EXISTS vacancy_applications (
            application_id INT AUTO_INCREMENT PRIMARY KEY,
            vacancy_id INT NOT NULL,
            applicant_user_id INT NOT NULL,
            cover_note TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_vacancy_application (vacancy_id, applicant_user_id),
            KEY idx_vacancy_applications_applicant_user_id (applicant_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backward-compatible column migrations for existing databases.
    if (!workflowColumnExists($conn, 'artist_applications', 'intro_video_path')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN intro_video_path VARCHAR(255) NULL AFTER portfolio_url');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'social_links')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN social_links TEXT NULL AFTER intro_video_path');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'contact_preference')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN contact_preference VARCHAR(40) NULL AFTER social_links');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'gallery_image_paths')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN gallery_image_paths TEXT NULL AFTER contact_preference');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'portfolio_video_paths')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN portfolio_video_paths TEXT NULL AFTER gallery_image_paths');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'profile_image_path')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN profile_image_path VARCHAR(255) NULL AFTER portfolio_video_paths');
    }
    if (!workflowColumnExists($conn, 'artist_applications', 'is_emerging_artist')) {
        $conn->query('ALTER TABLE artist_applications ADD COLUMN is_emerging_artist TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_image_path');
    }
    if (!workflowColumnExists($conn, 'event_bookings', 'reviewed_at')) {
        $conn->query('ALTER TABLE event_bookings ADD COLUMN reviewed_at DATETIME NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'event_bookings', 'reviewed_by')) {
        $conn->query('ALTER TABLE event_bookings ADD COLUMN reviewed_by INT NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'event_bookings', 'review_note')) {
        $conn->query('ALTER TABLE event_bookings ADD COLUMN review_note VARCHAR(255) NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'vacancy_posts', 'reviewed_at')) {
        $conn->query('ALTER TABLE vacancy_posts ADD COLUMN reviewed_at DATETIME NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'vacancy_posts', 'poster_path')) {
        $conn->query('ALTER TABLE vacancy_posts ADD COLUMN poster_path VARCHAR(255) NULL AFTER description');
    }
    if (!workflowColumnExists($conn, 'vacancy_posts', 'reviewed_by')) {
        $conn->query('ALTER TABLE vacancy_posts ADD COLUMN reviewed_by INT NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'vacancy_posts', 'review_note')) {
        $conn->query('ALTER TABLE vacancy_posts ADD COLUMN review_note VARCHAR(255) NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'ad_posts', 'reviewed_at')) {
        $conn->query('ALTER TABLE ad_posts ADD COLUMN reviewed_at DATETIME NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'ad_posts', 'poster_path')) {
        $conn->query('ALTER TABLE ad_posts ADD COLUMN poster_path VARCHAR(255) NULL AFTER ad_content');
    }
    if (!workflowColumnExists($conn, 'ad_posts', 'reviewed_by')) {
        $conn->query('ALTER TABLE ad_posts ADD COLUMN reviewed_by INT NULL DEFAULT NULL');
    }
    if (!workflowColumnExists($conn, 'ad_posts', 'review_note')) {
        $conn->query('ALTER TABLE ad_posts ADD COLUMN review_note VARCHAR(255) NULL DEFAULT NULL');
    }
}

function fetchArtistApplicationByUser(mysqli $conn, int $userId): ?array
{
    // Returns the latest/current single application row for this user.
    $stmt = $conn->prepare(
        'SELECT application_id, stage_name, artist_category, experience_years, bio, portfolio_url, status, submitted_at, reviewed_at, review_note,
         intro_video_path, social_links, contact_preference, gallery_image_paths, portfolio_video_paths, profile_image_path, is_emerging_artist
         FROM artist_applications
         WHERE user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function isApprovedArtist(mysqli $conn, int $userId): bool
{
    $stmt = $conn->prepare(
        "SELECT application_id
         FROM artist_applications
         WHERE user_id = ? AND status = 'approved'
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $ok = $result->num_rows > 0;
    $stmt->close();
    return $ok;
}

function upsertArtistApplication(
    mysqli $conn,
    int $userId,
    string $stageName,
    string $category,
    int $experienceYears,
    string $bio,
    string $portfolioUrl,
    string $introVideoPath,
    string $socialLinks,
    string $contactPreference,
    string $galleryImagePaths,
    string $portfolioVideoPaths,
    int $isEmergingArtist
): bool {
    // Single-row upsert by user_id; every re-submit resets status to pending.
    $stmt = $conn->prepare(
        "INSERT INTO artist_applications (user_id, stage_name, artist_category, experience_years, bio, portfolio_url, intro_video_path, social_links, contact_preference, gallery_image_paths, portfolio_video_paths, is_emerging_artist, status, submitted_at, reviewed_at, reviewed_by, review_note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NULL, NULL, NULL)
         ON DUPLICATE KEY UPDATE
            stage_name = VALUES(stage_name),
            artist_category = VALUES(artist_category),
            experience_years = VALUES(experience_years),
            bio = VALUES(bio),
            portfolio_url = VALUES(portfolio_url),
            intro_video_path = VALUES(intro_video_path),
            social_links = VALUES(social_links),
            contact_preference = VALUES(contact_preference),
            gallery_image_paths = VALUES(gallery_image_paths),
            portfolio_video_paths = VALUES(portfolio_video_paths),
            is_emerging_artist = VALUES(is_emerging_artist),
            status = 'pending',
            submitted_at = NOW(),
            reviewed_at = NULL,
            reviewed_by = NULL,
            review_note = NULL"
    );
    $stmt->bind_param(
        'ississsssssi',
        $userId,
        $stageName,
        $category,
        $experienceYears,
        $bio,
        $portfolioUrl,
        $introVideoPath,
        $socialLinks,
        $contactPreference,
        $galleryImagePaths,
        $portfolioVideoPaths,
        $isEmergingArtist
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createEventBooking(
    mysqli $conn,
    int $userId,
    string $requestedByType,
    string $eventTitle,
    string $eventDate,
    string $location,
    ?float $budget,
    string $notes
): bool {
    // Event booking always starts as pending for admin review.
    $stmt = $conn->prepare(
        'INSERT INTO event_bookings (requested_by_user_id, requested_by_type, event_title, event_date, location, budget, notes, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->bind_param('issssds', $userId, $requestedByType, $eventTitle, $eventDate, $location, $budget, $notes);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createVacancyPost(
    mysqli $conn,
    int $userId,
    string $postedByType,
    string $title,
    string $category,
    string $location,
    string $description,
    string $posterPath
): bool {
    // Vacancy always starts as pending and becomes public only after approval.
    $stmt = $conn->prepare(
        'INSERT INTO vacancy_posts (posted_by_user_id, posted_by_type, title, category, location, description, poster_path, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->bind_param('issssss', $userId, $postedByType, $title, $category, $location, $description, $posterPath);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createAdPost(
    mysqli $conn,
    int $userId,
    string $postedByType,
    string $title,
    string $content,
    string $posterPath,
    ?string $startDate,
    ?string $endDate
): bool {
    // Ad always starts as pending and becomes public only after approval.
    $stmt = $conn->prepare(
        'INSERT INTO ad_posts (posted_by_user_id, posted_by_type, ad_title, ad_content, poster_path, start_date, end_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
    );
    $stmt->bind_param('issssss', $userId, $postedByType, $title, $content, $posterPath, $startDate, $endDate);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchPendingArtistApplications(mysqli $conn, int $limit = 50): array
{
    // Admin queue: only pending artist applications from non-admin users.
    $limit = max(1, min(200, $limit));
    $result = $conn->query(
        "SELECT a.application_id, a.user_id, a.stage_name, a.artist_category, a.experience_years, a.bio, a.portfolio_url, a.intro_video_path, a.social_links, a.contact_preference, a.gallery_image_paths, a.portfolio_video_paths, a.is_emerging_artist, a.status, a.submitted_at,
                u.username, u.email, u.role
         FROM artist_applications a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.status = 'pending' AND u.role <> 'admin'
         ORDER BY a.submitted_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function reviewEventBooking(mysqli $conn, int $bookingId, int $adminId, string $decision, string $note): bool
{
    // Admin decision: approved/rejected.
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return false;
    }
    $stmt = $conn->prepare(
        'UPDATE event_bookings
         SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_note = ?
         WHERE booking_id = ?'
    );
    $stmt->bind_param('sisi', $decision, $adminId, $note, $bookingId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function reviewArtistApplication(mysqli $conn, int $applicationId, int $adminId, string $decision, string $note): bool
{
    // Admin decision: approved/rejected.
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return false;
    }

    $stmt = $conn->prepare(
        'UPDATE artist_applications
         SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_note = ?
         WHERE application_id = ?'
    );
    $stmt->bind_param('sisi', $decision, $adminId, $note, $applicationId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchEventBookingsForAdmin(mysqli $conn, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT b.booking_id, b.requested_by_type, b.event_title, b.event_date, b.location, b.budget, b.status, b.created_at,
                u.username, u.email
         FROM event_bookings b
         INNER JOIN users u ON u.user_id = b.requested_by_user_id
         ORDER BY b.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function reviewVacancyPost(mysqli $conn, int $vacancyId, int $adminId, string $decision, string $note): bool
{
    // Admin decision: approved/rejected.
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return false;
    }
    $stmt = $conn->prepare(
        'UPDATE vacancy_posts
         SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_note = ?
         WHERE vacancy_id = ?'
    );
    $stmt->bind_param('sisi', $decision, $adminId, $note, $vacancyId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchVacancyPostsForAdmin(mysqli $conn, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT v.vacancy_id, v.posted_by_type, v.title, v.category, v.location, v.poster_path, v.status, v.created_at,
                u.username, u.email
         FROM vacancy_posts v
         INNER JOIN users u ON u.user_id = v.posted_by_user_id
         ORDER BY v.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function reviewAdPost(mysqli $conn, int $adId, int $adminId, string $decision, string $note): bool
{
    // Admin decision: approved/rejected.
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return false;
    }
    $stmt = $conn->prepare(
        'UPDATE ad_posts
         SET status = ?, reviewed_at = NOW(), reviewed_by = ?, review_note = ?
         WHERE ad_id = ?'
    );
    $stmt->bind_param('sisi', $decision, $adminId, $note, $adId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchAdPostsForAdmin(mysqli $conn, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT a.ad_id, a.posted_by_type, a.ad_title, a.poster_path, a.status, a.start_date, a.end_date, a.created_at,
                u.username, u.email
         FROM ad_posts a
         INNER JOIN users u ON u.user_id = a.posted_by_user_id
         ORDER BY a.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchApprovedArtists(mysqli $conn, string $category = '', int $limit = 100): array
{
    // Public list endpoint used by artists/explore/category pages.
    $limit = max(1, min(300, $limit));
    $category = trim($category);

    if ($category !== '') {
        $stmt = $conn->prepare(
            "SELECT a.user_id, a.stage_name, a.artist_category, a.experience_years, a.bio, a.portfolio_url, a.intro_video_path, a.social_links, a.contact_preference, a.profile_image_path, a.is_emerging_artist,
                    u.username, u.email
             FROM artist_applications a
             INNER JOIN users u ON u.user_id = a.user_id
             WHERE a.status = 'approved' AND a.artist_category = ?
             ORDER BY a.submitted_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('si', $category, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    $result = $conn->query(
        "SELECT a.user_id, a.stage_name, a.artist_category, a.experience_years, a.bio, a.portfolio_url, a.intro_video_path, a.social_links, a.contact_preference, a.profile_image_path, a.is_emerging_artist,
                u.username, u.email
         FROM artist_applications a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.status = 'approved'
         ORDER BY a.submitted_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchArtistCategories(mysqli $conn): array
{
    // Returns active categories; includes fallback defaults for resilience.
    if (!workflowTableExists($conn, 'artist_categories')) {
        return ['Singers', 'Bands', 'Actors', 'Dancers', 'Photographers', 'Instrumentalists'];
    }

    $result = $conn->query(
        "SELECT category_name
         FROM artist_categories
         WHERE is_active = 1
         ORDER BY sort_order ASC, category_name ASC"
    );

    if (!($result instanceof mysqli_result)) {
        return ['Singers', 'Bands', 'Actors', 'Dancers', 'Photographers', 'Instrumentalists'];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $names = array_values(array_filter(array_map(static function (array $row): string {
        return (string) ($row['category_name'] ?? '');
    }, $rows)));

    if (!$names) {
        return ['Singers', 'Bands', 'Actors', 'Dancers', 'Photographers', 'Instrumentalists'];
    }

    return $names;
}

function fetchTrendingArtists(mysqli $conn, int $limit = 8): array
{
    // Lightweight trending model based on fan + review counts.
    $limit = max(1, min(50, $limit));
    $result = $conn->query(
        "SELECT a.user_id, a.stage_name, a.artist_category,
                COALESCE(f.total_follows, 0) AS follows_count,
                COALESCE(r.total_reviews, 0) AS reviews_count
         FROM artist_applications a
         LEFT JOIN (
             SELECT artist_user_id, COUNT(*) AS total_follows
             FROM artist_follows
             GROUP BY artist_user_id
         ) f ON f.artist_user_id = a.user_id
         LEFT JOIN (
             SELECT artist_user_id, COUNT(*) AS total_reviews
             FROM artist_reviews
             GROUP BY artist_user_id
         ) r ON r.artist_user_id = a.user_id
         WHERE a.status = 'approved'
         ORDER BY follows_count DESC, reviews_count DESC, a.submitted_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchArtistPublicProfile(mysqli $conn, int $artistUserId): ?array
{
    // Public profile details for approved artists only.
    $stmt = $conn->prepare(
        "SELECT a.user_id, a.stage_name, a.artist_category, a.experience_years, a.bio, a.portfolio_url, a.intro_video_path, a.social_links, a.contact_preference, a.gallery_image_paths, a.portfolio_video_paths, a.profile_image_path, a.is_emerging_artist,
                u.username, u.email
         FROM artist_applications a
         INNER JOIN users u ON u.user_id = a.user_id
         WHERE a.user_id = ? AND a.status = 'approved'
         LIMIT 1"
    );
    $stmt->bind_param('i', $artistUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function fetchUserProfileImagePath(mysqli $conn, int $userId): string
{
    // Returns uploaded artist profile image path for header/avatar usage.
    $stmt = $conn->prepare(
        "SELECT profile_image_path
         FROM artist_applications
         WHERE user_id = ? AND profile_image_path IS NOT NULL AND profile_image_path <> ''
         ORDER BY application_id DESC
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['profile_image_path'])) {
        return (string) $row['profile_image_path'];
    }

    return 'assets/img/user profile.png';
}

function isFollowingArtist(mysqli $conn, int $userId, int $artistUserId): bool
{
    $stmt = $conn->prepare('SELECT follow_id FROM artist_follows WHERE user_id = ? AND artist_user_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $artistUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $isFollowing = $result->num_rows > 0;
    $stmt->close();
    return $isFollowing;
}

function createArtistFollow(mysqli $conn, int $userId, int $artistUserId): bool
{
    // Idempotent follow action (duplicate follow won't fail).
    $stmt = $conn->prepare(
        'INSERT INTO artist_follows (user_id, artist_user_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE created_at = created_at'
    );
    $stmt->bind_param('ii', $userId, $artistUserId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function deleteArtistFollow(mysqli $conn, int $userId, int $artistUserId): bool
{
    // Removes the follow link so the user is no longer a fan.
    $stmt = $conn->prepare(
        'DELETE FROM artist_follows
         WHERE user_id = ? AND artist_user_id = ?'
    );
    $stmt->bind_param('ii', $userId, $artistUserId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function createArtistReview(mysqli $conn, int $artistUserId, int $reviewerUserId, int $rating, string $reviewText): bool
{
    // Adds a review authored by reviewer for an artist.
    $stmt = $conn->prepare(
        'INSERT INTO artist_reviews (artist_user_id, reviewer_user_id, rating, review_text)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('iiis', $artistUserId, $reviewerUserId, $rating, $reviewText);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function deleteArtistReview(mysqli $conn, int $reviewId, int $reviewerUserId): bool
{
    $stmt = $conn->prepare(
        'DELETE FROM artist_reviews
         WHERE review_id = ? AND reviewer_user_id = ?'
    );
    $stmt->bind_param('ii', $reviewId, $reviewerUserId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchArtistReviews(mysqli $conn, int $artistUserId, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $stmt = $conn->prepare(
        "SELECT r.review_id, r.rating, r.review_text, r.created_at, r.reviewer_user_id, u.username AS reviewer_name
         FROM artist_reviews r
         INNER JOIN users u ON u.user_id = r.reviewer_user_id
         WHERE r.artist_user_id = ?
         ORDER BY r.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $artistUserId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function createHireRequest(mysqli $conn, int $artistUserId, int $requesterUserId, string $message): bool
{
    // Hire requests are separate from event bookings.
    $stmt = $conn->prepare(
        'INSERT INTO hire_requests (artist_user_id, requester_user_id, message, status)
         VALUES (?, ?, ?, "pending")'
    );
    $stmt->bind_param('iis', $artistUserId, $requesterUserId, $message);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchApprovedVacancies(mysqli $conn, int $limit = 100): array
{
    // Public vacancies page source (approved only).
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT v.vacancy_id, v.title, v.category, v.location, v.description, v.poster_path, v.created_at,
                u.username, u.email
         FROM vacancy_posts v
         INNER JOIN users u ON u.user_id = v.posted_by_user_id
         WHERE v.status = 'approved'
         ORDER BY v.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function hasAppliedVacancy(mysqli $conn, int $vacancyId, int $userId): bool
{
    $stmt = $conn->prepare(
        'SELECT application_id
         FROM vacancy_applications
         WHERE vacancy_id = ? AND applicant_user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $vacancyId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function applyVacancy(mysqli $conn, int $vacancyId, int $userId, string $coverNote): bool
{
    // One application per user per vacancy (upsert-like behavior).
    $stmt = $conn->prepare(
        'INSERT INTO vacancy_applications (vacancy_id, applicant_user_id, cover_note, status)
         VALUES (?, ?, ?, "pending")
         ON DUPLICATE KEY UPDATE cover_note = VALUES(cover_note), status = "pending"'
    );
    $stmt->bind_param('iis', $vacancyId, $userId, $coverNote);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function fetchApprovedAds(mysqli $conn, int $limit = 100): array
{
    // Public ads page source (approved only).
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT a.ad_id, a.ad_title, a.ad_content, a.poster_path, a.start_date, a.end_date, a.created_at,
                u.username, u.email
         FROM ad_posts a
         INNER JOIN users u ON u.user_id = a.posted_by_user_id
         WHERE a.status = 'approved'
         ORDER BY a.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchApprovedEventBookings(mysqli $conn, int $limit = 100): array
{
    // Public events page source (approved bookings only).
    $limit = max(1, min(300, $limit));
    $result = $conn->query(
        "SELECT b.booking_id, b.event_title, b.event_date, b.location, b.budget, b.notes, b.created_at,
                u.username, u.email
         FROM event_bookings b
         INNER JOIN users u ON u.user_id = b.requested_by_user_id
         WHERE b.status = 'approved'
         ORDER BY b.created_at DESC
         LIMIT {$limit}"
    );

    if (!($result instanceof mysqli_result)) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}


