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

// Helper function for HTML escaping
if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

// Reports & Feedback: Fetch aggregate metrics for this organizer's events
$reportStats = ['avg_rating' => 0, 'total_feedback' => 0];
$statsStmt = $conn->prepare(
    "SELECT 
        AVG(f.rating) AS avg_rating,
        COUNT(f.feedback_id) AS total_feedback
     FROM feedback f
     INNER JOIN events e ON f.event_id = e.event_id
     WHERE e.organizer_id = ?"
);
$statsStmt->bind_param('i', $organizerId);
$statsStmt->execute();
$statsRes = $statsStmt->get_result()->fetch_assoc();
if ($statsRes) {
    $reportStats['avg_rating'] = $statsRes['avg_rating'] ? round((float) $statsRes['avg_rating'], 1) : 0;
    $reportStats['total_feedback'] = (int) ($statsRes['total_feedback'] ?? 0);
}
$statsStmt->close();

// Fetch all events by this organizer for the feedback dropdown filter
$organizerEventsStmt = $conn->prepare(
    "SELECT event_id, title 
     FROM events 
     WHERE organizer_id = ? 
     ORDER BY start_datetime DESC"
);
$organizerEventsStmt->bind_param('i', $organizerId);
$organizerEventsStmt->execute();
$organizerEvents = $organizerEventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$organizerEventsStmt->close();

// Fetch recent feedback submissions across all events of this organizer
$fbStmt = $conn->prepare(
    "SELECT f.rating, f.comment, f.submitted_at, u.full_name, e.title AS event_title 
     FROM feedback f 
     INNER JOIN users u ON f.user_id = u.user_id 
     INNER JOIN events e ON f.event_id = e.event_id 
     WHERE e.organizer_id = ? 
     ORDER BY f.submitted_at DESC 
     LIMIT 10"
);
$fbStmt->bind_param('i', $organizerId);
$fbStmt->execute();
$recentFeedback = $fbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fbStmt->close();

// Per-Event Feedback logic
$feedbackEventId = filter_var($_GET['feedback_event_id'] ?? 0, FILTER_VALIDATE_INT);
$feedbackEventId = ($feedbackEventId !== false && $feedbackEventId > 0) ? $feedbackEventId : 0;
$eventFeedback = [];
$eventAvgRating = 0;
$eventFeedbackCount = 0;
$selectedEventTitle = '';

