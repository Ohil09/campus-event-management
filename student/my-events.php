<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');
require_once __DIR__ . '/../config/database.php';

$studentName = (string) ($_SESSION['full_name'] ?? 'Student');
$userId      = (int) $_SESSION['user_id'];

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message     = '';
$messageType = 'success';

// Handle cancellation and feedback submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string) ($_POST['action'] ?? '');
    $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$eventId || $eventId < 1) {
        $message     = 'Invalid event.';
        $messageType = 'error';
    } elseif ($action === 'cancel') {
        try {
            $stmt = $conn->prepare(
                "UPDATE event_registrations SET status = 'cancelled'
                 WHERE event_id = ? AND user_id = ? AND status = 'registered'"
            );
            $stmt->bind_param('ii', $eventId, $userId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = 'Registration cancelled successfully.';
            } else {
                $message     = 'No active registration was found.';
                $messageType = 'error';
            }
            $stmt->close();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $message     = 'Unable to cancel registration. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'feedback') {
        $rating   = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $comments = trim((string) ($_POST['feedback'] ?? ''));

        if (
            $rating === false || $rating === null ||
            $rating < 1 || $rating > 5 ||
            $comments === '' || mb_strlen($comments) > 2000
        ) {
            $message     = 'Choose a rating and enter comments (up to 2000 characters).';
            $messageType = 'error';
        } else {
            try {
                // Feedback is accepted only for a registered event that has already ended.
                $check = $conn->prepare(
                    "SELECT er.registration_id FROM event_registrations er
                     JOIN events e ON e.event_id = er.event_id
                     WHERE er.event_id = ? AND er.user_id = ?
                       AND er.status = 'registered' AND e.end_datetime < NOW()
                     LIMIT 1"
                );
                $check->bind_param('ii', $eventId, $userId);
                $check->execute();
                $eligible = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$eligible) {
                    $message     = 'Feedback is available after the event ends, for active registrations.';
                    $messageType = 'error';
                } else {
                    $stmt = $conn->prepare(
                        "INSERT INTO feedback (event_id, user_id, rating, comment)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param('iiis', $eventId, $userId, $rating, $comments);
                    $stmt->execute();
                    $stmt->close();
                    $message = 'Feedback submitted successfully.';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $message     = 'Unable to save feedback. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Fetch this student's registrations.
$stmt = $conn->prepare(
    "SELECT e.event_id, e.title, e.description, e.start_datetime, e.end_datetime,
            e.venue, er.status AS registration_status,
            a.status AS attendance_status
     FROM event_registrations er
     JOIN events e ON e.event_id = er.event_id
     LEFT JOIN attendance a ON a.event_id = er.event_id AND a.user_id = er.user_id
     WHERE er.user_id = ?
     ORDER BY e.start_datetime DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="View your registered events, cancel registrations, and submit feedback">
    <title>My Events | Campus Event Management System</title>
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
                    <li><a href="index.php" class="nav-link"><span aria-hidden="true">&#128218;</span><span>Discover Events</span></a></li>
                    <li><a href="my-events.php" class="nav-link active" aria-current="page"><span aria-hidden="true">&#128196;</span><span>My Events</span></a></li>
                    <li><a href="volunteer-opportunities.php" class="nav-link"><span aria-hidden="true">&#129309;</span><span>Volunteer</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Student / My Events</p>
                    <h1>My Events &amp; Participation</h1>
                    <p class="page-description">View your registrations, attendance, and submit event feedback.</p>
                </div>
                <a href="index.php" class="btn btn-primary">Discover Events</a>
            </section>

            <?php if ($message !== ''): ?>
                <?php if ($messageType === 'error'): ?>
                    <p role="alert" class="form-message"><?= h($message) ?></p>
                <?php else: ?>
                    <p role="status" class="form-message"><?= h($message) ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <section class="dashboard-section" aria-labelledby="registrations-heading">
                <div class="section-header">
                    <div>
                        <h2 id="registrations-heading">Your Registrations</h2>
                        <p>All events you have registered for, with status and actions.</p>
                    </div>
                </div>

                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <h3>No Registrations Yet</h3>
                        <p>Browse events and register for one to see it here.</p>
                        <a href="index.php" class="btn btn-primary" style="margin-top:1rem;">Discover Events</a>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table>
                            <caption class="visually-hidden">Your event registrations</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Event</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Time</th>
                                    <th scope="col">Venue</th>
                                    <th scope="col">Registration</th>
                                    <th scope="col">Attendance</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event):
                                    $regStatus   = (string) $event['registration_status'];
                                    $attendance   = $event['attendance_status'];
                                    $isPast       = strtotime($event['end_datetime']) < time();
                                    $isUpcoming   = strtotime($event['start_datetime']) > time();
                                    $regClass     = $regStatus === 'registered' ? 'status-registered' : 'status-cancelled';
                                ?>
                                    <tr>
                                        <td><strong><?= h($event['title']) ?></strong></td>
                                        <td><?= date('d M Y', strtotime($event['start_datetime'])) ?></td>
                                        <td><?= date('h:i A', strtotime($event['start_datetime'])) ?> – <?= date('h:i A', strtotime($event['end_datetime'])) ?></td>
                                        <td><?= h($event['venue']) ?></td>
                                        <td><span class="status <?= h($regClass) ?>"><?= h(ucfirst($regStatus)) ?></span></td>
                                        <td>
                                            <?php if ($attendance !== null): ?>
                                                <span class="status status-<?= h($attendance) ?>"><?= h(ucfirst($attendance)) ?></span>
                                            <?php else: ?>
                                                <span style="color:#7a8491;font-size:0.85rem;">Not recorded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="event-details.php?id=<?= (int) $event['event_id'] ?>" class="table-action">View</a>
                                            <?php if ($regStatus === 'registered' && $isUpcoming): ?>
                                                &nbsp;
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this registration?');">
                                                    <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <button type="submit" class="table-action" style="color:#b52b38;">Cancel</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php
            // Show feedback forms for past events that student is registered for
            $pastEvents = array_filter($events, function($e) {
                return $e['registration_status'] === 'registered'
                    && strtotime($e['end_datetime']) < time();
            });
            ?>
            <?php if (!empty($pastEvents)): ?>
                <section class="dashboard-section" aria-labelledby="feedback-heading">
                    <div class="section-header">
                        <div>
                            <h2 id="feedback-heading">Event Feedback</h2>
                            <p>Share your feedback for completed events.</p>
                        </div>
                    </div>

                    <?php foreach ($pastEvents as $event): ?>
                        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:1.25rem; margin-bottom:1rem;">
                            <h3 style="color:#0d7377; font-size:1rem; margin-bottom:0.75rem;"><?= h($event['title']) ?></h3>
                            <form method="post" class="event-form" style="gap:1rem;">
                                <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                                <input type="hidden" name="action" value="feedback">

                                <div class="form-group">
                                    <label for="rating-<?= (int) $event['event_id'] ?>">Rating</label>
                                    <select id="rating-<?= (int) $event['event_id'] ?>" name="rating" required>
                                        <option value="">Select rating</option>
                                        <option value="5">5 - Excellent</option>
                                        <option value="4">4 - Very good</option>
                                        <option value="3">3 - Good</option>
                                        <option value="2">2 - Fair</option>
                                        <option value="1">1 - Poor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="feedback-<?= (int) $event['event_id'] ?>">Comments</label>
                                    <textarea id="feedback-<?= (int) $event['event_id'] ?>" name="feedback" rows="3" maxlength="2000" placeholder="Share your feedback..." required></textarea>
                                </div>
                                <div class="form-actions" style="border:0;padding:0;">
                                    <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
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
