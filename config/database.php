<?php

declare(strict_types=1);

/**
 * Load environment variables from the project's .env file.
 */
function loadEnv(string $filePath): void
{
    if (!file_exists($filePath)) {
        die('Environment configuration file (.env) not found.');
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Ignore comments
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Ignore invalid lines
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        $_ENV[$key] = $value;
    }
}

/**
 * Load project environment configuration.
 */
loadEnv(__DIR__ . '/../.env');

/**
 * Database configuration.
 */
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbPort = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbName = $_ENV['DB_NAME'] ?? '';
$dbUser = $_ENV['DB_USER'] ?? '';
$dbPassword = $_ENV['DB_PASSWORD'] ?? '';

/**
 * Create MariaDB connection.
 */
$conn = new mysqli(
    $dbHost,
    $dbUser,
    $dbPassword,
    $dbName,
    $dbPort
);

/**
 * Stop execution if the connection fails.
 */
if ($conn->connect_error) {
    die('Database connection failed.');
}

/**
 * Set database character encoding.
 */
$conn->set_charset('utf8mb4');