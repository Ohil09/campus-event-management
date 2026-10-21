
<?php


session_start();
echo 'PHP time: ' . date('Y-m-d H:i:s');
echo '<br>PHP timezone: ' . date_default_timezone_get();

require_once __DIR__ . '/../config/database.php';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'info';
$event = null;
$id = filter_var(
    $_GET['id'] ?? $_POST['id'] ?? 0,
    FILTER_VALIDATE_INT
);

$id = ($id !== false && $id > 0) ? $id : 0;

// Only logged-in students can register.
if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($id < 1) {
    $message = 'Invalid event. Please open an event from Discover Events.';
    $messageType = 'error';
} else {
    // Load the event from the database.
    $stmt = $conn->prepare(
        "SELECT e.event_id, e.title, e.start_datetime, e.end_datetime,
                e.venue, e.capacity, e.registration_deadline, e.status
         FROM events e
         INNER JOIN categories c ON c.category_id = e.category_id
         WHERE e.event_id = ?
           AND e.status = 'published'
           AND c.status = 'active'"
    );

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if (!$event) {
        $message = 'Event not found or it is no longer available for registration.';
        $messageType = 'error';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Lock the event row while checking capacity and registering.
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                "SELECT event_id, title, start_datetime, end_datetime,
                        venue, capacity, registration_deadline, status
                 FROM events
                 WHERE event_id = ?
                   AND status = 'published'
                 FOR UPDATE"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $event = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();

            if (!$event) {
                throw new RuntimeException(
                    'This event is no longer available.'
                );
            }

            if (strtotime($event['registration_deadline']) < time()) {
                throw new RuntimeException(
                    'The registration deadline has passed.'
                );
            }

            if (strtotime($event['start_datetime']) <= time()) {
                throw new RuntimeException(
                    'Registration is not available because this event has already started.'
                );
            }

            // Check whether the student is already registered.
            $stmt = $conn->prepare(
                "SELECT registration_id
                 FROM event_registrations
                 WHERE event_id = ?
                   AND user_id = ?
                   AND status = 'registered'
                 LIMIT 1"
            );

            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $alreadyRegistered = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($alreadyRegistered) {
                throw new RuntimeException(
                    'You are already registered for this event.'
                );
            }

            // Check capacity using active registrations.
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM event_registrations
                 WHERE event_id = ?
                   AND status = 'registered'"
            );

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $registrationCount = (int) $stmt->get_result()
                ->fetch_assoc()['total'];
            $stmt->close();

            if ($registrationCount >= (int) $event['capacity']) {
                throw new RuntimeException(
                    'Sorry, this event has reached its registration capacity.'
                );
            }

            // Detect overlap with another event the student registered for.
            $stmt = $conn->prepare(
                "SELECT e.title
                 FROM event_registrations er
                 INNER JOIN events e ON e.event_id = er.event_id
                 WHERE er.user_id = ?
                   AND er.status = 'registered'
                   AND e.event_id <> ?
                   AND e.status <> 'cancelled'
                   AND e.start_datetime < ?
                   AND e.end_datetime > ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                'iiss',
                $userId,
                $id,
                $event['end_datetime'],
                $event['start_datetime']
            );

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
                "SELECT registration_id
                 FROM event_registrations
                 WHERE event_id = ?
                   AND user_id = ?
                   AND status = 'cancelled'
                 ORDER BY registration_id DESC
                 LIMIT 1"
            );

            $stmt->bind_param('ii', $id, $userId);
            $stmt->execute();
            $cancelled = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cancelled) {
                $registrationId = (int) $cancelled['registration_id'];

                $stmt = $conn->prepare(
                    "UPDATE event_registrations
                     SET status = 'registered', registered_at = NOW()
                     WHERE registration_id = ?"
                );

                $stmt->bind_param('i', $registrationId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO event_registrations
                        (event_id, user_id, status, registered_at)
                     VALUES (?, ?, 'registered', NOW())"
                );

                $stmt->bind_param('ii', $id, $userId);
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();

            $message = 'Registration successful! Your event registration has been saved.';
            $messageType = 'success';
        } catch (Throwable $e) {
            $conn->rollback();

            if ($e instanceof RuntimeException) {
                $message = $e->getMessage();
                $messageType = 'warning';
            } else {
                error_log('Event registration error: ' . $e->getMessage());
                $message = 'Something went wrong while registering. Please try again.';
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
    $message = 'Registration is closed for this event.';
    $messageType = 'warning';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Event Registration</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    margin: 0;
    color: #26324a;
}
main {
    max-width: 650px;
    margin: 45px auto;
    padding: 25px;
    background: white;
    border-radius: 12px;
}
.notice {
    padding: 14px;
    border-radius: 8px;
    margin: 18px 0;
    line-height: 1.5;
}
.info { background: #edf1ff; }
.success { background: #e4f7e9; color: #176b31; }
.warning { background: #fff3d6; color: #805600; }
.error { background: #ffe6e6; color: #9b2020; }
button, .btn {
    display: inline-block;
    padding: 12px 16px;
    background: #3157c8;
    color: white;
    border: 0;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
}
button:disabled {
    background: #999;
    cursor: not-allowed;
}
a { color: #3157c8; }
</style>
</head>
<body>
<main>
<a href="index.php">← Discover Events</a>

<?php if ($event): ?>
    <h1>Event Registration</h1>
    <h2><?= h($event['title']) ?></h2>

    <p>
        Date:
        <?= date('d M Y', strtotime($event['start_datetime'])) ?>
    </p>

    <p>
        Start:
        <?= date('h:i A', strtotime($event['start_datetime'])) ?>
    </p>

    <p>
        End:
        <?= date('h:i A', strtotime($event['end_datetime'])) ?>
    </p>

    <p>Venue: <?= h($event['venue']) ?></p>

    <p>
        Registration deadline:
        <?= date('d M Y, h:i A', strtotime($event['registration_deadline'])) ?>
    </p>

    <?php if ($message !== ''): ?>
        <div class="notice <?= h($messageType) ?>" role="status">
            <?= h($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($messageType !== 'success' && $canRegister): ?>
        <form method="post" action="register-event.php?id=<?= (int) $id ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <button type="submit">Confirm Registration</button>
        </form>
    <?php endif; ?>

    <p><a href="my-events.php">View My Events</a></p>

<?php else: ?>
    <h1>Unable to register</h1>
    <div class="notice error"><?= h($message) ?></div>
<?php endif; ?>
</main>
</body>
</html>
