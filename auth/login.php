<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Password is required.';

    } else {

        $stmt = $conn->prepare(
            'SELECT user_id, full_name, password_hash, role, status
             FROM users
             WHERE email = ?'
        );

        $stmt->bind_param('s', $email);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (
                $user['status'] === 'active' &&
                password_verify($password, $user['password_hash'])
            ) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {

                    case 'organizer':
                        header('Location: ../organizer/organizer-dashboard.php');
                        exit;

                    case 'student':
                        header('Location: ../student/');
                        exit;

                    case 'administrator':
                        header('Location: ../admin/');
                        exit;

                    default:
                        $error = 'Invalid account role.';
                }

            } else {

                $error = 'Invalid email or password.';
            }

        } else {

            $error = 'Invalid email or password.';
        }

        $stmt->close();
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Campus Event &amp; Volunteer Management System</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<style>
    *{
        box-sizing: border-box;
    }
    body{
        margin: 0;
        padding: 0;
        width: 100%;
        overflow-x: hidden;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 16px;
    }
</style>
</head>
<body>
    <main class="flex min-h-screen items-center justify-center bg-gray-100 p-6">
        <section class="w-full max-w-md rounded-lg bg-white p-8 shadow-md">
            <h1 class="mb-6 text-center text-2xl font-bold text-gray-800">Login</h1>

            <?php if ($error !== ''): ?>

                <div
                    role="alert"
                    class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700"
                >
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form action="login.php" method="post">
                <div>
                    <label for="email" class="mb-1 block font-medium text-gray-700">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="email" required class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label for="password" class="mb-1 block font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                </div>

                <button type="submit" class="mt-4 w-full rounded-md bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">Login</button>
            </form>
            <p class="mt-5 text-center text-sm text-gray-600">
                Do not have an account?
                <a href="register.php" class="font-medium text-blue-600 hover:underline">Register here</a>
            </p>
            </section>
    </main>
</body>
</html>