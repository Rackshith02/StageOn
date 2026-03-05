<?php
/**
 * Project File Purpose:
 * - logout.php
 * - Contains page logic and rendering for the StageOn workflow.
 */
declare(strict_types=1);
session_start();
// Section: Initialize Session/User Context
session_unset();
session_destroy();
header("Location: login.php");
exit;

