<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');
require_once __DIR__ . '/../config/database.php';

$studentName = (string) ($_SESSION['full_name'] ?? 'Student');

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message     = '';
$messageType = 'info';
$event       = null;
$id          = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT);
$id          = ($id !== false && $id > 0) ? $id : 0;
$userId      = (int) $_SESSION['user_id'];

if ($id < 1) {
    $message     = 'Invalid event. Please open an event from Discover Events.';
    $messageType = 'error';
} else {
    // Load the event from the database.
    $stmt = $conn->prepare(
        "SELECT e.event_id, e.title, e.start_datetime, e.end_datetime,
                e.venue, e.capacity, e.registration_deadline, e.status
         FROM events e
         INNER JOIN categories c ON c.category_id = e.category_id
         WHERE e.event_id = ? AND e.status = 'published' AND c.status = 'active'"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$event) {
        $message     = 'Event not found or it is no longer available for registration.';
        $messageType = 'error';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Lock the event row while checking capacity and registering.
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT event_id, title, start_datetime, end_datetime,
                        venue, capacity, registration_deadline, status
                 FROM events
                 WHERE event_id = ? AND status = 'published'
                 FOR UPDATE"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if (!$event) {
                throw new RuntimeException('This event is no longer available.');
            }

            if (strtotime($event['registration_deadline']) < time()) {
                throw new RuntimeException('The registration deadline has passed.');
            }

            if (strtotime($event['start_datetime']) <= time()) {
                throw new RuntimeException('Registration is not available because this event has already started.');
            }

            // Check whether the student is already registered.
            $stmt = $conn->prepare(
                "SELECT registration_id FROM event_registrations
                 WHERE event_id = ? AND user_id = ? AND status = 'registered' LIMIT 1"
            );
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $alreadyRegistered = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($alreadyRegistered) {
                throw new RuntimeException('You are already registered for this event.');
            }

            // Check capacity using active registrations.
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS total FROM event_registrations
                 WHERE event_id = ? AND status = 'registered'"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $registrationCount = (int) $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            if ($registrationCount >= (int) $event['capacity']) {
                throw new RuntimeException('Sorry, this event has reached its registration capacity.');
            }

            // Detect overlap with another event the student registered for.
            $stmt = $conn->prepare(
                "SELECT e.title FROM event_registrations er
                 INNER JOIN events e ON e.event_id = er.event_id
                 WHERE er.user_id = ? AND er.status = 'registered'
                   AND e.event_id <> ? AND e.status <> 'cancelled'
                   AND e.start_datetime < ? AND e.end_datetime > ?
                 LIMIT 1"
            );
            $stmt->bind_param('iiss', $userId, $id, $event['end_datetime'], $event['start_datetime']);
            $stmt->execute();
            $conflict = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($conflict) {
                throw new RuntimeException(
                    'Schedule conflict with "' . $conflict['title'] .
                    '". Please review your schedule before registering.'
                );
            }

            // Restore a cancelled registration if one exists.
            $stmt = $conn->prepare(
                "SELECT registration_id FROM event_registrations
                 WHERE event_id = ? AND user_id = ? AND status = 'cancelled'
                 ORDER BY registration_id DESC LIMIT 1"
            );
            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $cancelled = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cancelled) {
                $registrationId = (int) $cancelled['registration_id'];
                $stmt = $conn->prepare(
                    "UPDATE event_registrations SET status = 'registered', registered_at = NOW()
                     WHERE registration_id = ?"
                );
                $stmt->bind_param('i', $registrationId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO event_registrations (event_id, user_id, status, registered_at)
                     VALUES (?, ?, 'registered', NOW())"
                );
                $stmt->bind_param('ii', $id, $userId);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $message     = 'Registration successful! Your event registration has been saved.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof RuntimeException) {
                $message     = $e->getMessage();
                $messageType = 'warning';
            } else {
                error_log('Event registration error: ' . $e->getMessage());
                $message     = 'Something went wrong while registering. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$canRegister = $event
    && $event['status'] === 'published'
    && strtotime($event['registration_deadline']) >= time()
    && strtotime($event['start_datetime']) > time();

if ($event && !$canRegister && $message === '') {
    $message     = 'Registration is closed for this event.';
    $messageType = 'warning';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for a campus event">
    <title>Event Registration | Campus Event Management System</title>
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
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Student / Discover Events / Registration</p>
                    <h1>Event Registration</h1>
                    <p class="page-description"><?= $event ? 'Confirm your registration for this event.' : 'Unable to process registration.' ?></p>
                </div>
            </section>

            <?php if ($event): ?>
                <section class="dashboard-section" aria-labelledby="registration-heading">
                    <div class="section-header">
                        <div>
                            <h2 id="registration-heading"><?= h($event['title']) ?></h2>
                            <p>Review the event details below and confirm your registration.</p>
                        </div>
                        <a href="event-details.php?id=<?= (int) $event['event_id'] ?>" class="text-link">← Event Details</a>
                    </div>

                    <div class="table-wrapper" style="margin-bottom:1.25rem;">
                        <table style="min-width:auto;">
                            <caption class="visually-hidden">Event registration details</caption>
                            <tbody>
                                <tr>
                                    <th scope="row" style="width:200px;">📅 Date</th>
                                    <td><?= date('d M Y', strtotime($event['start_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">🕒 Start</th>
                                    <td><?= date('h:i A', strtotime($event['start_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">🕒 End</th>
                                    <td><?= date('h:i A', strtotime($event['end_datetime'])) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">📍 Venue</th>
                                    <td><?= h($event['venue']) ?></td>
                                </tr>
                                <tr>
                                    <th scope="row">📝 Deadline</th>
                                    <td><?= date('d M Y, h:i A', strtotime($event['registration_deadline'])) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($message !== ''): ?>
                        <?php
                            $roleAttr = ($messageType === 'success') ? 'status' : (($messageType === 'error') ? 'alert' : 'status');
                            $msgClass = 'form-message';
                            if ($messageType === 'error')   $msgClass .= '" role="alert';
                            if ($messageType === 'success') $msgClass .= '" role="status';
                            if ($messageType === 'warning') $msgClass .= ' form-message-warning';
                        ?>
                        <?php if ($messageType === 'success'): ?>
                            <p role="status" class="form-message"><?= h($message) ?></p>
                        <?php elseif ($messageType === 'error'): ?>
                            <p role="alert" class="form-message"><?= h($message) ?></p>
                        <?php else: ?>
                            <p role="status" class="form-message form-message-warning"><?= h($message) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($messageType !== 'success' && $canRegister): ?>
                        <form method="post" action="register-event.php?id=<?= (int) $id ?>" style="margin-top:1rem;">
                            <input type="hidden" name="id" value="<?= (int) $id ?>">
                            <button type="submit" class="btn btn-primary">Confirm Registration</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($messageType === 'success'): ?>
                        <div style="margin-top:1rem; display:flex; gap:0.75rem;">
                            <a href="my-events.php" class="btn btn-primary">View My Events</a>
                            <a href="index.php" class="btn btn-secondary">Browse More Events</a>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <section class="dashboard-section">
                    <?php if ($message !== ''): ?>
                        <p role="alert" class="form-message"><?= h($message) ?></p>
                    <?php endif; ?>
                    <div class="empty-state">
                        <h3>Unable to Register</h3>
                        <p>The requested event could not be found. Please browse available events.</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top:1rem;">Discover Events</a>
                    </div>
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
