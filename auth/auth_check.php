<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

function require_role(string $requiredRole): void
{
    if (($_SESSION['role'] ?? '') !== $requiredRole) {
        http_response_code(403);
        exit('Access denied.');
    }
}