<?php
/**
 * Project File Purpose:
 * - test_db.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
require_once __DIR__ . '/config/db.php';

if ($conn->ping()) {
    echo "✅ Database connected successfully!";
} else {
    echo "❌ Database connection failed!";
}

