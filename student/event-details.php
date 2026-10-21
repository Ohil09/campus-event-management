<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');
require_once __DIR__ . '/../config/database.php';

$studentName = (string) ($_SESSION['full_name'] ?? 'Student');

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$id    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = null;

if ($id && $id > 0) {
    $stmt = $conn->prepare(
        "SELECT e.event_id, e.title, e.description, e.start_datetime, e.end_datetime,
                e.venue, e.capacity, e.registration_deadline, e.status,
                c.category_name
         FROM events e
         INNER JOIN categories c ON e.category_id = c.category_id
         WHERE e.event_id = ? AND e.status = 'published' AND c.status = 'active'
               AND e.start_datetime >= NOW()"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

// Allow registration only while the deadline has not passed.
$registrationOpen = false;
if ($event) {
    $registrationOpen = strtotime($event['registration_deadline']) >= time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Event details and registration">
    <title><?= $event ? h($event['title']) : 'Event Not Found' ?> | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/student.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent</a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="student-navigation">&#9776;</button>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= h($studentName) ?></span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">
        <aside class="sidebar" id="student-navigation" aria-label="Student navigation">
            <nav>
                <h2 class="sidebar-title">Student</h2>
                <ul class="sidebar-menu">
                    <li><a href="index.php" class="nav-link active"><span aria-hidden="true">&#128218;</span><span>Discover Events</span></a></li>
                    <li><a href="my-events.php" class="nav-link"><span aria-hidden="true">&#128196;</span><span>My Events</span></a></li>
                    <li><a href="volunteer-opportunities.php" class="nav-link"><span aria-hidden="true">&#129309;</span><span>Volunteer</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <?php if (!$event): ?>
                <section class="page-heading">
                    <div>
                        <p class="breadcrumb">Student / Event Details</p>
                        <h1>Event Not Found</h1>
                        <p class="page-description">This event may not exist, may not be published, or may no longer be upcoming.</p>
                    </div>
                </section>
                <section class="dashboard-section">
                    <div class="empty-state">
                        <h3>No event to display</h3>
                        <p>Go back and browse all available events.</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top:1rem;">Browse Events</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="page-heading">
                    <div>
                        <p class="breadcrumb">Student / Discover Events / Event Details</p>
                        <h1><?= h($event['title']) ?></h1>
                        <p class="page-description"><?= h($event['category_name']) ?></p>
                    </div>
                    <?php if ($registrationOpen): ?>
                        <a href="register-event.php?id=<?= (int) $event['event_id'] ?>" class="btn btn-primary">Register for Event</a>
                    <?php endif; ?>
                </section>

                <section class="dashboard-section" aria-labelledby="event-details-heading">
                    <div class="section-header">
                        <div>
                            <h2 id="event-details-heading">Event Details</h2>
                            <p>Full information about this event.</p>
                        </div>
                        <a href="index.php" class="btn btn-primary">Back to Events</a>
                    </div>

                    <div style="margin-bottom:1.5rem; line-height:1.8; color:#4b5563;">
                        <?= nl2br(h($event['description'])) ?>
                    </div>

                    <div class="table-wrapper">
                        <table style="min-width:auto;">
                            <caption class="visually-hidden">Event information</caption>
                            <tbody>
                                <tr>
                                    <th scope="row" style="width:200px;">📅 Date</th>
                                    <td><?= date('d M Y', strtotime($event['start_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">🕒 Start Time</th>
                                    <td><?= date('h:i A', strtotime($event['start_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">🕒 End Time</th>
                                    <td><?= date('h:i A', strtotime($event['end_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">📍 Venue</th>
                                    <td><?= h($event['venue']) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">👥 Maximum Capacity</th>
                                    <td><?= (int) $event['capacity'] ?> students</td>
                                </tr>
                                <tr>
                                    <th scope="row">📝 Registration Deadline</th>
                                    <td><?= date('d M Y, h:i A', strtotime($event['registration_deadline'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">Status</th>
                                    <td>
                                        <?php if ($registrationOpen): ?>
                                            <span class="status status-open">Registration Open</span>
                                        <?php else: ?>
                                            <span class="status status-full">Registration Closed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!$registrationOpen): ?>
                        <div class="form-message form-message-warning" style="margin-top:1rem;">
                            The registration deadline has passed for this event.
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>
    <script src="../public/js/main.js"></script>
</body>
</html>
<?php
$conn->close();
?>