if ($feedbackEventId > 0) {
    // Verify event ownership by organizer and get event title
    $evTitleStmt = $conn->prepare("SELECT title FROM events WHERE event_id = ? AND organizer_id = ?");
    $evTitleStmt->bind_param('ii', $feedbackEventId, $organizerId);
    $evTitleStmt->execute();
    $evTitleRes = $evTitleStmt->get_result()->fetch_assoc();
    $selectedEventTitle = $evTitleRes ? (string) $evTitleRes['title'] : '';
    $evTitleStmt->close();

    if ($selectedEventTitle !== '') {
        $evFbStmt = $conn->prepare(
            "SELECT f.rating, f.comment, f.submitted_at, u.full_name 
             FROM feedback f 
             INNER JOIN users u ON f.user_id = u.user_id 
             WHERE f.event_id = ? 
             ORDER BY f.submitted_at DESC"
        );
        $evFbStmt->bind_param('i', $feedbackEventId);
        $evFbStmt->execute();
        $eventFeedback = $evFbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $evFbStmt->close();

        if (!empty($eventFeedback)) {
            $eventFeedbackCount = count($eventFeedback);
            $ratingSum = array_sum(array_column($eventFeedback, 'rating'));
            $eventAvgRating = round($ratingSum / $eventFeedbackCount, 1);
        }
    } else {
        $feedbackEventId = 0;
    }
}
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
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false"
                aria-controls="organizer-navigation">☰</button>
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
                    <li><a href="organizer-dashboard.php" class="nav-link active"
                            aria-current="page"><span>Dashboard</span></a></li>
                    <li><a href="event-management.php" class="nav-link"><span>Manage Events</span></a></li>
                    <li><a href="../volunteer/volunteer-management.php" class="nav-link"><span>Volunteers</span></a>
                    </li>
                    <li><a href="event-management.php#participants" class="nav-link"><span>Participants</span></a></li>
                    <li><a href="organizer-dashboard.php#reports" class="nav-link"><span>Reports &amp; Feedback</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Organizer</p>
                    <h1>Organizer Dashboard</h1>
                    <p class="page-description">Manage your campus events, registrations and participant activity from
                        one place.</p>
                </div>
                <a href="event-management.php#create-event" class="btn btn-primary">Create New Event</a>
            </section>

            <section class="dashboard-stats" aria-labelledby="dashboard-summary-heading">
                <h2 id="dashboard-summary-heading" class="visually-hidden">Event summary</h2>
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Events</h3>
                    </div>
                    <p class="stat-value"><?= $totalEvents ?></p>
                    <p class="stat-description">Events created by you</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Upcoming Events</h3>
                    </div>
                    <p class="stat-value"><?= $upcomingEvents ?></p>
                    <p class="stat-description">Scheduled events</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Registrations</h3>
                    </div>
                    <p class="stat-value"><?= $totalRegistrations ?></p>
                    <p class="stat-description">Total participant registrations</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Volunteers</h3>
                    </div>
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
                                        <td><a href="event-management.php?event_id=<?= $eventId ?>"
                                                class="table-action">Manage</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">No upcoming published events found. Create an event to get started.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Reports & Feedback Section -->
            <section id="reports" class="dashboard-section" aria-labelledby="reports-feedback-heading">
                <div class="section-header">
                    <div>
                        <h2 id="reports-feedback-heading">Reports &amp; Feedback</h2>
                        <p>Participant satisfaction and feedback overview for your events.</p>
                    </div>
                </div>

                <!-- Global stats -->
                <div class="dashboard-stats" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 0;">
                    <article class="stat-card">
                        <div class="stat-card-header">
                            <h3>Average Rating</h3>
                        </div>
                        <p class="stat-value"><?= $reportStats['avg_rating'] ?> / 5</p>
                        <p class="stat-description">Across all feedback</p>
                    </article>
                    <article class="stat-card">
                        <div class="stat-card-header">
                            <h3>Total Feedback</h3>
                        </div>
                        <p class="stat-value"><?= $reportStats['total_feedback'] ?></p>
                        <p class="stat-description">Submissions received</p>
                    </article>
                </div>
            </section>

            <!-- Feedback by Event Section -->
            <section id="feedback-by-event" class="dashboard-section" aria-labelledby="feedback-event-heading">
                <div class="section-header">
                    <div>
                        <h2 id="feedback-event-heading">Feedback by Event</h2>
                        <p>Filter feedback by specific event or review recent submissions.</p>
                    </div>
                </div>

                <!-- Per-event feedback filter -->
                <form method="get" action="organizer-dashboard.php#feedback-by-event"
                    style="display:flex;gap:1rem;margin-bottom:1.5rem;align-items:flex-end;flex-wrap:wrap;">
                    <div style="flex:1;min-width:200px;">
                        <label for="feedback_event"
                            style="font-weight:600;font-size:0.9rem;margin-bottom:0.3rem;display:block;color:#374151;">Select
                            Event</label>
                        <select id="feedback_event" name="feedback_event_id" onchange="this.form.submit()"
                            style="width:100%;padding:0.6rem;border:1px solid #dfe4ea;border-radius:6px;background:#fff;color:#1f2937;">
                            <?php if (empty($organizerEvents)): ?>
                                <option value="0" selected disabled>-- No events found --</option>
                            <?php else: ?>
                                <option value="0" <?= $feedbackEventId === 0 ? 'selected' : '' ?>>-- All Events --</option>
                                <?php foreach ($organizerEvents as $ev): ?>
                                    <option value="<?= (int) $ev['event_id'] ?>" <?= $feedbackEventId === (int) $ev['event_id'] ? 'selected' : '' ?>><?= h($ev['title']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($feedbackEventId > 0): ?>
                        <a href="organizer-dashboard.php#feedback-by-event" class="btn btn-secondary"
                            style="padding:0.6rem 1.2rem;min-height:auto;">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if ($feedbackEventId > 0 && $selectedEventTitle !== ''): ?>
                    <!-- Per-event average score card -->
                    <div
                        style="background:linear-gradient(135deg,#f0f4f8,#fff);border:1px solid #cbd5e1;border-radius:10px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                        <div>
                            <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.2rem;">Average Score for</p>
                            <p style="font-weight:700;font-size:1.05rem;color:#163a5f;margin:0;"><?= h($selectedEventTitle) ?>
                            </p>
                        </div>
                        <div style="margin-left:auto;text-align:center;">
                            <?php
                            $stars = '';
                            $fullStars = (int) floor($eventAvgRating);
                            $hasHalf = ($eventAvgRating - $fullStars) >= 0.5;
                            for ($s = 1; $s <= 5; $s++) {
                                if ($s <= $fullStars) {
                                    $stars .= '<span style="color:#f59e0b;font-size:1.4rem;">&#9733;</span>';
                                } elseif ($hasHalf && $s === $fullStars + 1) {
                                    $stars .= '<span style="color:#f59e0b;font-size:1.4rem;">&#9734;</span>';
                                } else {
                                    $stars .= '<span style="color:#d1d5db;font-size:1.4rem;">&#9734;</span>';
                                }
                            }
                            echo $stars;
                            ?>
                            <p style="font-size:1.8rem;font-weight:800;color:#163a5f;margin:0.2rem 0 0;">
                                <?= $eventAvgRating ?> <span style="font-size:1rem;font-weight:500;color:#6b7280;">/
                                    5</span>
                            </p>
                            <p style="font-size:0.8rem;color:#6b7280;margin:0;"><?= $eventFeedbackCount ?>
                                response<?= $eventFeedbackCount !== 1 ? 's' : '' ?></p>
                        </div>
                    </div>

                    <!-- Per-event feedback table -->
                    <?php if (!empty($eventFeedback)): ?>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Rating</th>
                                        <th>Comments</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eventFeedback as $fb): ?>
                                        <tr>
                                            <td><?= h($fb['full_name']) ?></td>
                                            <td>
                                                <span class="status status-open"><?= (int) $fb['rating'] ?> / 5</span>
                                            </td>
                                            <td><?= h($fb['comment'] ?: 'No comment provided.') ?></td>
                                            <td><?= h(date('d M Y', strtotime($fb['submitted_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:#6b7280;text-align:center;padding:2rem;">No feedback submitted for this event yet.</p>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Default: recent feedback across all events -->
                    <h3 style="color:#163a5f;margin-bottom:1rem;font-size:1.05rem;font-weight:600;">Recent Feedback Submissions</h3>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Student</th>
                                    <th>Rating</th>
                                    <th>Comments</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentFeedback)):
                                    foreach ($recentFeedback as $fb): ?>
                                        <tr>
                                            <td><strong><?= h($fb['event_title']) ?></strong></td>
                                            <td><?= h($fb['full_name']) ?></td>
                                            <td><span class="status status-open"><?= (int) $fb['rating'] ?> / 5</span></td>
                                            <td><?= h($fb['comment'] ?: 'No comment provided.') ?></td>
                                            <td><?= h(date('d M Y', strtotime($fb['submitted_at']))) ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:1.5rem;color:#6b7280;">No feedback submitted yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
                                    <p><?= htmlspecialchars($activity['full_name'], ENT_QUOTES, 'UTF-8') ?> registered for
                                        <?= htmlspecialchars($activity['title'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <time
                                    datetime="<?= date('c', $registeredAt) ?>"><?= date('d M Y, h:i A', $registeredAt) ?></time>
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