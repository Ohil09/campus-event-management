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

// 3. Session Variables
$adminName = (string) ($_SESSION['full_name'] ?? 'Administrator');

// Helper function for output escaping
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

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

    // 5. Fetch Recent Events (Last 5 created)
    $recentStmt = $conn->prepare(
        "SELECT e.event_id, e.title, e.start_datetime, e.status, u.full_name AS organizer_name
         FROM events e
         INNER JOIN users u ON e.organizer_id = u.user_id
         ORDER BY e.event_id DESC
         LIMIT 5"
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
    <!-- Admin specific CSS for Red/White theme -->
    <link rel="stylesheet" href="../public/css/admin.css">
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
                    <li><a href="admin-dashboard.php" class="nav-link active" aria-current="page"><span
                                aria-hidden="true">&#128202;</span><span>Dashboard</span></a></li>
                    <li><a href="administration-attendance-reports.php" class="nav-link"><span
                                aria-hidden="true">&#128101;</span><span>Users & Events</span></a></li>
                    <li><a href="administration-attendance-reports.php#attendance" class="nav-link"><span
                                aria-hidden="true">&#128203;</span><span>Attendance</span></a></li>
                    <li><a href="administration-attendance-reports.php#reports" class="nav-link"><span
                                aria-hidden="true">&#128200;</span><span>Reports & Feedback</span></a></li>
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
                        <p>The 5 most recently created events in the system.</p>
                    </div>
                </div>
                <div class="table-wrapper">
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

            <!-- Quick Actions -->
            <section class="dashboard-section" aria-labelledby="quick-actions-heading">
                <div class="section-header">
                    <div>
                        <h2 id="quick-actions-heading">Administrative Quick Actions</h2>
                        <p>Navigate to core system management tools.</p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="administration-attendance-reports.php#users" class="action-card">
                        <h3>Manage Users</h3>
                        <p>View, activate, or deactivate user accounts.</p>
                    </a>
                    <a href="administration-attendance-reports.php#attendance" class="action-card">
                        <h3>Mark Attendance</h3>
                        <p>Record participant and volunteer attendance.</p>
                    </a>
                    <a href="administration-attendance-reports.php#reports" class="action-card">
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