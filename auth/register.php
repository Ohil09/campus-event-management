<?php

declare(strict_types=1);

session_start();

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

    $allowedRoles = ['student'];

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

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $stmt->insert_id;
            $_SESSION['full_name'] = $fullName;
            $_SESSION['role'] = 'student';

            header('Location: ../student/index.php');
            exit;

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
            <form action="register.php" method="post" id="register-form" novalidate>

                <!-- Full Name -->
                <div class="mb-4">

                    <label for="full-name" class="mb-1 block font-medium text-gray-700">
                        Full name
                    </label>

                    <input type="text" id="full-name" name="full_name" autocomplete="name" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                    <p id="full-name-error" class="mt-1 text-sm text-red-600" role="alert"></p>

                </div>


                <!-- Email -->
                <div class="mb-4">

                    <label for="email" class="mb-1 block font-medium text-gray-700">
                        Email address
                    </label>

                    <input type="email" id="email" name="email" autocomplete="email" required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none">
                    <p id="email-error" class="mt-1 text-sm text-red-600" role="alert"></p>

                </div>


                <!-- Password -->
                <div class="mb-4">

                    <label for="password" class="mb-1 block font-medium text-gray-700">
                        Password
                    </label>

                    <div class="relative">
                        <input type="password" id="password" name="password" autocomplete="new-password" required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 pr-11 focus:border-blue-500 focus:outline-none">
                        <button type="button"
                            class="password-toggle absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-800"
                            data-target="password" aria-label="Show password" aria-pressed="false">&#128065;</button>
                    </div>
                    <p id="password-error" class="mt-1 text-sm text-red-600" role="alert"></p>

                </div>


                <!-- Confirm Password -->
                <div class="mb-4">

                    <label for="confirm-password" class="mb-1 block font-medium text-gray-700">
                        Confirm password
                    </label>

                    <div class="relative">
                        <input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password"
                            required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 pr-11 focus:border-blue-500 focus:outline-none">
                        <button type="button"
                            class="password-toggle absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-800"
                            data-target="confirm-password" aria-label="Show password"
                            aria-pressed="false">&#128065;</button>
                    </div>
                    <p id="confirm-password-error" class="mt-1 text-sm text-red-600" role="alert"></p>

                </div>


                <!-- Role -->
                <div class="mb-4">

                    <label for="role" class="mb-1 block font-medium text-gray-700">
                        Select role
                    </label>

                    <select id="role" name="role" required
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 focus:border-blue-500 focus:outline-none">
                        <option value="student" selected>Student</option>
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

    <script>
        const registerForm = document.getElementById('register-form');
        const fullName = document.getElementById('full-name');
        const registerEmail = document.getElementById('email');
        const registerPassword = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm-password');
        const fields = [
            [fullName, document.getElementById('full-name-error')],
            [registerEmail, document.getElementById('email-error')],
            [registerPassword, document.getElementById('password-error')],
            [confirmPassword, document.getElementById('confirm-password-error')]
        ];
        const namePattern = /^(?=.{2,100}$)[\p{L}][\p{L}\s.'-]*$/u;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
        const strongPasswordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        document.querySelectorAll('.password-toggle').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const field = document.getElementById(toggle.dataset.target);
                const isHidden = field.type === 'password';
                field.type = isHidden ? 'text' : 'password';
                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', String(isHidden));
            });
        });

        registerForm.addEventListener('submit', (event) => {
            let isValid = true;
            fields.forEach(([field, error]) => {
                error.textContent = '';
                field.removeAttribute('aria-invalid');
            });

            if (!namePattern.test(fullName.value.trim())) {
                document.getElementById('full-name-error').textContent =
                    'Use a name with 2-100 letters; spaces, apostrophes, periods, and hyphens are allowed.';
                fullName.setAttribute('aria-invalid', 'true');
                isValid = false;
            }

            if (!emailPattern.test(registerEmail.value.trim())) {
                document.getElementById('email-error').textContent =
                    'Enter a valid email address, such as name@gmail.com or name@outlook.com.';
                registerEmail.setAttribute('aria-invalid', 'true');
                isValid = false;
            }

            if (!strongPasswordPattern.test(registerPassword.value)) {
                document.getElementById('password-error').textContent =
                    'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
                registerPassword.setAttribute('aria-invalid', 'true');
                isValid = false;
            }

            if (confirmPassword.value !== registerPassword.value) {
                document.getElementById('confirm-password-error').textContent =
                    'Passwords do not match.';
                confirmPassword.setAttribute('aria-invalid', 'true');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>

</html>