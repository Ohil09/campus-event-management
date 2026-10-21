<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');
require_once __DIR__ . '/../config/database.php';

$studentId   = (int) ($_SESSION['user_id'] ?? 0);
$studentName = (string) ($_SESSION['full_name'] ?? 'Student');

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Get search and category filters
$search   = trim((string) ($_GET['search'] ?? ''));
$category = (string) ($_GET['category'] ?? 'All');

// Fetch active categories for the filter
$categories    = [];
$categoryResult = $conn->query(
    "SELECT category_name FROM categories WHERE status = 'active' ORDER BY category_name"
);
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row['category_name'];
    }
}

// Dashboard stats
// Total registered events
$totalRegistered = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM event_registrations WHERE user_id = ? AND status = 'registered'"
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$stmt->bind_result($totalRegistered);
$stmt->fetch();
$stmt->close();
$totalRegistered = (int) $totalRegistered;

// Upcoming registered events
$upcomingRegistered = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM event_registrations er
     INNER JOIN events e ON er.event_id = e.event_id
     WHERE er.user_id = ? AND er.status = 'registered' AND e.start_datetime >= NOW()"
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$stmt->bind_result($upcomingRegistered);
$stmt->fetch();
$stmt->close();
$upcomingRegistered = (int) $upcomingRegistered;

// Total upcoming published events available
$totalAvailable = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM events e
     INNER JOIN categories c ON e.category_id = c.category_id
     WHERE e.status = 'published' AND c.status = 'active' AND e.start_datetime >= NOW()"
);
$stmt->execute();
$stmt->bind_result($totalAvailable);
$stmt->fetch();
$stmt->close();
$totalAvailable = (int) $totalAvailable;

// Volunteer applications count
$volunteerApps = 0;
$stmt = $conn->prepare(
    "SELECT COUNT(*) FROM volunteer_applications WHERE user_id = ?"
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$stmt->bind_result($volunteerApps);
$stmt->fetch();
$stmt->close();
$volunteerApps = (int) $volunteerApps;

// Fetch published upcoming events with category names
$sql = "SELECT e.event_id, e.title, e.description, e.start_datetime, e.end_datetime,
               e.venue, e.capacity, e.registration_deadline, c.category_name
        FROM events e
        INNER JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published' AND c.status = 'active' AND e.start_datetime >= NOW()";

$conditions = [];
$params     = [];
$types      = '';

if ($search !== '') {
    $conditions[] = '(e.title LIKE ? OR e.venue LIKE ?)';
    $searchTerm   = '%' . $search . '%';
    $params[]     = $searchTerm;
    $params[]     = $searchTerm;
    $types       .= 'ss';
}

if ($category !== 'All' && in_array($category, $categories, true)) {
    $conditions[] = 'c.category_name = ?';
    $params[]     = $category;
    $types       .= 's';
}

if (!empty($conditions)) {
    $sql .= ' AND ' . implode(' AND ', $conditions);
}

$sql .= ' ORDER BY e.start_datetime ASC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Unable to prepare the events query.');
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$events = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover and register for upcoming campus events">
    <title>Student Dashboard | Campus Event Management System</title>
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
                    <li><a href="index.php" class="nav-link active" aria-current="page"><span>Discover Events</span></a></li>
                    <li><a href="my-events.php" class="nav-link"><span>My Events</span></a></li>
                    <li><a href="volunteer-opportunities.php" class="nav-link"><span>Volunteer</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Student</p>
                    <h1>Discover Campus Events</h1>
                    <p class="page-description">Find events, learn new skills and participate in campus activities.</p>
                </div>
                <a href="my-events.php" class="btn btn-primary">My Events</a>
            </section>

            <section class="dashboard-stats" aria-labelledby="student-stats-heading">
                <h2 id="student-stats-heading" class="visually-hidden">Your activity summary</h2>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Available Events</h3></div>
                    <p class="stat-value"><?= $totalAvailable ?></p>
                    <p class="stat-description">Published upcoming events</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>My Registrations</h3></div>
                    <p class="stat-value"><?= $totalRegistered ?></p>
                    <p class="stat-description">Events you registered for</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Upcoming</h3></div>
                    <p class="stat-value"><?= $upcomingRegistered ?></p>
                    <p class="stat-description">Your upcoming events</p>
                </article>
                <article class="stat-card">
                    <div class="stat-card-header"><h3>Volunteer Apps</h3></div>
                    <p class="stat-value"><?= $volunteerApps ?></p>
                    <p class="stat-description">Your volunteer applications</p>
                </article>
            </section>

            <section class="dashboard-section" aria-labelledby="upcoming-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="upcoming-events-heading">Upcoming Events</h2>
                        <p>Browse and register for campus events.</p>
                    </div>
                </div>

                <form class="event-filters" method="get" action="index.php">
                    <div class="form-group">
                        <label for="event-search">Search Events</label>
                        <input type="search" id="event-search" name="search" placeholder="Search by title or venue" value="<?= h($search) ?>">
                    </div>
                    <div class="form-group">
                        <label for="category-filter">Category</label>
                        <select id="category-filter" name="category">
                            <option value="All">All Categories</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= h($c) ?>" <?= $category === $c ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;flex-direction:row;align-items:flex-end;gap:0.5rem;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>

                <?php if (!empty($events)): ?>
                    <div class="event-grid">
                        <?php foreach ($events as $event): ?>
                            <article class="event-card">
                                <span class="event-card-category"><?= h($event['category_name']) ?></span>
                                <h3><?= h($event['title']) ?></h3>
                                <p class="event-card-description"><?= h($event['description']) ?></p>
                                <div class="event-card-meta">
                                    <span>📅 <?= date('d M Y', strtotime($event['start_datetime'])) ?></span>
                                    <span>🕒 <?= date('h:i A', strtotime($event['start_datetime'])) ?></span>
                                    <span>📍 <?= h($event['venue']) ?></span>
                                    <span>👥 Capacity: <?= (int) $event['capacity'] ?></span>
                                    <span>📝 Deadline: <?= date('d M Y, h:i A', strtotime($event['registration_deadline'])) ?></span>
                                </div>
                                <div class="event-card-footer">
                                    <a href="event-details.php?id=<?= (int) $event['event_id'] ?>" class="btn btn-primary">View Details</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>No Events Found</h3>
                        <p>No upcoming published events match your search. Try another search or category.</p>
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
