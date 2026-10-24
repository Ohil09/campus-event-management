<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$email = '';
$notice = '';

if (isset($_GET['notice']) && is_string($_GET['notice'])) {
    $notice = trim($_GET['notice']);
}

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

            if ($user['status'] !== 'active') {

                $error = 'Account deactivated by the admin.';

            } elseif (password_verify($password, $user['password_hash'])) {

                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                switch ($user['role']) {

                    case 'organizer':
                        header('Location: ../organizer/organizer-dashboard.php');
                        exit;

                    case 'student':
                        header('Location: ../student/index.php');
                        exit;

                    case 'administrator':
                        header('Location: ../admin/admin-dashboard.php');
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
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Sign in to the Campus Event & Volunteer Management System to discover events, register, and volunteer.">
    <title>Sign In | Campus Event &amp; Volunteer Management System</title>

    <!-- Google Fonts: Plus Jakarta Sans for crisp, modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS v4 in-browser compiler -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
    </style>
</head>

<body class="min-h-full bg-slate-50 text-slate-900 flex flex-col justify-center">

    <div class="min-h-screen w-full flex flex-col lg:flex-row">

        <!-- LEFT PANEL: CampusEvent Branding & Welcome (Desktop/Tablet) -->
        <aside
            class="relative w-full lg:w-5/12 xl:w-5/12 bg-gradient-to-br from-red-950 via-red-900 to-red-800 text-white p-8 sm:p-12 lg:p-14 flex flex-col justify-between overflow-hidden shadow-2xl">

            <!-- Decorative Background Glow & Pattern -->
            <div class="absolute -top-32 -left-32 w-80 h-80 bg-red-600/25 rounded-full blur-3xl pointer-events-none"
                aria-hidden="true"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-red-500/20 rounded-full blur-3xl pointer-events-none"
                aria-hidden="true"></div>

            <!-- Top Branding -->
            <div class="relative z-10">
                <a href="../index.php"
                    class="inline-flex items-center gap-3.5 group focus:outline-none focus:ring-2 focus:ring-white/40 rounded-xl p-1"
                    aria-label="CampusEvent Home">
                    <div
                        class="w-11 h-11 rounded-xl bg-white text-red-900 flex items-center justify-center font-extrabold text-xl shadow-lg shadow-black/20 group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-6 h-6 text-red-800" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M12 2L1 7l11 5 9-4.09V17h2V7L12 2zm-8.8 7.37L12 13.43l8.8-4.06-2.8-1.27L12 10.57 6 7.84l-2.8 1.53zM12 15.22L4.5 11.8V17c0 3.04 3.36 5.5 7.5 5.5s7.5-2.46 7.5-5.5v-5.2l-7.5 3.42z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-2xl font-black tracking-tight text-white">Campus<span
                                class="text-red-300">Event</span></span>
                        <span class="block text-[11px] font-medium tracking-wider text-red-200/80 uppercase">Management
                            &amp; Volunteer Hub</span>
                    </div>
                </a>
            </div>

            <!-- Middle Value Propositions -->
            <div class="relative z-10 my-10 lg:my-0 space-y-6">

                <div
                    class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold text-red-100">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" aria-hidden="true"></span>
                    <span>Campus Life Reimagined</span>
                </div>

                <div class="space-y-3">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Connect, Engage &amp; Lead on Campus.
                    </h2>
                    <p class="text-red-100/85 text-sm sm:text-base leading-relaxed max-w-md">
                        Your central portal for campus event registration, volunteer opportunities, and leadership
                        recognition across university life.
                    </p>
                </div>



            </div>

            <!-- Bottom Note -->
            <div
                class="relative z-10 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-red-200/70">
                <span>&copy; 2026 CampusEvent Platform</span>
                <span class="inline-flex items-center gap-1.5 font-medium text-white/90">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"
                        aria-hidden="true">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    Protected by University Auth
                </span>
            </div>

        </aside>

        <!-- RIGHT PANEL: Login Form -->
        <main class="w-full lg:w-7/12 xl:w-7/12 flex items-center justify-center p-6 sm:p-10 lg:p-16">

            <section
                class="w-full max-w-md bg-white rounded-3xl p-7 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100">

                <!-- Mobile Logo Header (Visible on small screens) -->
                <div class="lg:hidden flex items-center gap-3 mb-6 pb-6 border-b border-slate-100">
                    <div
                        class="w-10 h-10 rounded-xl bg-red-800 text-white flex items-center justify-center font-bold shadow-md shadow-red-900/20">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M12 2L1 7l11 5 9-4.09V17h2V7L12 2zm-8.8 7.37L12 13.43l8.8-4.06-2.8-1.27L12 10.57 6 7.84l-2.8 1.53zM12 15.22L4.5 11.8V17c0 3.04 3.36 5.5 7.5 5.5s7.5-2.46 7.5-5.5v-5.2l-7.5 3.42z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-slate-900">Campus<span
                                class="text-red-700">Event</span></span>
                        <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Campus
                            Portal</span>
                    </div>
                </div>

                <!-- Form Header -->
                <div class="mb-8">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Sign In</h1>
                    <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                        Welcome back! Please enter your credentials to access your account.
                    </p>
                </div>

                <!-- Notice Message (if provided) -->
                <?php if ($notice !== ''): ?>
                    <div role="status"
                        class="mb-6 flex items-start gap-3 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"
                            aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Server Error Message -->
                <?php if ($error !== ''): ?>
                    <div role="alert"
                        class="mb-6 flex items-start gap-3 p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm shadow-sm">
                        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"
                            aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form action="login.php" method="post" id="login-form" novalidate class="space-y-5">

                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                            Email Address
                        </label>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                            <input type="email" id="email" name="email"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email"
                                required placeholder="e.g. abc@gmail.com" aria-describedby="email-error"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200">
                        </div>
                        <p id="email-error" class="mt-1.5 text-xs font-semibold text-red-600 hidden" role="alert"></p>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password"
                                class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                                Password
                            </label>
                        </div>
                        <div class="relative">
                            <div
                                class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" id="password" name="password" autocomplete="current-password"
                                required placeholder="••••••••" aria-describedby="password-error"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-12 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200">
                            <button type="button"
                                class="password-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600/30 rounded-lg m-1"
                                data-target="password" aria-label="Show password" aria-pressed="false">
                                <svg class="eye-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg class="eye-slash-icon w-5 h-5 hidden" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <p id="password-error" class="mt-1.5 text-xs font-semibold text-red-600 hidden" role="alert">
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="login-submit-btn"
                        class="w-full mt-2 py-3.5 px-5 rounded-xl font-bold text-sm text-white bg-red-800 hover:bg-red-900 active:scale-[0.99] focus:outline-none focus:ring-4 focus:ring-red-700/20 shadow-md hover:shadow-lg shadow-red-900/15 transition-all duration-200 flex items-center justify-center gap-2 group cursor-pointer">
                        <span>Sign In to Account</span>
                    </button>

                </form>

                <!-- Link to Registration -->
                <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                    <p class="text-sm text-slate-600">
                        Do not have an account?
                        <a href="register.php"
                            class="font-bold text-red-800 hover:text-red-950 hover:underline underline-offset-4 transition-colors">
                            Register as a Student
                        </a>
                    </p>
                    <p class="mt-3 text-xs text-slate-400">
                        Organizer and Administrator accounts are assigned directly by department administrators.
                    </p>
                </div>

            </section>

        </main>

    </div>

    <!-- Interactive Client Script -->
    <script>
        const loginForm = document.getElementById('login-form');
        const loginEmail = document.getElementById('email');
        const loginPassword = document.getElementById('password');
        const loginEmailError = document.getElementById('email-error');
        const loginPasswordError = document.getElementById('password-error');
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

        // Password visibility toggles with SVG icon changes
        document.querySelectorAll('.password-toggle').forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const targetId = toggle.dataset.target;
                const field = document.getElementById(targetId);
                const isHidden = field.type === 'password';
                field.type = isHidden ? 'text' : 'password';

                toggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
                toggle.setAttribute('aria-pressed', String(isHidden));

                const eyeIcon = toggle.querySelector('.eye-icon');
                const eyeSlashIcon = toggle.querySelector('.eye-slash-icon');
                if (eyeIcon && eyeSlashIcon) {
                    eyeIcon.classList.toggle('hidden', isHidden);
                    eyeSlashIcon.classList.toggle('hidden', !isHidden);
                }
            });
        });

        // Real-time error clearance on input
        loginEmail.addEventListener('input', () => {
            if (loginEmail.getAttribute('aria-invalid') === 'true') {
                loginEmailError.textContent = '';
                loginEmailError.classList.add('hidden');
                loginEmail.removeAttribute('aria-invalid');
                loginEmail.classList.remove('border-red-500', 'bg-red-50/20');
            }
        });

        loginPassword.addEventListener('input', () => {
            if (loginPassword.getAttribute('aria-invalid') === 'true') {
                loginPasswordError.textContent = '';
                loginPasswordError.classList.add('hidden');
                loginPassword.removeAttribute('aria-invalid');
                loginPassword.classList.remove('border-red-500', 'bg-red-50/20');
            }
        });

        // Form submission validation
        loginForm.addEventListener('submit', (event) => {
            let isValid = true;
            loginEmailError.textContent = '';
            loginEmailError.classList.add('hidden');
            loginPasswordError.textContent = '';
            loginPasswordError.classList.add('hidden');
            loginEmail.removeAttribute('aria-invalid');
            loginPassword.removeAttribute('aria-invalid');
            loginEmail.classList.remove('border-red-500', 'bg-red-50/20');
            loginPassword.classList.remove('border-red-500', 'bg-red-50/20');

            if (!emailPattern.test(loginEmail.value.trim())) {
                loginEmailError.textContent = 'Enter a valid email address, such as name@gmail.com ';
                loginEmailError.classList.remove('hidden');
                loginEmail.setAttribute('aria-invalid', 'true');
                loginEmail.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (loginPassword.value === '') {
                loginPasswordError.textContent = 'Password is required.';
                loginPasswordError.classList.remove('hidden');
                loginPassword.setAttribute('aria-invalid', 'true');
                loginPassword.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                const firstInvalid = loginForm.querySelector('[aria-invalid="true"]');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    </script>
</body>

</html>