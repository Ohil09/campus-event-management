<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');

require_once __DIR__ . '/../config/database.php';

// Get search and category filters
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'All';

// Fetch active categories for the filter
$categories = [];

$categorySql = "
    SELECT category_name
    FROM categories
    WHERE status = 'active'
    ORDER BY category_name
";

$categoryResult = $conn->query($categorySql);

if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row['category_name'];
    }
}

// Fetch published upcoming events and their category names
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
        c.category_name
    FROM events e
    INNER JOIN categories c
        ON e.category_id = c.category_id
    WHERE e.status = 'published'
      AND c.status = 'active'
      AND e.start_datetime >= NOW()
";

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = '(e.title LIKE ? OR e.venue LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

if ($category !== 'All' && in_array($category, $categories, true)) {
    $conditions[] = 'c.category_name = ?';
    $params[] = $category;
    $types .= 's';
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

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Discover Events</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f4f6fb;
    color: #26324a;
}

header {
    background: #fff;
    padding: 20px 7%;
    display: flex;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

header a {
    margin-left: 18px;
    color: #3157c8;
    text-decoration: none;
    font-weight: bold;
}

.hero {
    padding: 40px 7%;
    background: #e7edff;
}

main {
    max-width: 1100px;
    margin: auto;
    padding: 25px;
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 20px 0;
}

input, select {
    padding: 12px;
    border: 1px solid #ccd3e0;
    border-radius: 8px;
}

input {
    flex: 1;
    min-width: 180px;
}

button, .btn {
    display: inline-block;
    padding: 11px 15px;
    background: #3157c8;
    color: white;
    border: 0;
    border-radius: 7px;
    text-decoration: none;
    cursor: pointer;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
}

.card {
    background: white;
    border: 1px solid #e1e5ef;
    border-radius: 12px;
    padding: 20px;
}

.tag {
    color: #3157c8;
    font-size: 13px;
    font-weight: bold;
}

.info {
    color: #5d687d;
    line-height: 1.8;
}

.empty {
    background: white;
    padding: 20px;
    border-radius: 10px;
}
</style>
</head>

<body>

<header>
    <strong>CampusEvents</strong>
    <nav>
        <a href="index.php">Discover Events</a>
        <a href="my-events.php">My Events</a>
    </nav>
</header>

<section class="hero">
    <h1>Discover Campus Events</h1>
    <p>Find events, learn new skills and participate in campus activities.</p>
</section>

<main>
    <h2>Upcoming Events</h2>

    <form class="filters" method="get">
        <input
            name="search"
            placeholder="Search by title or venue"
            value="<?= h($search) ?>"
        >

        <select name="category">
            <option value="All">All Categories</option>

            <?php foreach ($categories as $c): ?>
                <option
                    value="<?= h($c) ?>"
                    <?= $category === $c ? 'selected' : '' ?>
                >
                    <?= h($c) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Search</button>
        <a class="btn" href="index.php">Reset</a>
    </form>

    <div class="grid">
        <?php if ($result->num_rows > 0): ?>

            <?php while ($event = $result->fetch_assoc()): ?>

                <article class="card">
                    <p class="tag">
                        <?= h($event['category_name']) ?>
                    </p>

                    <h3><?= h($event['title']) ?></h3>

                    <p><?= nl2br(h($event['description'])) ?></p>

                    <div class="info">
                        📅 <?= date(
                            'd M Y',
                            strtotime($event['start_datetime'])
                        ) ?><br>

                        🕒 <?= date(
                            'h:i A',
                            strtotime($event['start_datetime'])
                        ) ?><br>

                        📍 <?= h($event['venue']) ?><br>

                        👥 Capacity: <?= (int)$event['capacity'] ?><br>

                        📝 Registration deadline:
                        <?= date(
                            'd M Y, h:i A',
                            strtotime($event['registration_deadline'])
                        ) ?>
                    </div>

                    <p>
                        <a
                            class="btn"
                            href="event-details.php?id=<?= (int)$event['event_id'] ?>"
                        >
                            View Details
                        </a>
                    </p>
                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <p class="empty">
                No upcoming published events found.
                Try another search or category.
            </p>

        <?php endif; ?>
    </div>
</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>

