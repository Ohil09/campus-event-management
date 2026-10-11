<?php
require_once __DIR__ . '/../auth/auth_check.php';
require_role('organizer');
require_once __DIR__ . '/../config/database.php';

$organizerId = (int) $_SESSION['user_id'];
$organizerName = $_SESSION['full_name'] ?? 'Organizer';

// Total events created by this organizer
$totalEvents = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE organizer_id = ?");
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$stmt->bind_result($totalEvents);
$stmt->fetch();
$stmt->close();
$totalEvents = (int) $totalEvents;

// Upcoming published events
$upcomingEvents = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM events
     WHERE organizer_id = ? AND status = 'published' AND start_datetime >= NOW()"
);
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$stmt->bind_result($upcomingEvents);
$stmt->fetch();
$stmt->close();
$upcomingEvents = (int) $upcomingEvents;

// Total participant registrations
$totalRegistrations = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM event_registrations er
     INNER JOIN events e ON er.event_id = e.event_id
     WHERE e.organizer_id = ? AND er.status = 'registered'"
);
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$stmt->bind_result($totalRegistrations);
$stmt->fetch();
$stmt->close();
$totalRegistrations = (int) $totalRegistrations;

// Total volunteer positions required
$totalVolunteerPositions = 0;
$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(vo.required_count), 0) FROM volunteer_opportunities vo
     INNER JOIN events e ON vo.event_id = e.event_id
     WHERE e.organizer_id = ?"
);
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$stmt->bind_result($totalVolunteerPositions);
$stmt->fetch();
$stmt->close();
$totalVolunteerPositions = (int) $totalVolunteerPositions;

// Next three published events
$stmt = $conn->prepare(
    "SELECT e.event_id, e.title, e.start_datetime, e.venue, e.capacity,
            COUNT(er.registration_id) AS registration_count
     FROM events e
     LEFT JOIN event_registrations er ON e.event_id = er.event_id AND er.status = 'registered'
     WHERE e.organizer_id = ? AND e.status = 'published' AND e.start_datetime >= NOW()
     GROUP BY e.event_id, e.title, e.start_datetime, e.venue, e.capacity
     ORDER BY e.start_datetime ASC
     LIMIT 3"
);
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$result = $stmt->get_result();
$upcomingEventRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Three most recent registrations
$stmt = $conn->prepare(
    "SELECT u.full_name, e.title, er.registered_at
     FROM event_registrations er
     INNER JOIN events e ON er.event_id = e.event_id
     INNER JOIN users u ON er.user_id = u.user_id
     WHERE e.organizer_id = ? AND er.status = 'registered'
     ORDER BY er.registered_at DESC
     LIMIT 3"
);
$stmt->bind_param("i", $organizerId);
$stmt->execute();
$result = $stmt->get_result();
$recentRegistrations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Organizer dashboard for the Campus Event and Volunteer Management System">
    <title>Organizer Dashboard | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/organizer.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent</a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="organizer-navigation">☰</button>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= htmlspecialchars($organizerName, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">
        <aside class="sidebar" id="organizer-navigation" aria-label="Organizer navigation">
            <nav>
                <h2 class="sidebar-title">Organizer</h2>
                <ul class="sidebar-menu">
                    <li><a href="organizer-dashboard.php" class="nav-link active" aria-current="page"><span aria-hidden="true">⌂</span><span>Dashboard</span></a></li>
                    <li><a href="event-management.php" class="nav-link"><span aria-hidden="true">▣</span><span>Manage Events</span></a></li>
                    <li><a href="event-management.php#participants" class="nav-link"><span aria-hidden="true">👥</span><span>Participants</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Organizer</p>
                    <h1>Organizer Dashboard</h1>
                    <p class="page-description">Manage your campus events, registrations and participant activity from one place.</p>
                </div>
                <a href="event-management.php#create-event" class="btn btn-primary">Create New Event</a>
            </section>

            <section class="dashboard-stats" aria-labelledby="dashboard-summary-heading">
                <h2 id="dashboard-summary-heading" class="visually-hidden">Event summary</h2>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Total Events</h3></div>
                    <p class="stat-value"><?= $totalEvents ?></p>
                    <p class="stat-description">Events created by you</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Upcoming Events</h3></div>
                    <p class="stat-value"><?= $upcomingEvents ?></p>
                    <p class="stat-description">Scheduled events</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Registrations</h3></div>
                    <p class="stat-value"><?= $totalRegistrations ?></p>
                    <p class="stat-description">Total participant registrations</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Volunteers</h3></div>
                    <p class="stat-value"><?= $totalVolunteerPositions ?></p>
                    <p class="stat-description">Volunteer positions required</p>
                </article>
            </section>

            <section class="dashboard-section" aria-labelledby="upcoming-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="upcoming-events-heading">Upcoming Events</h2>
                        <p>Recently scheduled events managed by you.</p>
                    </div>
                    <a href="event-management.php" class="text-link">View All Events</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Upcoming events managed by the organizer</caption>
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Registrations</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($upcomingEventRows)): ?>
                                <?php foreach ($upcomingEventRows as $event):
                                    $eventId = (int) $event['event_id'];
                                    $registrationCount = (int) $event['registration_count'];
                                    $capacity = (int) $event['capacity'];
                                    $isFull = $registrationCount >= $capacity;
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= date('d M Y', strtotime($event['start_datetime'])) ?></td>
                                        <td><?= htmlspecialchars($event['venue'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $registrationCount ?> / <?= $capacity ?></td>
                                        <td>
                                            <?php if ($isFull): ?>
                                                <span class="status status-full">Full</span>
                                            <?php else: ?>
                                                <span class="status status-open">Open</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><a href="event-management.php?event_id=<?= $eventId ?>" class="table-action">Manage</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No upcoming published events found. Create an event to get started.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard-section" aria-labelledby="quick-actions-heading">
                <div class="section-header">
                    <div>
                        <h2 id="quick-actions-heading">Quick Actions</h2>
                        <p>Common organizer tasks.</p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="event-management.php#create-event" class="action-card">
                        <h3>Create Event</h3>
                        <p>Add a new campus event.</p>
                    </a>
                    <a href="event-management.php#participants" class="action-card">
                        <h3>View Participants</h3>
                        <p>Review event registrations.</p>
                    </a>
                    <a href="../volunteer/volunteer-management.php" class="action-card">
                        <h3>Manage Volunteers</h3>
                        <p>Review volunteer requirements.</p>
                    </a>
                </div>
            </section>

            <section class="dashboard-section" aria-labelledby="recent-activity-heading">
                <div class="section-header">
                    <div>
                        <h2 id="recent-activity-heading">Recent Activity</h2>
                        <p>Latest activity related to your events.</p>
                    </div>
                </div>
                <ul class="activity-list">
                    <?php if (!empty($recentRegistrations)): ?>
                        <?php foreach ($recentRegistrations as $activity):
                            $registeredAt = strtotime($activity['registered_at']);
                        ?>
                            <li class="activity-item">
                                <div>
                                    <strong>New participant registration</strong>
                                    <p><?= htmlspecialchars($activity['full_name'], ENT_QUOTES, 'UTF-8') ?> registered for <?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <time datetime="<?= date('c', $registeredAt) ?>"><?= date('d M Y, h:i A', $registeredAt) ?></time>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="activity-item">
                            <div>
                                <strong>No recent registrations</strong>
                                <p>New participant registrations for your events will appear here.</p>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>
    <script src="../public/js/main.js"></script>
</body>
</html>