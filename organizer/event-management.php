<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('organizer');
require_once __DIR__ . '/../config/database.php';

$organizerId = (int) ($_SESSION['user_id'] ?? 0);
$organizerName = (string) ($_SESSION['full_name'] ?? 'Organizer');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fetch categories
$categoryStmt = $conn->prepare('SELECT category_id, category_name FROM categories ORDER BY category_name');
$categoryStmt->execute();
$categories = $categoryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$categoryStmt->close();

$formData = [
    'event_title' => '', 'event_category' => '', 'event_description' => '',
    'event_date' => '', 'start_time' => '', 'end_time' => '',
    'venue' => '', 'capacity' => '', 'registration_deadline' => '', 'event_status' => 'draft',
];

$formError = '';
$notice = '';
$editingEventId = filter_var($_GET['event_id'] ?? 0, FILTER_VALIDATE_INT);
$editingEventId = ($editingEventId !== false && $editingEventId > 0) ? $editingEventId : 0;

$saved = (string) ($_GET['saved'] ?? '');
if ($saved === 'created') { $notice = 'Event created successfully.'; }
elseif ($saved === 'updated') { $notice = 'Event updated successfully.'; }

// Create or update an event. Ownership is checked on every update.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedAction = isset($_POST['event_action']) && is_string($_POST['event_action']) ? $_POST['event_action'] : '';

    $postString = static function (string $key): string {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    };

    $formData = [
        'event_title' => $postString('event_title'), 'event_category' => $postString('event_category'),
        'event_description' => $postString('event_description'), 'event_date' => $postString('event_date'),
        'start_time' => $postString('start_time'), 'end_time' => $postString('end_time'),
        'venue' => $postString('venue'), 'capacity' => $postString('capacity'),
        'registration_deadline' => $postString('registration_deadline'), 'event_status' => $postString('event_status'),
    ];

    $postedEventId = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT);
    $postedEventId = ($postedEventId !== false && $postedEventId > 0) ? $postedEventId : 0;
    $editingEventId = ($postedAction === 'update') ? $postedEventId : 0;

    $submittedToken = $postString('csrf_token');
    if (!hash_equals((string) $_SESSION['csrf_token'], $submittedToken)) {
        $formError = 'The form session expired. Please refresh the page and try again.';
    } elseif (!in_array($postedAction, ['create', 'update'], true)) {
        $formError = 'Invalid form action.';
    } elseif ($formData['event_title'] === '' || mb_strlen($formData['event_title']) > 100) {
        $formError = 'Enter an event title containing no more than 100 characters.';
    } elseif ($formData['event_description'] === '') {
        $formError = 'Enter an event description.';
    } elseif ($formData['venue'] === '' || mb_strlen($formData['venue']) > 100) {
        $formError = 'Enter a venue containing no more than 100 characters.';
    } elseif (!in_array($formData['event_status'], ['draft', 'published', 'closed', 'cancelled'], true)) {
        $formError = 'Select a valid event status.';
    } else {
        $categoryId = filter_var($formData['event_category'], FILTER_VALIDATE_INT);
        $capacity = filter_var($formData['capacity'], FILTER_VALIDATE_INT);
        $validCategory = false;

        if ($categoryId !== false && $categoryId > 0) {
            foreach ($categories as $category) {
                if ((int) $category['category_id'] === $categoryId) { $validCategory = true; break; }
            }
        }

        if (!$validCategory) {
            $formError = 'Select a valid event category.';
        } elseif ($capacity === false || $capacity < 1 || $capacity > 1000) {
            $formError = 'Participant capacity must be between 1 and 1000.';
        } else {
            $startInput = $formData['event_date'] . ' ' . $formData['start_time'];
            $endInput = $formData['event_date'] . ' ' . $formData['end_time'];
            $startDateTime = DateTime::createFromFormat('!Y-m-d H:i', $startInput);
            $endDateTime = DateTime::createFromFormat('!Y-m-d H:i', $endInput);
            $deadlineInput = $formData['registration_deadline'];
            $deadlineDateTime = DateTime::createFromFormat('!Y-m-d\TH:i', $deadlineInput);

            $validStart = $startDateTime !== false && $startDateTime->format('Y-m-d H:i') === $startInput;
            $validEnd = $endDateTime !== false && $endDateTime->format('Y-m-d H:i') === $endInput;
            $validDeadline = $deadlineDateTime !== false && $deadlineDateTime->format('Y-m-d\TH:i') === $deadlineInput;

            if (!$validStart || !$validEnd || !$validDeadline) {
                $formError = 'Enter a valid event date, time, and registration deadline.';
            } elseif ($endDateTime <= $startDateTime) {
                $formError = 'The event end time must be later than its start time.';
            } elseif ($deadlineDateTime > $startDateTime) {
                $formError = 'The registration deadline must be on or before the event start.';
            } elseif ($postedAction === 'update' && $postedEventId === 0) {
                $formError = 'The event to update could not be identified.';
            } else {
                $startSql = $startDateTime->format('Y-m-d H:i:s');
                $endSql = $endDateTime->format('Y-m-d H:i:s');
                $deadlineSql = $deadlineDateTime->format('Y-m-d H:i:s');

                try {
                    if ($postedAction === 'create') {
                        $stmt = $conn->prepare(
                            'INSERT INTO events (organizer_id, category_id, title, description, start_datetime,
                             end_datetime, venue, capacity, registration_deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $stmt->bind_param('iisssssiss', $organizerId, $categoryId, $formData['event_title'],
                            $formData['event_description'], $startSql, $endSql, $formData['venue'],
                            $capacity, $deadlineSql, $formData['event_status']);
                        $stmt->execute();
                        $stmt->close();
                        header('Location: event-management.php?saved=created');
                        exit;
                    }

                    // Check ownership before attempting an update.
                    $ownerCheck = $conn->prepare('SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?');
                    $ownerCheck->bind_param('ii', $postedEventId, $organizerId);
                    $ownerCheck->execute();
                    $ownerCheck->store_result();
                    $ownsEvent = $ownerCheck->num_rows === 1;
                    $ownerCheck->close();

                    if (!$ownsEvent) {
                        $formError = 'The selected event was not found or you do not have permission to edit it.';
                    } else {
                        $stmt = $conn->prepare(
                            'UPDATE events SET category_id = ?, title = ?, description = ?, start_datetime = ?,
                             end_datetime = ?, venue = ?, capacity = ?, registration_deadline = ?, status = ?
                             WHERE event_id = ? AND organizer_id = ?'
                        );
                        $stmt->bind_param('isssssissii', $categoryId, $formData['event_title'],
                            $formData['event_description'], $startSql, $endSql, $formData['venue'],
                            $capacity, $deadlineSql, $formData['event_status'], $postedEventId, $organizerId);
                        $stmt->execute();
                        $stmt->close();
                        header('Location: event-management.php?saved=updated');
                        exit;
                    }
                } catch (Throwable $exception) {
                    error_log('Event save failed: ' . $exception->getMessage());
                    $formError = 'The event could not be saved. Check the entered details and try again.';
                }
            }
        }
    }
}

