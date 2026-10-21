
<?php
session_start();

require_once __DIR__ . '/../config/database.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = null;

if ($id && $id > 0) {
    $sql = "
        SELECT
            e.event_id,
            e.title,
            e.description,
            e.start_datetime,
            e.end_datetime,
            e.venue,
            e.capacity,
            e.registration_deadline,
            e.status,
            c.category_name
        FROM events e
        INNER JOIN categories c
            ON e.category_id = c.category_id
        WHERE e.event_id = ?
          AND e.status = 'published'
          AND c.status = 'active'
          AND e.start_datetime >= NOW()
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $result = $stmt->get_result();
    $event = $result->fetch_assoc();

    $stmt->close();
}

// Allow registration only while the deadline has not passed.
$registrationOpen = false;

if ($event) {
    $registrationOpen =
        strtotime($event['registration_deadline']) >= time();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Event Details</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    margin: 0;
    color: #26324a;
}

header, main {
    padding: 22px 7%;
}

header {
    background: white;
    display: flex;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

a {
    color: #3157c8;
    text-decoration: none;
}

main {
    max-width: 800px;
    margin: 30px auto;
    background: white;
    border-radius: 12px;
    padding: 28px;
}

.btn {
    display: inline-block;
    background: #3157c8;
    color: white;
    padding: 12px 18px;
    border-radius: 7px;
}

.info {
    line-height: 2;
    color: #566279;
}

.notice {
    padding: 12px;
    background: #fff0d9;
    border-radius: 7px;
    color: #805313;
}

.tag {
    color: #3157c8;
    font-weight: bold;
}
</style>
</head>

<body>

<header>
    <a href="index.php">← Back to Events</a>
    <a href="my-events.php">My Events</a>
</header>

<main>

<?php if (!$event): ?>

    <h2>Event not found</h2>
    <p>
        This event may not exist, may not be published,
        or may no longer be upcoming.
    </p>
    <a class="btn" href="index.php">Browse Events</a>

<?php else: ?>

    <p class="tag"><?= h($event['category_name']) ?></p>

    <h1><?= h($event['title']) ?></h1>

    <p><?= nl2br(h($event['description'])) ?></p>

    <div class="info">
        <strong>Date:</strong>
        <?= date('d M Y', strtotime($event['start_datetime'])) ?>
        <br>

        <strong>Start time:</strong>
        <?= date('h:i A', strtotime($event['start_datetime'])) ?>
        <br>

        <strong>End time:</strong>
        <?= date('h:i A', strtotime($event['end_datetime'])) ?>
        <br>

        <strong>Venue:</strong>
        <?= h($event['venue']) ?>
        <br>

        <strong>Maximum capacity:</strong>
        <?= (int)$event['capacity'] ?> students
        <br>

        <strong>Registration deadline:</strong>
        <?= date(
            'd M Y, h:i A',
            strtotime($event['registration_deadline'])
        ) ?>
    </div>

    <?php if ($registrationOpen): ?>

        <p>
            <a class="btn"
               href="register-event.php?id=<?= (int)$event['event_id'] ?>">
                Register for Event
            </a>
        </p>

    <?php else: ?>

        <p class="notice">
            The registration deadline has passed.
        </p>

    <?php endif; ?>

<?php endif; ?>

</main>

</body>
</html>

<?php
$conn->close();
?>
