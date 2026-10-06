<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';

    // -------------------------------
    // Validation
    // -------------------------------

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    $allowedRoles = [
        'student',
        'organizer',
        'administrator'
    ];

    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }


    // -------------------------------
    // Check email
    // -------------------------------

    if (empty($errors)) {

        $checkStmt = $conn->prepare(
            'SELECT user_id FROM users WHERE email = ?'
        );

        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();

        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }

        $checkStmt->close();
    }


    // -------------------------------
    // Create account
    // -------------------------------

    if (empty($errors)) {

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            'INSERT INTO users
            (full_name, email, password_hash, role)
            VALUES (?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'ssss',
            $fullName,
            $email,
            $passwordHash,
            $role
        );

        if ($stmt->execute()) {

            $success = 'Account created successfully. You can now log in.';

        } else {

            $errors[] = 'Unable to create account. Please try again.';
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

    <title>
        Register | Campus Event &amp; Volunteer Management System
    </title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
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

            <!-- Page Heading -->
            <h1 class="mb-6 text-center text-2xl font-bold text-gray-800">
                Create an Account
            </h1>

            <?php if (!empty($errors)): ?>

                <div role="alert" class="mb-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        <?php foreach ($errors as $error): ?>
                            <li>
                                <?= htmlspecialchars($error) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div role="status" class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm text-green-700">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>

            <!-- Registration Form -->
            <form action="register.php" method="post">

                <!-- Full Name -->
                <div class="mb-4">

                    <label for="full-name" class="mb-1 block font-medium text-gray-700">
                        Full name
                    </label>

                    <input type="text" id="full-name" name="full_name" autocomplete="name" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">

                </div>


                <!-- Email -->
                <div class="mb-4">

                    <label for="email" class="mb-1 block font-medium text-gray-700">
                        Email address
                    </label>

                    <input type="email" id="email" name="email" autocomplete="email" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">

                </div>


                <!-- Password -->
                <div class="mb-4">

                    <label for="password" class="mb-1 block font-medium text-gray-700">
                        Password
                    </label>

                    <input type="password" id="password" name="password" autocomplete="new-password" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">

                </div>


                <!-- Confirm Password -->
                <div class="mb-4">

                    <label for="confirm-password" class="mb-1 block font-medium text-gray-700">
                        Confirm password
                    </label>

                    <input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password"
                        required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">

                </div>


                <!-- Role -->
                <div class="mb-4">

                    <label for="role" class="mb-1 block font-medium text-gray-700">
                        Select role
                    </label>

                    <select id="role" name="role" required
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">
                        <option value="student" selected>
                            Student
                        </option>

                        <option value="organizer">
                            Organizer
                        </option>

                        <option value="administrator">
                            Administrator
                        </option>
                    </select>

                </div>


                <!-- Register Button -->
                <button type="submit"
                    class="mt-2 w-full rounded-md bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
                    Register
                </button>

            </form>


            <!-- Login Link -->
            <p class="mt-5 text-center text-sm text-gray-600">

                Already have an account?

                <a href="login.php" class="font-medium text-blue-600 hover:underline">
                    Login here
                </a>

            </p>

        </section>

    </main>

</body>

</html>