// Load an event for editing, but only if it belongs to this organizer.
$editingEvent = null;
if ($editingEventId > 0) {
    $editStmt = $conn->prepare(
        'SELECT event_id, category_id, title, description, start_datetime, end_datetime,
                venue, capacity, registration_deadline, status
         FROM events WHERE event_id = ? AND organizer_id = ?'
    );
    $editStmt->bind_param('ii', $editingEventId, $organizerId);
    $editStmt->execute();
    $editingEvent = $editStmt->get_result()->fetch_assoc() ?: null;
    $editStmt->close();

    if ($editingEvent === null) {
        if ($formError === '') { $formError = 'The selected event was not found or you do not have permission to edit it.'; }
        $editingEventId = 0;
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $formData = [
            'event_title' => $editingEvent['title'],
            'event_category' => (string) $editingEvent['category_id'],
            'event_description' => $editingEvent['description'],
            'event_date' => date('Y-m-d', strtotime($editingEvent['start_datetime'])),
            'start_time' => date('H:i', strtotime($editingEvent['start_datetime'])),
            'end_time' => date('H:i', strtotime($editingEvent['end_datetime'])),
            'venue' => $editingEvent['venue'],
            'capacity' => (string) $editingEvent['capacity'],
            'registration_deadline' => date('Y-m-d\TH:i', strtotime($editingEvent['registration_deadline'])),
            'event_status' => $editingEvent['status'],
        ];
    }
}

