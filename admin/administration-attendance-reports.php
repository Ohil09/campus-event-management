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

// 3. Session & Helpers
$adminName = (string) ($_SESSION['full_name'] ?? 'Administrator');
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$notice = '';
$formError = '';
/* PER-EVENT FEEDBACK - updated */

if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $notice = htmlspecialchars(urldecode((string) $_GET['notice']), ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------
   Handle POST Actions (User Status & Attendance)
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn !== null) {
    $postedToken = trim((string) ($_POST['csrf_token'] ?? ''));
    $action = trim((string) ($_POST['admin_action'] ?? ''));

    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $formError = 'Session expired. Please refresh and try again.';
    } else {
        try {
            if ($action === 'toggle_user_status') {
                $userId = filter_var($_POST['user_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($userId !== false && $userId > 0) {
                    // Prevent admin from deactivating themselves
                    if ((int) $userId === (int) $_SESSION['user_id']) {
                        $formError = 'You cannot change your own account status.';
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = ?");
                        $stmt->bind_param('i', $userId);
                        $stmt->execute();
                        $stmt->close();
                        header('Location: administration-attendance-reports.php?notice=' . urlencode('User status updated successfully.') . '#users');
                        exit;
                    }
                }
            } elseif ($action === 'mark_attendance') {
                $eventId = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT);
                $attendanceData = $_POST['attendance'] ?? []; // Array of user_id => status

                if ($eventId !== false && $eventId > 0 && is_array($attendanceData)) {
                    $stmt = $conn->prepare(
                        "INSERT INTO attendance (event_id, user_id, status) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE status = VALUES(status)"
                    );
                    foreach ($attendanceData as $uid => $status) {
                        $uid = (int) $uid;
                        $status = in_array($status, ['present', 'absent'], true) ? $status : 'absent';
                        $stmt->bind_param('iis', $eventId, $uid, $status);
                        $stmt->execute();
                    }
                    $stmt->close();
                    header('Location: administration-attendance-reports.php?notice=' . urlencode('Attendance marked successfully.') . '#attendance');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('Admin action error: ' . $e->getMessage());
            $formError = 'An error occurred while processing your request.';
        }
    }
}

/* ---------------------------------------------------------
   Fetch Data for Display
--------------------------------------------------------- */
$allUsers = [];
$allEvents = [];
$attendanceEventId = 0;
$registeredParticipants = [];
$existingAttendance = [];
$reportStats = ['avg_rating' => 0, 'total_feedback' => 0, 'avg_attendance' => 0];
$recentFeedback = [];
$feedbackEventId = 0;
$eventFeedback = [];
$eventAvgRating = 0;
$eventFeedbackCount = 0;
$selectedEventTitle = '';

if ($conn !== null) {
    // A. Fetch All Users
    $userStmt = $conn->prepare("SELECT user_id, full_name, email, role, status FROM users ORDER BY user_id DESC");
    $userStmt->execute();
    $allUsers = $userStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $userStmt->close();

    // B. Fetch All Events (with optional search)
    $searchTerm = trim((string) ($_GET['event_search'] ?? ''));
    $eventSql = "SELECT e.event_id, e.title, e.start_datetime, e.status, u.full_name AS organizer_name 
                 FROM events e INNER JOIN users u ON e.organizer_id = u.user_id";
    if ($searchTerm !== '') {
        $eventSql .= " WHERE e.title LIKE ?";
    }
    $eventSql .= " ORDER BY e.start_datetime DESC";

    $evStmt = $conn->prepare($eventSql);
    if ($searchTerm !== '') {
        $likeTerm = '%' . $searchTerm . '%';
        $evStmt->bind_param('s', $likeTerm);
    }
    $evStmt->execute();
    $allEvents = $evStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $evStmt->close();

    // C. Fetch Attendance Data for Selected Event
    $attendanceEventId = filter_var($_GET['attendance_event_id'] ?? 0, FILTER_VALIDATE_INT);
    $attendanceEventId = ($attendanceEventId !== false && $attendanceEventId > 0) ? $attendanceEventId : 0;

    if ($attendanceEventId > 0) {
        // Get registered participants
        $partStmt = $conn->prepare(
            "SELECT u.user_id, u.full_name, u.email FROM event_registrations er 
             INNER JOIN users u ON er.user_id = u.user_id WHERE er.event_id = ? AND er.status = 'registered'"
        );
        $partStmt->bind_param('i', $attendanceEventId);
        $partStmt->execute();
        $registeredParticipants = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $partStmt->close();

        // Get existing attendance records
        $attStmt = $conn->prepare("SELECT user_id, status FROM attendance WHERE event_id = ?");
        $attStmt->bind_param('i', $attendanceEventId);
        $attStmt->execute();
        $result = $attStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existingAttendance[(int) $row['user_id']] = $row['status'];
        }
        $attStmt->close();
    }

    // D. Fetch Report Statistics
    $statsSql = "SELECT 
                    (SELECT AVG(rating) FROM feedback) AS avg_rating,
                    (SELECT COUNT(*) FROM feedback) AS total_feedback,
                    (SELECT COUNT(*) FROM attendance WHERE status = 'present') AS total_present,
                    (SELECT COUNT(*) FROM attendance) AS total_attendance_records";
    $statsRes = $conn->query($statsSql);
    if ($statsRes) {
        $row = $statsRes->fetch_assoc();
        $reportStats['avg_rating'] = $row['avg_rating'] ? round((float) $row['avg_rating'], 1) : 0;
        $reportStats['total_feedback'] = (int) $row['total_feedback'];
        $totalPresent = (int) $row['total_present'];
        $totalRecords = (int) $row['total_attendance_records'];
        $reportStats['avg_attendance'] = $totalRecords > 0 ? round(($totalPresent / $totalRecords) * 100) : 0;
    }

    // Fetch Recent Feedback (all events)
    $fbStmt = $conn->prepare(
        "SELECT f.rating, f.comment, f.submitted_at, u.full_name, e.title AS event_title 
         FROM feedback f INNER JOIN users u ON f.user_id = u.user_id INNER JOIN events e ON f.event_id = e.event_id 
         ORDER BY f.submitted_at DESC LIMIT 10"
    );
    $fbStmt->execute();
    $recentFeedback = $fbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $fbStmt->close();

    // E. Per-Event Feedback (for Reports & Feedback dropdown)
    $feedbackEventId = filter_var($_GET['feedback_event_id'] ?? 0, FILTER_VALIDATE_INT);
    $feedbackEventId = ($feedbackEventId !== false && $feedbackEventId > 0) ? $feedbackEventId : 0;

    if ($feedbackEventId > 0) {
        // Get event title
        $evTitleStmt = $conn->prepare("SELECT title FROM events WHERE event_id = ?");
        $evTitleStmt->bind_param('i', $feedbackEventId);
        $evTitleStmt->execute();
        $evTitleRes = $evTitleStmt->get_result()->fetch_assoc();
        $selectedEventTitle = $evTitleRes ? $evTitleRes['title'] : '';
        $evTitleStmt->close();

        // Get feedback for the selected event
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

        // Calculate average rating for this event
        if (!empty($eventFeedback)) {
            $eventFeedbackCount = count($eventFeedback);
            $ratingSum = array_sum(array_column($eventFeedback, 'rating'));
            $eventAvgRating = round($ratingSum / $eventFeedbackCount, 1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration & Reports | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/admin.css">
    <style>
        /* Sub-navigation for sections */
        .sub-nav {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .sub-nav a {
            padding: 0.6rem 1.2rem;
            background: #fff;
            border: 1px solid #fecaca;
            border-radius: 6px;
            color: #991b1b;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
            transition: all 0.2s;
        }

        .sub-nav a:hover {
            background: #fef2f2;
        }

        .attendance-radio {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .attendance-radio label {
            cursor: pointer;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .attendance-radio input {
            margin-right: 0.3rem;
        }
    </style>
</head>

<body>

    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo">CampusEvent Admin</a>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= h($adminName) ?></span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">
        <aside class="sidebar">
            <nav>
                <h2 class="sidebar-title">Administration</h2>
                <ul class="sidebar-menu">
                    <li><a href="admin-dashboard.php" class="nav-link"><span>&#128202;</span><span>Dashboard</span></a>
                    </li>
                    <li><a href="administration-attendance-reports.php" class="nav-link active"
                            aria-current="page"><span>&#128101;</span><span>System Management</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Administrator</p>
                    <h1>System Management & Reports</h1>
                    <p class="page-description">Manage users, monitor events, record attendance, and view system
                        feedback.</p>
                </div>
            </section>

            <?php if ($notice !== ''): ?>
                <p role="status" class="form-message" style="background-color:#f0fdf4;border-color:#86efac;color:#166534;">
                    <?= h($notice) ?>
                </p><?php endif; ?>
            <?php if ($formError !== ''): ?>
                <p role="alert" class="form-message"><?= h($formError) ?></p><?php endif; ?>

            <!-- Sub Navigation -->
            <nav class="sub-nav" aria-label="Section navigation">
                <a href="#users">User Management</a>
                <a href="#events">Event Monitoring</a>
                <a href="#attendance">Attendance</a>
                <a href="#reports">Reports & Feedback</a>
            </nav>

            <!-- SECTION A: User Management -->
            <section id="users" class="dashboard-section">
                <div class="section-header">
                    <div>
                        <h2>User Management</h2>
                        <p>View and manage system user accounts.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allUsers)):
                                foreach ($allUsers as $user): ?>
                                    <tr>
                                        <td><strong><?= h($user['full_name']) ?></strong></td>
                                        <td><?= h($user['email']) ?></td>
                                        <td><span class="status status-pending"><?= h(ucfirst($user['role'])) ?></span></td>
                                        <td><span
                                                class="status <?= $user['status'] === 'active' ? 'status-open' : 'status-rejected' ?>"><?= h(ucfirst($user['status'])) ?></span>
                                        </td>
                                        <td>
                                            <?php if ((int) $user['user_id'] !== (int) $_SESSION['user_id']): ?>
                                                <form method="post" style="margin:0;display:inline">
                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= h($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="admin_action" value="toggle_user_status">
                                                    <input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>">
                                                    <button type="submit" class="btn btn-secondary"
                                                        style="padding:0.4rem 0.8rem;font-size:0.85rem;min-height:auto;">
                                                        <?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                            <?php else: ?><span style="color:#9ca3af;font-size:0.85rem;">Current
                                                    User</span><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:1.5rem;">No users found.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION B: Event Monitoring -->
            <section id="events" class="dashboard-section">
                <div class="section-header">
                    <div>
                        <h2>Event Monitoring</h2>
                        <p>Overview of all campus events.</p>
                    </div>
                </div>
                <form method="get" action="administration-attendance-reports.php#events"
                    style="display:flex;gap:1rem;margin-bottom:1rem;">
                    <input type="search" name="event_search" placeholder="Search events by title..."
                        value="<?= h($searchTerm) ?>"
                        style="flex:1;padding:0.6rem;border:1px solid #fecaca;border-radius:6px;">
                    <button type="submit" class="btn btn-primary" style="padding:0.6rem 1.2rem;">Search</button>
                    <?php if ($searchTerm !== ''): ?><a href="administration-attendance-reports.php#events"
                            class="btn btn-secondary" style="padding:0.6rem 1.2rem;">Clear</a><?php endif; ?>
                </form>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Organizer</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($allEvents)):
                                foreach ($allEvents as $event):
                                    $statusClass = match ($event['status']) { 'published' => 'status-open', 'cancelled' => 'status-rejected', default => 'status-pending'};
                                    ?>
                                    <tr>
                                        <td><strong><?= h($event['title']) ?></strong></td>
                                        <td><?= h($event['organizer_name']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($event['start_datetime']))) ?></td>
                                        <td><span class="status <?= $statusClass ?>"><?= h(ucfirst($event['status'])) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:1.5rem;">No events match your search.
                                    </td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- SECTION C: Attendance Management -->
            <section id="attendance" class="dashboard-section">
                <div class="section-header">
                    <div>
                        <h2>Attendance Management</h2>
                        <p>Mark participant attendance for specific events.</p>
                    </div>
                </div>
                <form method="get" action="administration-attendance-reports.php#attendance"
                    style="display:flex;gap:1rem;margin-bottom:1.5rem;align-items:flex-end;">
                    <div style="flex:1;">
                        <label for="att_event"
                            style="font-weight:600;font-size:0.9rem;margin-bottom:0.3rem;display:block;">Select
                            Event</label>
                        <select id="att_event" name="attendance_event_id" onchange="this.form.submit()"
                            style="width:100%;padding:0.6rem;border:1px solid #fecaca;border-radius:6px;">
                            <option value="0">-- Choose an event --</option>
                            <?php foreach ($allEvents as $ev): ?>
                                <option value="<?= (int) $ev['event_id'] ?>" <?= $attendanceEventId === (int) $ev['event_id'] ? 'selected' : '' ?>><?= h($ev['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($attendanceEventId > 0): ?>
                    <?php if (!empty($registeredParticipants)): ?>
                        <form method="post" action="administration-attendance-reports.php#attendance">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="admin_action" value="mark_attendance">
                            <input type="hidden" name="event_id" value="<?= $attendanceEventId ?>">
                            <div class="table-wrapper">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Participant</th>
                                            <th>Email</th>
                                            <th style="text-align:center;">Attendance Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($registeredParticipants as $p):
                                            $currentStatus = $existingAttendance[(int) $p['user_id']] ?? 'absent';
                                            ?>
                                            <tr>
                                                <td><strong><?= h($p['full_name']) ?></strong></td>
                                                <td><?= h($p['email']) ?></td>
                                                <td style="text-align:center;">
                                                    <div class="attendance-radio" style="justify-content:center;">
                                                        <label><input type="radio" name="attendance[<?= (int) $p['user_id'] ?>]"
                                                                value="present" <?= $currentStatus === 'present' ? 'checked' : '' ?>>
                                                            Present</label>
                                                        <label><input type="radio" name="attendance[<?= (int) $p['user_id'] ?>]"
                                                                value="absent" <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                                            Absent</label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top:1.5rem;text-align:right;">
                                <button type="submit" class="btn btn-primary">Save Attendance</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p style="color:#6b7280;text-align:center;padding:2rem;">No registered participants found for this
                            event.</p><?php endif; ?>
                <?php else: ?>
                    <p style="color:#6b7280;text-align:center;padding:2rem;">Please select an event above to manage
                        attendance.</p><?php endif; ?>
            </section>

            <!-- SECTION D: Reports & Feedback -->
            <section id="reports" class="dashboard-section">
                <div class="section-header">
                    <div>
                        <h2>Reports & Feedback</h2>
                        <p>System-wide participation and feedback metrics.</p>
                    </div>
                </div>

                <!-- Global stats -->
                <div class="dashboard-stats" style="margin-bottom:2rem;">
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
                    <article class="stat-card">
                        <div class="stat-card-header">
                            <h3>Avg Attendance</h3>
                        </div>
                        <p class="stat-value"><?= $reportStats['avg_attendance'] ?>%</p>
                        <p class="stat-description">Present rate</p>
                    </article>
                </div>

                <!-- Per-event feedback filter -->
                <h3 style="color:#991b1b;margin-bottom:1rem;font-size:1.1rem;">Feedback by Event</h3>
                <form method="get" action="administration-attendance-reports.php#reports"
                    style="display:flex;gap:1rem;margin-bottom:1.5rem;align-items:flex-end;flex-wrap:wrap;">
                    <!-- Preserve other GET params so other sections are not disrupted -->
                    <?php if ($searchTerm !== ''): ?>
                        <input type="hidden" name="event_search" value="<?= h($searchTerm) ?>">
                    <?php endif; ?>
                    <?php if ($attendanceEventId > 0): ?>
                        <input type="hidden" name="attendance_event_id" value="<?= $attendanceEventId ?>">
                    <?php endif; ?>
                    <div style="flex:1;min-width:200px;">
                        <label for="feedback_event"
                            style="font-weight:600;font-size:0.9rem;margin-bottom:0.3rem;display:block;">Select
                            Event</label>
                        <select id="feedback_event" name="feedback_event_id" onchange="this.form.submit()"
                            style="width:100%;padding:0.6rem;border:1px solid #fecaca;border-radius:6px;">
                            <option value="0">-- All Events (Recent 10) --</option>
                            <?php foreach ($allEvents as $ev): ?>
                                <option value="<?= (int) $ev['event_id'] ?>" <?= $feedbackEventId === (int) $ev['event_id'] ? 'selected' : '' ?>><?= h($ev['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($feedbackEventId > 0): ?>
                        <a href="administration-attendance-reports.php#reports" class="btn btn-secondary"
                            style="padding:0.6rem 1.2rem;">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if ($feedbackEventId > 0 && $selectedEventTitle !== ''): ?>
                    <!-- Per-event average score card -->
                    <div style="background:linear-gradient(135deg,#fef2f2,#fff);border:1px solid #fecaca;border-radius:10px;padding:1.2rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
                        <div>
                            <p style="font-size:0.85rem;color:#6b7280;margin:0 0 0.2rem;">Average Score for</p>
                            <p style="font-weight:700;font-size:1rem;color:#1f2937;margin:0;"><?= h($selectedEventTitle) ?></p>
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
                            <p style="font-size:1.8rem;font-weight:800;color:#991b1b;margin:0.2rem 0 0;">
                                <?= $eventAvgRating ?> <span style="font-size:1rem;font-weight:500;color:#6b7280;">/ 5</span>
                            </p>
                            <p style="font-size:0.8rem;color:#6b7280;margin:0;"><?= $eventFeedbackCount ?> response<?= $eventFeedbackCount !== 1 ? 's' : '' ?></p>
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
                    <h3 style="color:#991b1b;margin-bottom:1rem;font-size:1.1rem;">Recent Feedback Submissions</h3>
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
                                        <td colspan="5" style="text-align:center;padding:1.5rem;">No feedback submitted yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>

    <script src="../public/js/main.js"></script>
</body>

</html>