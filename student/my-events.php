
<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Only logged-in students can access this page.
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = '';
$messageType = 'success';

// Handle cancellation and feedback submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = filter_var(
        $_POST['event_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$eventId || $eventId < 1) {
        $message = 'Invalid event.';
        $messageType = 'error';
    } elseif ($action === 'cancel') {
        try {
            // Cancel only this student's active registration.
            $stmt = $conn->prepare(
                "UPDATE event_registrations
                 SET status = 'cancelled'
                 WHERE event_id = ?
                   AND user_id = ?
                   AND status = 'registered'"
            );
            $stmt->bind_param('ii', $eventId, $userId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = 'Registration cancelled successfully.';
            } else {
                $message = 'No active registration was found.';
                $messageType = 'error';
            }

            $stmt->close();
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $message = 'Unable to cancel registration. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'feedback') {
        $rating = filter_var(
            $_POST['rating'] ?? null,
            FILTER_VALIDATE_INT
        );
        $comments = trim($_POST['feedback'] ?? '');

        if (
            $rating === false || $rating === null ||
            $rating < 1 || $rating > 5 ||
            $comments === '' || mb_strlen($comments) > 2000
        ) {
            $message = 'Choose a rating and enter comments (up to 2000 characters).';
            $messageType = 'error';
        } else {
            try {
                // Feedback is accepted only for a registered event
                // that has already ended.
                $check = $conn->prepare(
                    "SELECT er.registration_id
                     FROM event_registrations er
                     JOIN events e ON e.event_id = er.event_id
                     WHERE er.event_id = ?
                       AND er.user_id = ?
                       AND er.status = 'registered'
                       AND e.end_datetime < NOW()
                     LIMIT 1"
                );
                $check->bind_param('ii', $eventId, $userId);
                $check->execute();
                $eligible = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$eligible) {
                    $message = 'Feedback is available after the event ends, for active registrations.';
                    $messageType = 'error';
                } else {
                    /*
                     * Assumes feedback columns are:
                     * event_id, user_id, rating, comments.
                     * Verify these names against your database.
                     */
                    $stmt = $conn->prepare(
                        "INSERT INTO feedback
                            (event_id, user_id, rating, comments)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param(
                        'iiis',
                        $eventId,
                        $userId,
                        $rating,
                        $comments
                    );
                    $stmt->execute();
                    $stmt->close();

                    $message = 'Feedback submitted successfully.';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $message = 'Unable to save feedback. Check the feedback table columns and try again.';
                $messageType = 'error';
            }
        }
    }
}

// Fetch this student's registrations from the database.
$stmt = $conn->prepare(
    "SELECT
        e.event_id,
        e.title,
        e.description,
        e.start_datetime,
        e.end_datetime,
        e.venue,
        er.status AS registration_status,
        a.status AS attendance_status
     FROM event_registrations er
     JOIN events e ON e.event_id = er.event_id
     LEFT JOIN attendance a
       ON a.event_id = er.event_id
      AND a.user_id = er.user_id
     WHERE er.user_id = ?
     ORDER BY e.start_datetime DESC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Events</title>
<style>
* { box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    margin: 0;
    color: #26324a;
}
header {
    background: white;
    padding: 20px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
header a, .link {
    color: #3157c8;
    text-decoration: none;
}
main {
    max-width: 950px;
    margin: auto;
    padding: 25px;
}
.card {
    background: white;
    padding: 20px;
    border: 1px solid #e1e5ef;
    border-radius: 12px;
    margin: 18px 0;
}
button {
    padding: 10px 14px;
    border: 0;
    border-radius: 7px;
    background: #3157c8;
    color: white;
    cursor: pointer;
}
.cancel { background: #b52b38; }
input, select, textarea {
    display: block;
    width: 100%;
    max-width: 500px;
    padding: 10px;
    margin: 8px 0 14px;
    border: 1px solid #ccd3e0;
    border-radius: 7px;
    font: inherit;
}
.message {
    padding: 12px;
    border-radius: 8px;
    background: #e4f7e9;
}
.error { background: #fde8e8; }
.muted { color: #657087; }
.status { font-weight: bold; }
</style>
</head>
<body>

<header>
    <strong>My Events &amp; Participation</strong>
    <nav>
        <a href="index.php">Discover Events</a>
    </nav>
</header>

<main>
<h1>My Events</h1>
<p class="muted">View your registrations and participation details.</p>

<?php if ($message !== ''): ?>
    <p class="message <?= $messageType === 'error' ? 'error' : '' ?>">
        <?= h($message) ?>
    </p>
<?php endif; ?>

<?php if (!$events): ?>
    <div class="card">
        <h3>No registrations yet</h3>
        <p>Browse events and register for one to see it here.</p>
        <a class="link" href="index.php">Discover Events</a>
    </div>
<?php endif; ?>

<?php foreach ($events as $event): ?>
    <div class="card">
        <h2><?= h($event['title']) ?></h2>

        <p>
            <strong>Date:</strong>
            <?= h(date('d M Y', strtotime($event['start_datetime']))) ?>
        </p>
        <p>
            <strong>Time:</strong>
            <?= h(date('h:i A', strtotime($event['start_datetime']))) ?>
            –
            <?= h(date('h:i A', strtotime($event['end_datetime']))) ?>
        </p>
        <p><strong>Venue:</strong> <?= h($event['venue']) ?></p>

        <p>
            <strong>Registration status:</strong>
            <span class="status">
                <?= h(ucfirst($event['registration_status'])) ?>
            </span>
        </p>

        <p>
            <strong>Attendance:</strong>
            <?= $event['attendance_status'] !== null
                ? h(ucfirst($event['attendance_status']))
                : 'Not yet recorded' ?>
        </p>

        <a class="link"
           href="event-details.php?id=<?= (int)$event['event_id'] ?>">
            View Event Details
        </a>

        <?php if ($event['registration_status'] === 'registered'): ?>
            <form method="post"
                  onsubmit="return confirm('Are you sure you want to cancel this registration?');">
                <input type="hidden" name="event_id"
                       value="<?= (int)$event['event_id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="cancel" type="submit">
                    Cancel Registration
                </button>
            </form>
        <?php endif; ?>

        <?php if (
            $event['registration_status'] === 'registered' &&
            strtotime($event['end_datetime']) < time()
        ): ?>
            <h3>Event Feedback</h3>
            <form method="post">
                <input type="hidden" name="event_id"
                       value="<?= (int)$event['event_id'] ?>">
                <input type="hidden" name="action" value="feedback">

                <label for="rating-<?= (int)$event['event_id'] ?>">
                    Rating
                </label>
                <select id="rating-<?= (int)$event['event_id'] ?>"
                        name="rating" required>
                    <option value="">Select rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Very good</option>
                    <option value="3">3 - Good</option>
                    <option value="2">2 - Fair</option>
                    <option value="1">1 - Poor</option>
                </select>

                <label for="feedback-<?= (int)$event['event_id'] ?>">
                    Comments
                </label>
                <textarea id="feedback-<?= (int)$event['event_id'] ?>"
                          name="feedback" rows="3" maxlength="2000"
                          placeholder="Share your feedback..."
                          required></textarea>

                <button type="submit">Submit Feedback</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

</main>
</body>
</html>