// Fetch this organizer's events and registered participant counts.
$eventsStmt = $conn->prepare(
    "SELECT e.event_id, e.title, e.start_datetime, e.venue, e.capacity, e.status,
            COUNT(er.registration_id) AS registration_count
     FROM events e
     LEFT JOIN event_registrations er ON er.event_id = e.event_id AND er.status = 'registered'
     WHERE e.organizer_id = ?
     GROUP BY e.event_id, e.title, e.start_datetime, e.venue, e.capacity, e.status
     ORDER BY e.start_datetime DESC"
);
$eventsStmt->bind_param('i', $organizerId);
$eventsStmt->execute();
$events = $eventsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$eventsStmt->close();

// Participants for a selected event
$participantEventId = filter_var($_GET['participant_event_id'] ?? 0, FILTER_VALIDATE_INT);
$participantEventId = ($participantEventId !== false && $participantEventId > 0) ? $participantEventId : 0;
$selectedParticipantEvent = null;
$participants = [];

if ($participantEventId > 0) {
    foreach ($events as $event) {
        if ((int) $event['event_id'] === $participantEventId) { $selectedParticipantEvent = $event; break; }
    }

    if ($selectedParticipantEvent !== null) {
        $participantsStmt = $conn->prepare(
            'SELECT u.full_name, u.email, er.registered_at, er.status
             FROM event_registrations er
             INNER JOIN users u ON u.user_id = er.user_id
             INNER JOIN events e ON e.event_id = er.event_id
             WHERE e.organizer_id = ? AND e.event_id = ?
             ORDER BY er.registered_at DESC'
        );
        $participantsStmt->bind_param('ii', $organizerId, $participantEventId);
        $participantsStmt->execute();
        $participants = $participantsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $participantsStmt->close();
    } else {
        $participantEventId = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage campus events and participants">
    <title>Event Management | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/organizer.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent</a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="organizer-navigation">☰</button>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= h($organizerName) ?></span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">
        <aside class="sidebar" id="organizer-navigation" aria-label="Organizer navigation">
            <nav>
                <h2 class="sidebar-title">Organizer</h2>
                <ul class="sidebar-menu">
                    <li><a href="organizer-dashboard.php" class="nav-link"><span aria-hidden="true">⌂</span><span>Dashboard</span></a></li>
                    <li><a href="event-management.php" class="nav-link active" aria-current="page"><span aria-hidden="true">▣</span><span>Manage Events</span></a></li>
                    <li><a href="event-management.php#participants" class="nav-link"><span aria-hidden="true">👥</span><span>Participants</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Organizer / Event Management</p>
                    <h1>Event &amp; Participant Management</h1>
                    <p class="page-description">Create, update and manage your campus events and monitor participant registrations.</p>
                </div>
                <a href="event-management.php#create-event" class="btn btn-primary">Create New Event</a>
            </section>

            <section id="create-event" class="dashboard-section" aria-labelledby="event-form-heading">
                <div class="section-header">
                    <div>
                        <h2 id="event-form-heading"><?= $editingEventId > 0 ? 'Edit Event' : 'Create New Event' ?></h2>
                        <p><?= $editingEventId > 0 ? 'Update the details of your event.' : 'Enter the details required to create a campus event.' ?></p>
                    </div>
                </div>

                <?php if ($notice !== ''): ?>
                    <p role="status" class="form-message"><?= h($notice) ?></p>
                <?php endif; ?>
                <?php if ($formError !== ''): ?>
                    <p role="alert" class="form-message"><?= h($formError) ?></p>
                <?php endif; ?>

                <form action="event-management.php<?= $editingEventId > 0 ? '?event_id=' . $editingEventId : '' ?>#create-event" method="post" class="event-form">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="event_action" value="<?= $editingEventId > 0 ? 'update' : 'create' ?>">
                    <input type="hidden" name="event_id" value="<?= (int) $editingEventId ?>">

                    <div class="form-group">
                        <label for="event-title">Event Title</label>
                        <input type="text" id="event-title" name="event_title" placeholder="Enter event title" required maxlength="100" value="<?= h($formData['event_title']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="event-category">Category</label>
                        <select id="event-category" name="event_category" required>
                            <option value="">Select category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>" <?= (string) $formData['event_category'] === (string) $category['category_id'] ? 'selected' : '' ?>><?= h($category['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-group-full">
                        <label for="event-description">Event Description</label>
                        <textarea id="event-description" name="event_description" rows="5" placeholder="Describe the event" required><?= h($formData['event_description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="event-date">Event Date</label>
                        <input type="date" id="event-date" name="event_date" required value="<?= h($formData['event_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="start-time">Start Time</label>
                        <input type="time" id="start-time" name="start_time" required value="<?= h($formData['start_time']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end-time">End Time</label>
                        <input type="time" id="end-time" name="end_time" required value="<?= h($formData['end_time']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" placeholder="Enter venue" required maxlength="100" value="<?= h($formData['venue']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="capacity">Participant Capacity</label>
                        <input type="number" id="capacity" name="capacity" min="1" max="1000" placeholder="e.g. 100" required value="<?= h($formData['capacity']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="registration-deadline">Registration Deadline</label>
                        <input type="datetime-local" id="registration-deadline" name="registration_deadline" required value="<?= h($formData['registration_deadline']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="event-status">Event Status</label>
                        <select id="event-status" name="event_status" required>
                            <?php foreach (['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $statusValue => $statusLabel): ?>
                                <option value="<?= h($statusValue) ?>" <?= $formData['event_status'] === $statusValue ? 'selected' : '' ?>><?= h($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                        <button type="submit" class="btn btn-primary"><?= $editingEventId > 0 ? 'Update Event' : 'Save Event' ?></button>
                        <?php if ($editingEventId > 0): ?>
                            <a href="event-management.php" class="btn btn-secondary">Cancel Editing</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <section class="dashboard-section" aria-labelledby="my-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="my-events-heading">My Events</h2>
                        <p>Manage events created by you.</p>
                    </div>
                </div>
                <div class="event-filters">
                    <div class="form-group">
                        <label for="event-search">Search Events</label>
                        <input type="search" id="event-search" name="event_search" placeholder="Search by event name">
                    </div>
                    <div class="form-group">
                        <label for="status-filter">Filter by Status</label>
                        <select id="status-filter" name="status_filter">
                            <option value="all">All</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table id="my-events-table">
                        <caption class="visually-hidden">Events created by the organizer</caption>
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Capacity</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($events)): ?>
                                <?php foreach ($events as $event):
                                    $eventStatus = (string) $event['status'];
                                    $statusClass = $eventStatus === 'published' ? 'status-open' : 'status-full';
                                ?>
                                    <tr data-event-row data-title="<?= h(mb_strtolower($event['title'])) ?>" data-status="<?= h($eventStatus) ?>">
                                        <td><?= h($event['title']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($event['start_datetime']))) ?></td>
                                        <td><?= h($event['venue']) ?></td>
                                        <td><?= (int) $event['registration_count'] ?> / <?= (int) $event['capacity'] ?></td>
                                        <td><span class="status <?= h($statusClass) ?>"><?= h(ucfirst($eventStatus)) ?></span></td>
                                        <td>
                                            <a href="event-management.php?event_id=<?= (int) $event['event_id'] ?>#create-event" class="table-action">Edit</a>
                                            &nbsp;
                                            <a href="event-management.php?participant_event_id=<?= (int) $event['event_id'] ?>#participants" class="table-action">Participants</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr data-filter-empty hidden><td colspan="6">No events match the search and status filter.</td></tr>
                            <?php else: ?>
                                <tr><td colspan="6">You have not created any events yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="participants" class="dashboard-section" aria-labelledby="participants-heading">
                <div class="section-header">
                    <div>
                        <h2 id="participants-heading">Participants</h2>
                        <p>Review registrations for one of your events.</p>
                    </div>
                </div>
                <form action="event-management.php#participants" method="get" class="event-filters">
                    <div class="form-group">
                        <label for="participant-event">Select Event</label>
                        <select id="participant-event" name="participant_event_id">
                            <option value="">Select an event</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?= (int) $event['event_id'] ?>" <?= $participantEventId === (int) $event['event_id'] ? 'selected' : '' ?>><?= h($event['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">View Participants</button>
                    </div>
                </form>

                <?php if ($selectedParticipantEvent !== null): ?>
                    <p>Showing registrations for <strong><?= h($selectedParticipantEvent['title']) ?></strong>.</p>
                <?php endif; ?>

                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Registered participants for the selected event</caption>
                        <thead>
                            <tr>
                                <th scope="col">Student Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Registration Date</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($selectedParticipantEvent === null): ?>
                                <tr><td colspan="5">Select one of your events to view its participant registrations.</td></tr>
                            <?php elseif (!empty($participants)): ?>
                                <?php foreach ($participants as $participant):
                                    $participantStatusClass = $participant['status'] === 'registered' ? 'status-open' : 'status-full';
                                ?>
                                    <tr>
                                        <td><?= h($participant['full_name']) ?></td>
                                        <td><?= h($participant['email']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($participant['registered_at']))) ?></td>
                                        <td><span class="status <?= h($participantStatusClass) ?>"><?= h(ucfirst((string) $participant['status'])) ?></span></td>
                                        <td><a class="table-action" href="mailto:<?= h($participant['email']) ?>">Email</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No registrations were found for this event.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>

    <script src="../public/js/main.js"></script>
    <script>
        // Client-side search and status filtering for the organizer's event list.
        const eventSearch = document.getElementById('event-search');
        const statusFilter = document.getElementById('status-filter');
        const eventTable = document.getElementById('my-events-table');

        if (eventSearch && statusFilter && eventTable) {
            const rows = Array.from(eventTable.querySelectorAll('tbody tr[data-event-row]'));
            const noMatchesRow = eventTable.querySelector('tbody tr[data-filter-empty]');

            function filterEvents() {
                const searchTerm = eventSearch.value.trim().toLocaleLowerCase();
                const selectedStatus = statusFilter.value;
                let visibleCount = 0;
                rows.forEach((row) => {
                    const title = row.dataset.title || '';
                    const status = row.dataset.status || '';
                    const visible = title.includes(searchTerm) && (selectedStatus === 'all' || status === selectedStatus);
                    row.hidden = !visible;
                    if (visible) visibleCount++;
                });
                if (noMatchesRow) { noMatchesRow.hidden = rows.length === 0 || visibleCount > 0; }
            }

            eventSearch.addEventListener('input', filterEvents);
            statusFilter.addEventListener('change', filterEvents);
        }
    </script>
</body>
</html>
