<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$errors = [];
$success = '';
$fullName = '';
$email = '';
$role = 'student';

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
    // Check email existence
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
<html lang="en" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Create a student account on the Campus Event & Volunteer Management System to discover events and volunteer.">
    <title>Create Account | Campus Event &amp; Volunteer Management System</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
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
        <aside class="relative w-full lg:w-5/12 xl:w-5/12 bg-gradient-to-br from-red-950 via-red-900 to-red-800 text-white p-8 sm:p-12 lg:p-14 flex flex-col justify-between overflow-hidden shadow-2xl">
            
            <!-- Decorative Background Glow -->
            <div class="absolute -top-32 -left-32 w-80 h-80 bg-red-600/25 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-red-500/20 rounded-full blur-3xl pointer-events-none" aria-hidden="true"></div>
            
            <!-- Top Branding -->
            <div class="relative z-10">
                <a href="../index.php" class="inline-flex items-center gap-3.5 group focus:outline-none focus:ring-2 focus:ring-white/40 rounded-xl p-1" aria-label="CampusEvent Home">
                    <div class="w-11 h-11 rounded-xl bg-white text-red-900 flex items-center justify-center font-extrabold text-xl shadow-lg shadow-black/20 group-hover:scale-105 transition-transform duration-200">
                        <svg class="w-6 h-6 text-red-800" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2L1 7l11 5 9-4.09V17h2V7L12 2zm-8.8 7.37L12 13.43l8.8-4.06-2.8-1.27L12 10.57 6 7.84l-2.8 1.53zM12 15.22L4.5 11.8V17c0 3.04 3.36 5.5 7.5 5.5s7.5-2.46 7.5-5.5v-5.2l-7.5 3.42z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-2xl font-black tracking-tight text-white">Campus<span class="text-red-300">Event</span></span>
                        <span class="block text-[11px] font-medium tracking-wider text-red-200/80 uppercase">Management &amp; Volunteer Hub</span>
                    </div>
                </a>
            </div>

            <!-- Middle Value Propositions -->
            <div class="relative z-10 my-10 lg:my-0 space-y-6">
                
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold text-red-100">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" aria-hidden="true"></span>
                    <span>Student Registration</span>
                </div>

                <div class="space-y-3">
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Step Into Campus Activities &amp; Leadership.
                    </h2>
                    <p class="text-red-100/85 text-sm sm:text-base leading-relaxed max-w-md">
                        Join hundreds of university students discovering campus fests, workshops, academic forums, and community volunteer projects.
                    </p>
                </div>

                <!-- Registration Benefits -->
                <div class="space-y-3.5 pt-2">
                    <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10">
                        <div class="w-9 h-9 rounded-xl bg-red-700/60 border border-white/15 flex items-center justify-center shrink-0 text-white mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Instant Event Access</h3>
                            <p class="text-xs text-red-100/70 mt-0.5">Reserve passes, get digital tickets, and receive real-time event notifications.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 p-3.5 rounded-2xl bg-white/5 backdrop-blur-sm border border-white/10">
                        <div class="w-9 h-9 rounded-xl bg-red-700/60 border border-white/15 flex items-center justify-center shrink-0 text-white mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Recognized Volunteer Service</h3>
                            <p class="text-xs text-red-100/70 mt-0.5">Participate in university volunteer roles and earn verified participation records.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Assurance -->
            <div class="relative z-10 pt-6 border-t border-white/10 flex items-center justify-between text-xs text-red-200/70">
                <span>&copy; 2026 CampusEvent Platform</span>
                <span class="inline-flex items-center gap-1.5 font-medium text-white/90">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Verified Student Membership
                </span>
            </div>

        </aside>

        <!-- RIGHT PANEL: Registration Form -->
        <main class="w-full lg:w-7/12 xl:w-7/12 flex items-center justify-center p-6 sm:p-10 lg:p-14">
            
            <section class="w-full max-w-lg bg-white rounded-3xl p-7 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100">
                
                <!-- Mobile Logo Header -->
                <div class="lg:hidden flex items-center gap-3 mb-6 pb-6 border-b border-slate-100">
                    <div class="w-10 h-10 rounded-xl bg-red-800 text-white flex items-center justify-center font-bold shadow-md shadow-red-900/20">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2L1 7l11 5 9-4.09V17h2V7L12 2zm-8.8 7.37L12 13.43l8.8-4.06-2.8-1.27L12 10.57 6 7.84l-2.8 1.53zM12 15.22L4.5 11.8V17c0 3.04 3.36 5.5 7.5 5.5s7.5-2.46 7.5-5.5v-5.2l-7.5 3.42z"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-bold tracking-tight text-slate-900">Campus<span class="text-red-700">Event</span></span>
                        <span class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider">Student Registration</span>
                    </div>
                </div>

                <!-- Page Heading -->
                <div class="mb-7">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Create Your Account</h1>
                    <p class="mt-1.5 text-sm text-slate-500 leading-relaxed">
                        Join the CampusEvent platform to browse campus activities and participate in volunteer initiatives.
                    </p>
                </div>

                <!-- Server Errors List -->
                <?php if (!empty($errors)): ?>
                    <div role="alert" class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm shadow-sm">
                        <div class="flex items-center gap-2 font-bold mb-1.5 text-red-900">
                            <svg class="w-5 h-5 text-red-600 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <span>Please correct the following:</span>
                        </div>
                        <ul class="list-disc pl-7 space-y-1 text-xs sm:text-sm font-medium">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Server Success Message -->
                <?php if ($success !== ''): ?>
                    <div role="status" class="mb-6 flex items-start gap-3 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form action="register.php" method="post" id="register-form" novalidate class="space-y-4">
                    
                    <!-- Full Name -->
                    <div>
                        <label for="full-name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Full Name
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <input 
                                type="text" 
                                id="full-name" 
                                name="full_name" 
                                value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="name" 
                                required 
                                placeholder="e.g. Alex Johnson"
                                aria-describedby="full-name-error"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200"
                            >
                        </div>
                        <p id="full-name-error" class="mt-1 text-xs font-semibold text-red-600 hidden" role="alert"></p>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Email Address
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                                </svg>
                            </div>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                autocomplete="email" 
                                required 
                                placeholder="e.g. student@campus.edu" 
                                aria-describedby="email-error"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200"
                            >
                        </div>
                        <p id="email-error" class="mt-1 text-xs font-semibold text-red-600 hidden" role="alert"></p>
                    </div>

                    <!-- Role Selector (Styled UI with required form select) -->
                    <div>
                        <label for="role" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Account Type
                        </label>
                        
                        <!-- Visual Card for Student Role -->
                        <div class="p-3.5 rounded-xl border-2 border-red-700 bg-red-50/40 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-red-800 text-white flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                                    </svg>
                                </div>
                                <div>
                                    <span class="block text-sm font-bold text-slate-900">Student Account</span>
                                    <span class="block text-xs text-slate-500">Public campus registration &amp; volunteer participation</span>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-red-100 text-[11px] font-bold text-red-800">
                                Active
                            </span>
                        </div>

                        <!-- Underlying functional form select element -->
                        <div class="mt-1.5">
                            <select id="role" name="role" required class="w-full text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-2 focus:border-red-700 focus:outline-none">
                                <option value="student" selected>Student (Public Registration)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                autocomplete="new-password" 
                                required 
                                placeholder="At least 8 characters" 
                                aria-describedby="password-error password-requirements"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-12 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200"
                            >
                            <button 
                                type="button" 
                                class="password-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600/30 rounded-lg m-1" 
                                data-target="password" 
                                aria-label="Show password" 
                                aria-pressed="false"
                            >
                                <svg class="eye-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg class="eye-slash-icon w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>
                        <p id="password-error" class="mt-1 text-xs font-semibold text-red-600 hidden" role="alert"></p>
                        
                        <!-- Interactive Password Requirements List -->
                        <div id="password-requirements" class="mt-2 p-3 rounded-lg bg-slate-50 border border-slate-200/80 text-[11px] space-y-1">
                            <span class="block font-bold text-slate-700 mb-1">Password Requirements:</span>
                            <div class="grid grid-cols-2 gap-1.5 text-slate-500">
                                <span id="req-len" class="flex items-center gap-1.5 transition-colors">
                                    <span class="req-bullet w-1.5 h-1.5 rounded-full bg-slate-400"></span> 8+ characters
                                </span>
                                <span id="req-upper" class="flex items-center gap-1.5 transition-colors">
                                    <span class="req-bullet w-1.5 h-1.5 rounded-full bg-slate-400"></span> Uppercase letter
                                </span>
                                <span id="req-lower" class="flex items-center gap-1.5 transition-colors">
                                    <span class="req-bullet w-1.5 h-1.5 rounded-full bg-slate-400"></span> Lowercase letter
                                </span>
                                <span id="req-num" class="flex items-center gap-1.5 transition-colors">
                                    <span class="req-bullet w-1.5 h-1.5 rounded-full bg-slate-400"></span> One number (0-9)
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm-password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <input 
                                type="password" 
                                id="confirm-password" 
                                name="confirm_password" 
                                autocomplete="new-password" 
                                required 
                                placeholder="Re-enter your password" 
                                aria-describedby="confirm-password-error"
                                class="w-full rounded-xl border border-slate-300 bg-white pl-10 pr-12 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-red-700 focus:ring-4 focus:ring-red-700/15 focus:outline-none transition-all duration-200"
                            >
                            <button 
                                type="button" 
                                class="password-toggle absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600/30 rounded-lg m-1" 
                                data-target="confirm-password" 
                                aria-label="Show password" 
                                aria-pressed="false"
                            >
                                <svg class="eye-icon w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg class="eye-slash-icon w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>
                        <p id="confirm-password-error" class="mt-1 text-xs font-semibold text-red-600 hidden" role="alert"></p>
                    </div>

                    <!-- Register Button -->
                    <button 
                        type="submit" 
                        id="register-submit-btn"
                        class="w-full mt-3 py-3.5 px-5 rounded-xl font-bold text-sm text-white bg-red-800 hover:bg-red-900 active:scale-[0.99] focus:outline-none focus:ring-4 focus:ring-red-700/20 shadow-md hover:shadow-lg shadow-red-900/15 transition-all duration-200 flex items-center justify-center gap-2 group cursor-pointer"
                    >
                        <span>Create Student Account</span>
                        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>

                </form>

                <!-- Link to Login -->
                <div class="mt-7 pt-6 border-t border-slate-100 text-center">
                    <p class="text-sm text-slate-600">
                        Already have an account?
                        <a href="login.php" class="font-bold text-red-800 hover:text-red-950 hover:underline underline-offset-4 transition-colors">
                            Sign in here
                        </a>
                    </p>
                    <p class="mt-2.5 text-xs text-slate-400">
                        Organizers &amp; staff: Contact admin for credential provisioning.
                    </p>
                </div>

            </section>

        </main>

    </div>

    <!-- Client-side Validation & Live Checklist Script -->
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

        // Password visibility toggles with SVG icon switches
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

        // Live password requirement checklist indicators
        const reqLen = document.getElementById('req-len');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNum = document.getElementById('req-num');

        function updateReqStatus(element, isMet) {
            const bullet = element.querySelector('.req-bullet');
            if (isMet) {
                element.classList.remove('text-slate-500');
                element.classList.add('text-emerald-700', 'font-semibold');
                if (bullet) {
                    bullet.classList.remove('bg-slate-400');
                    bullet.classList.add('bg-emerald-500');
                }
            } else {
                element.classList.remove('text-emerald-700', 'font-semibold');
                element.classList.add('text-slate-500');
                if (bullet) {
                    bullet.classList.remove('bg-emerald-500');
                    bullet.classList.add('bg-slate-400');
                }
            }
        }

        registerPassword.addEventListener('input', () => {
            const val = registerPassword.value;
            updateReqStatus(reqLen, val.length >= 8);
            updateReqStatus(reqUpper, /[A-Z]/.test(val));
            updateReqStatus(reqLower, /[a-z]/.test(val));
            updateReqStatus(reqNum, /\d/.test(val));

            // Clear password error when typing
            if (registerPassword.getAttribute('aria-invalid') === 'true') {
                const errElem = document.getElementById('password-error');
                errElem.textContent = '';
                errElem.classList.add('hidden');
                registerPassword.removeAttribute('aria-invalid');
                registerPassword.classList.remove('border-red-500', 'bg-red-50/20');
            }
        });

        // Live clear error on input for other fields
        [fullName, registerEmail, confirmPassword].forEach((input) => {
            input.addEventListener('input', () => {
                if (input.getAttribute('aria-invalid') === 'true') {
                    const errElem = document.getElementById(input.id + '-error');
                    if (errElem) {
                        errElem.textContent = '';
                        errElem.classList.add('hidden');
                    }
                    input.removeAttribute('aria-invalid');
                    input.classList.remove('border-red-500', 'bg-red-50/20');
                }
            });
        });

        // Form submission validation
        registerForm.addEventListener('submit', (event) => {
            let isValid = true;
            fields.forEach(([field, error]) => {
                error.textContent = '';
                error.classList.add('hidden');
                field.removeAttribute('aria-invalid');
                field.classList.remove('border-red-500', 'bg-red-50/20');
            });

            if (!namePattern.test(fullName.value.trim())) {
                const err = document.getElementById('full-name-error');
                err.textContent = 'Use a name with 2-100 letters; spaces, apostrophes, periods, and hyphens are allowed.';
                err.classList.remove('hidden');
                fullName.setAttribute('aria-invalid', 'true');
                fullName.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (!emailPattern.test(registerEmail.value.trim())) {
                const err = document.getElementById('email-error');
                err.textContent = 'Enter a valid email address, such as name@gmail.com or name@outlook.com.';
                err.classList.remove('hidden');
                registerEmail.setAttribute('aria-invalid', 'true');
                registerEmail.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (!strongPasswordPattern.test(registerPassword.value)) {
                const err = document.getElementById('password-error');
                err.textContent = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
                err.classList.remove('hidden');
                registerPassword.setAttribute('aria-invalid', 'true');
                registerPassword.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (confirmPassword.value !== registerPassword.value) {
                const err = document.getElementById('confirm-password-error');
                err.textContent = 'Passwords do not match.';
                err.classList.remove('hidden');
                confirmPassword.setAttribute('aria-invalid', 'true');
                confirmPassword.classList.add('border-red-500', 'bg-red-50/20');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                const firstInvalid = registerForm.querySelector('[aria-invalid="true"]');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    </script>
</body>

</html>