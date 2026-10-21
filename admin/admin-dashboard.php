<?php
declare(strict_types=1);

// 1. Authentication & Authorization
require_once __DIR__ . '/../auth/auth_check.php';
require_role('administrator');

// 2. Database Connection
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    $conn = null;
}

// Helper
if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dashNotice = '';
$dashError = '';

if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $dashNotice = htmlspecialchars(urldecode((string) $_GET['notice']), ENT_QUOTES, 'UTF-8');
}

/* -----------------------------------------------------------
   Handle POST: Create Organizer Account
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
    $postedToken = trim((string) ($_POST['csrf_token'] ?? ''));
    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $dashError = 'Session expired. Please refresh and try again.';
    } elseif (trim((string) ($_POST['admin_action'] ?? '')) === 'create_organizer') {
        $orgName = trim((string) ($_POST['full_name'] ?? ''));
        $orgEmail = trim((string) ($_POST['email'] ?? ''));
        $orgPass = (string) ($_POST['password'] ?? '');
        $orgConfirm = (string) ($_POST['confirm_password'] ?? '');

        $createErrors = [];
        if ($orgName === '')
            $createErrors[] = 'Full name is required.';
        if (!filter_var($orgEmail, FILTER_VALIDATE_EMAIL))
            $createErrors[] = 'A valid email address is required.';
        if (strlen($orgPass) < 8)
            $createErrors[] = 'Password must be at least 8 characters.';
        if ($orgPass !== $orgConfirm)
            $createErrors[] = 'Passwords do not match.';

        if (empty($createErrors)) {
            // Check email uniqueness
            $chk = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
            $chk->bind_param('s', $orgEmail);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $createErrors[] = 'An account with this email already exists.';
            }
            $chk->close();
        }

        if (empty($createErrors)) {
            $hash = password_hash($orgPass, PASSWORD_DEFAULT);
            $role = 'organizer';
            $ins = $conn->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $ins->bind_param('ssss', $orgName, $orgEmail, $hash, $role);
            if ($ins->execute()) {
                header('Location: admin-dashboard.php?notice=' . urlencode('Organizer account created successfully.') . '#create-organizer');
                exit;
            } else {
                $dashError = 'Database error: could not create the account.';
            }
            $ins->close();
        } else {
            $dashError = implode(' ', $createErrors);
        }
    }
}

// 3. Session Variables
$adminName = (string) ($_SESSION['full_name'] ?? 'Administrator');

// 4. Fetch System Statistics
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_events' => 0,
    'upcoming_events' => 0,
    'total_registrations' => 0,
    'total_applications' => 0,
];

if ($conn !== null) {
    // Total Users
    $res = $conn->query("SELECT COUNT(*) AS count FROM users");
    if ($res) {
        $stats['total_users'] = (int) $res->fetch_assoc()['count'];
        $res->free();
    }

    // Active Users
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM users WHERE status = 'active'");
    if ($stmt) {
        $stmt->execute();
        $stats['active_users'] = (int) $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    }

    // Total Events
    $res = $conn->query("SELECT COUNT(*) AS count FROM events");
    if ($res) {
        $stats['total_events'] = (int) $res->fetch_assoc()['count'];
        $res->free();
    }

    // Upcoming Published Events
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM events WHERE status = 'published' AND start_datetime >= NOW()");
    if ($stmt) {
        $stmt->execute();
        $stats['upcoming_events'] = (int) $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    }

    // Total Participant Registrations
    $res = $conn->query("SELECT COUNT(*) AS count FROM event_registrations");
    if ($res) {
        $stats['total_registrations'] = (int) $res->fetch_assoc()['count'];
        $res->free();
    }

    // Total Volunteer Applications
    $res = $conn->query("SELECT COUNT(*) AS count FROM volunteer_applications");
    if ($res) {
        $stats['total_applications'] = (int) $res->fetch_assoc()['count'];
        $res->free();
    }

    // 5. Fetch Recent Events (All events ordered by most recent)
    $recentStmt = $conn->prepare(
        "SELECT e.event_id, e.title, e.start_datetime, e.status, u.full_name AS organizer_name
         FROM events e
         INNER JOIN users u ON e.organizer_id = u.user_id
         ORDER BY e.event_id DESC"
    );
    $recentStmt->execute();
    $recentEvents = $recentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $recentStmt->close();
} else {
    $recentEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Administrator dashboard for the Campus Event and Volunteer Management System">
    <title>Admin Dashboard | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/admin.css?v=<?= time() ?>">
    <style>
        .recent-events-scrollbox {
            max-height: 285px;
            overflow-y: scroll;
            overscroll-behavior-y: contain;
            border: 1px solid #fee2e2;
            border-radius: 6px;
            scrollbar-width: thin;
            scrollbar-color: #f87171 #fef2f2;
        }

        .recent-events-scrollbox table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }

        .recent-events-scrollbox thead th {
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #fef2f2;
            box-shadow: inset 0 -2px 0 #fecaca;
        }

        .recent-events-scrollbox::-webkit-scrollbar {
            width: 8px;
        }

        .recent-events-scrollbox::-webkit-scrollbar-track {
            background: #fef2f2;
            border-radius: 4px;
        }

        .recent-events-scrollbox::-webkit-scrollbar-thumb {
            background-color: #f87171;
            border-radius: 4px;
            border: 2px solid #fef2f2;
        }

        .recent-events-scrollbox::-webkit-scrollbar-thumb:hover {
            background-color: #dc2626;
        }
    </style>
</head>

<body>

    <a class="skip-link" href="#main-content">Skip to main content</a>

    <!-- Header -->
    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent
                Admin</a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false"
                aria-controls="admin-navigation">&#9776;</button>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= h($adminName) ?></span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">

        <!-- Sidebar -->
        <aside class="sidebar" id="admin-navigation" aria-label="Administrator navigation">
            <nav>
                <h2 class="sidebar-title">Administration</h2>
                <ul class="sidebar-menu">
                    <li><a href="admin-dashboard.php" class="nav-link active" aria-current="page"><span>Dashboard</span></a></li>
                    <li><a href="administration-reports.php" class="nav-link"><span>Users & Events</span></a></li>
                    <li><a href="administration-reports.php#reports" class="nav-link"><span>Reports & Feedback</span></a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main id="main-content" class="main-content">

            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Administrator</p>
                    <h1>System Dashboard</h1>
                    <p class="page-description">Overview of campus events, user activity, and system participation
                        metrics.</p>
                </div>
            </section>

            <!-- Statistics Grid -->
            <section class="dashboard-stats" aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="visually-hidden">System statistics</h2>

                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Users</h3>
                    </div>
                    <p class="stat-value"><?= $stats['total_users'] ?></p>
                    <p class="stat-description"><?= $stats['active_users'] ?> active accounts</p>
                </article>

                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Events</h3>
                    </div>
                    <p class="stat-value"><?= $stats['total_events'] ?></p>
                    <p class="stat-description"><?= $stats['upcoming_events'] ?> upcoming</p>
                </article>

                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Registrations</h3>
                    </div>
                    <p class="stat-value"><?= $stats['total_registrations'] ?></p>
                    <p class="stat-description">Total participant sign-ups</p>
                </article>

                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Volunteer Apps</h3>
                    </div>
                    <p class="stat-value"><?= $stats['total_applications'] ?></p>
                    <p class="stat-description">Total volunteer applications</p>
                </article>
            </section>

            <!-- Recent Events Table -->
            <section class="dashboard-section" aria-labelledby="recent-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="recent-events-heading">Recent Events</h2>
                        <p>All campus events (showing 5 at a time, scroll to view more).</p>
                    </div>
                </div>
                <div class="table-wrapper recent-events-scrollbox">
                    <table>
                        <caption class="visually-hidden">List of recent campus events</caption>
                        <thead>
                            <tr>
                                <th scope="col">Event Title</th>
                                <th scope="col">Organizer</th>
                                <th scope="col">Date</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentEvents)): ?>
                                <?php foreach ($recentEvents as $event):
                                    $statusClass = match ($event['status']) {
                                        'published' => 'status-open',
                                        'cancelled' => 'status-rejected',
                                        'closed' => 'status-full',
                                        default => 'status-pending',
                                    };
                                    ?>
                                    <tr>
                                        <td><strong><?= h($event['title']) ?></strong></td>
                                        <td><?= h($event['organizer_name']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($event['start_datetime']))) ?></td>
                                        <td><span class="status <?= $statusClass ?>"><?= h(ucfirst($event['status'])) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 1.5rem; color: #6b7280;">No events
                                        have been created yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Create Organizer Account -->
            <section class="dashboard-section" id="create-organizer" aria-labelledby="create-organizer-heading">
                <div class="section-header">
                    <div>
                        <h2 id="create-organizer-heading"> Create Organizer Account</h2>
                        <p>Provision a new organizer account directly from the dashboard.</p>
                    </div>
                </div>

                <?php if ($dashNotice !== ''): ?>
                    <p role="status" class="form-message" style="background:#f0fdf4;border-color:#86efac;color:#166534;">
                        <?= h($dashNotice) ?>
                    </p>
                <?php endif; ?>
                <?php if ($dashError !== ''): ?>
                    <p role="alert" class="form-message"><?= h($dashError) ?></p>
                <?php endif; ?>

                <form method="post" action="admin-dashboard.php#create-organizer"
                    style="display:grid;grid-template-columns:1fr 1fr;gap:1rem 1.5rem;max-width:700px;">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="admin_action" value="create_organizer">

                    <!-- Full Name -->
                    <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <label for="org_full_name" style="font-weight:600;font-size:0.9rem;color:#374151;">Full Name
                            <span style="color:#dc2626;">*</span></label>
                        <input type="text" id="org_full_name" name="full_name" autocomplete="name" required
                            placeholder="Ex: Ohil Raj"
                            style="padding:0.6rem 0.75rem;border:1px solid #fecaca;border-radius:6px;font-size:0.95rem;width:100%;">
                    </div>

                    <!-- Email -->
                    <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <label for="org_email" style="font-weight:600;font-size:0.9rem;color:#374151;">Email Address
                            <span style="color:#dc2626;">*</span></label>
                        <input type="email" id="org_email" name="email" autocomplete="email" required
                            placeholder="organizer@campus.edu"
                            style="padding:0.6rem 0.75rem;border:1px solid #fecaca;border-radius:6px;font-size:0.95rem;width:100%;">
                    </div>

                    <!-- Password -->
                    <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <label for="org_password" style="font-weight:600;font-size:0.9rem;color:#374151;">Password <span
                                style="color:#dc2626;">*</span></label>
                        <input type="password" id="org_password" name="password" autocomplete="new-password" required
                            placeholder="Minimum 8 characters"
                            style="padding:0.6rem 0.75rem;border:1px solid #fecaca;border-radius:6px;font-size:0.95rem;width:100%;">
                    </div>

                    <!-- Confirm Password -->
                    <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <label for="org_confirm_password"
                            style="font-weight:600;font-size:0.9rem;color:#374151;">Confirm Password <span
                                style="color:#dc2626;">*</span></label>
                        <input type="password" id="org_confirm_password" name="confirm_password"
                            autocomplete="new-password" required placeholder="Repeat password"
                            style="padding:0.6rem 0.75rem;border:1px solid #fecaca;border-radius:6px;font-size:0.95rem;width:100%;">
                    </div>

                    <!-- Role (display-only, locked to organizer) -->
                    <div style="display:flex;flex-direction:column;gap:0.3rem;">
                        <label style="font-weight:600;font-size:0.9rem;color:#374151;">Role</label>
                        <div
                            style="padding:0.6rem 0.75rem;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;font-size:0.95rem;color:#991b1b;font-weight:600;">
                            Organizer</div>
                    </div>

                    <!-- Submit -->
                    <div style="display:flex;align-items:flex-end;">
                        <button type="submit" class="btn btn-primary"
                            style="width:100%;padding:0.65rem 1rem;font-size:0.95rem;">
                            Create Organizer Account
                        </button>
                    </div>
                </form>
            </section>

            <!-- Quick Actions -->
            <section class="dashboard-section" aria-labelledby="quick-actions-heading">
                <div class="section-header">
                    <div>
                        <h2 id="quick-actions-heading">Administrative Quick Actions</h2>
                        <p>Navigate to core system management tools.</p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="administration-reports.php#users" class="action-card">
                        <h3>Manage Users</h3>
                        <p>View, activate, or deactivate user accounts.</p>
                    </a>
                    <a href="administration-reports.php#events" class="action-card">
                        <h3>Monitor Events</h3>
                        <p>View event statuses and details.</p>
                    </a>
                    <a href="administration-reports.php#reports" class="action-card">
                        <h3>View Reports</h3>
                        <p>Analyze feedback and system participation data.</p>
                    </a>
                </div>
            </section>

        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>

    <script src="../public/js/main.js"></script>
    <script>
        // Simple mobile menu toggle script (matches organizer logic)
        const menuToggle = document.querySelector('.menu-toggle');
        const sidebar = document.getElementById('admin-navigation');
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', function () {
                const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
                menuToggle.setAttribute('aria-expanded', String(!isExpanded));
                sidebar.classList.toggle('sidebar-open');
            });
        }
    </script>
</body>

</html>