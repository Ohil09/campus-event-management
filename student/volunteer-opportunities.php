<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('student');

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    $conn = null;
}

$studentId   = (int) ($_SESSION['user_id'] ?? 0);
$studentName = (string) ($_SESSION['full_name'] ?? 'Student');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$notice    = '';
$formError = '';

/* ---------------------------------------------------------
   Handle volunteer application submission
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken   = trim((string) ($_POST['csrf_token'] ?? ''));
    $opportunityId = filter_var($_POST['opportunity_id'] ?? 0, FILTER_VALIDATE_INT);

    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $formError = 'Session expired. Please refresh and try again.';
    } elseif ($opportunityId === false || $opportunityId < 1) {
        $formError = 'Invalid volunteer opportunity selected.';
    } elseif ($conn === null) {
        $formError = 'Database connection failed. Please ensure MariaDB is running.';
    } else {
        // Check that the opportunity exists and event is published
        $checkStmt = $conn->prepare(
            'SELECT vo.opportunity_id, vo.event_id, vo.role_name, vo.required_count,
                    e.title AS event_title,
                    (SELECT COUNT(*) FROM volunteer_assignments vasn
                     INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
                     WHERE va.opportunity_id = vo.opportunity_id AND vasn.status IN (\'assigned\', \'completed\')) AS assigned_count
             FROM volunteer_opportunities vo
             INNER JOIN events e ON vo.event_id = e.event_id
             WHERE vo.opportunity_id = ? AND e.status = \'published\''
        );
        $checkStmt->bind_param('i', $opportunityId);
        $checkStmt->execute();
        $opp = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if ($opp === null) {
            $formError = 'Volunteer opportunity not found or event is not active.';
        } elseif ((int) $opp['assigned_count'] >= (int) $opp['required_count']) {
            $formError = 'All volunteer positions for this role have already been filled.';
        } else {
            // Check if already applied
            $dupStmt = $conn->prepare(
                'SELECT application_id FROM volunteer_applications WHERE opportunity_id = ? AND user_id = ?'
            );
            $dupStmt->bind_param('ii', $opportunityId, $studentId);
            $dupStmt->execute();
            $dupStmt->store_result();
            $alreadyApplied = ($dupStmt->num_rows > 0);
            $dupStmt->close();

            if ($alreadyApplied) {
                $formError = 'You have already applied for this volunteer opportunity.';
            } else {
                try {
                    $insertStmt = $conn->prepare(
                        'INSERT INTO volunteer_applications (opportunity_id, user_id, status) VALUES (?, ?, \'pending\')'
                    );
                    $insertStmt->bind_param('ii', $opportunityId, $studentId);
                    $insertStmt->execute();
                    $insertStmt->close();
                    $notice = 'Your application for "' . $opp['role_name'] . '" has been submitted successfully!';
                } catch (Throwable $e) {
                    error_log('Volunteer application failed: ' . $e->getMessage());
                    $formError = 'Could not submit application. Please try again.';
                }
            }
        }
    }
}

/* ---------------------------------------------------------
   Fetch data for student display
--------------------------------------------------------- */
$opportunities  = [];
$myApplications = [];

if ($conn !== null) {
    // All published-event opportunities with slot counts
    $oppStmt = $conn->prepare(
        "SELECT vo.opportunity_id, vo.role_name, vo.description AS opp_description,
                vo.required_count, vo.event_id,
                e.title AS event_title, e.start_datetime, e.venue,
                (SELECT COUNT(*) FROM volunteer_assignments vsub
                 INNER JOIN volunteer_applications vasub ON vsub.application_id = vasub.application_id
                 WHERE vasub.opportunity_id = vo.opportunity_id AND vsub.status IN ('assigned','completed')) AS assigned_count
         FROM volunteer_opportunities vo
         INNER JOIN events e ON vo.event_id = e.event_id
         WHERE e.status = 'published'
         ORDER BY e.start_datetime ASC, vo.role_name ASC"
    );
    $oppStmt->execute();
    $opportunities = $oppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $oppStmt->close();

    // Student's own applications
    $myStmt = $conn->prepare(
        "SELECT va.application_id, va.opportunity_id, va.status AS app_status, va.applied_at,
                vo.role_name, vo.event_id,
                e.title AS event_title,
                COALESCE(
                    (SELECT vasn.status FROM volunteer_assignments vasn
                     WHERE vasn.application_id = va.application_id LIMIT 1),
                    ''
                ) AS assignment_status
         FROM volunteer_applications va
         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
         INNER JOIN events e ON vo.event_id = e.event_id
         WHERE va.user_id = ?
         ORDER BY va.applied_at DESC"
    );
    $myStmt->bind_param('i', $studentId);
    $myStmt->execute();
    $myApplications = $myStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $myStmt->close();
}

// Build map of opportunity_id => user application
$appliedMap = [];
foreach ($myApplications as $app) {
    $appliedMap[(int) $app['opportunity_id']] = $app;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Browse and apply for campus volunteer opportunities">
    <title>Volunteer Opportunities | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/student.css">
    <link rel="stylesheet" href="../public/css/volunteer.css">
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
                    <li><a href="my-events.php" class="nav-link"><span aria-hidden="true">&#128196;</span><span>My Events</span></a></li>
                    <li><a href="volunteer-opportunities.php" class="nav-link active" aria-current="page"><span aria-hidden="true">&#129309;</span><span>Volunteer</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Student / Volunteer Opportunities</p>
                    <h1>Volunteer Opportunities</h1>
                    <p class="page-description">Browse available volunteer roles for upcoming campus events and submit your application.</p>
                </div>
            </section>

            <?php if ($notice !== ''): ?>
                <p role="status" class="form-message"><?= h($notice) ?></p>
            <?php endif; ?>
            <?php if ($formError !== ''): ?>
                <p role="alert" class="form-message" style="background-color:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;"><?= h($formError) ?></p>
            <?php endif; ?>

            <!-- Available Opportunities -->
            <section class="dashboard-section" aria-labelledby="opportunities-heading">
                <div class="section-header">
                    <div>
                        <h2 id="opportunities-heading">Available Opportunities</h2>
                        <p>Apply to volunteer at upcoming campus events.</p>
                    </div>
                </div>

                <?php if (!empty($opportunities)): ?>
                    <div class="opportunity-grid">
                        <?php foreach ($opportunities as $opp):
                            $oppId         = (int) $opp['opportunity_id'];
                            $requiredCount = (int) $opp['required_count'];
                            $assignedCount = (int) $opp['assigned_count'];
                            $slotsFilled   = min($assignedCount, $requiredCount);
                            $slotsPercent  = $requiredCount > 0 ? round(($slotsFilled / $requiredCount) * 100) : 0;
                            $isFull        = $slotsFilled >= $requiredCount;
                            $hasApplied    = isset($appliedMap[$oppId]);
                            $appStatus     = $hasApplied ? (string) $appliedMap[$oppId]['app_status'] : '';
                        ?>
                            <article class="opportunity-card" id="opp-<?= $oppId ?>">
                                <div class="opportunity-card-header">
                                    <h3><?= h($opp['role_name']) ?></h3>
                                    <?php if ($hasApplied): ?>
                                        <span class="status status-<?= h($appStatus) ?>"><?= h(ucfirst($appStatus)) ?></span>
                                    <?php endif; ?>
                                </div>

                                <p class="opportunity-event"><?= h($opp['event_title']) ?></p>

                                <div class="opportunity-meta">
                                    <span class="opportunity-meta-item">
                                        <strong>Venue:</strong> <?= h($opp['venue']) ?>
                                    </span>
                                    <span class="opportunity-meta-item">
                                        <strong>Date:</strong> <?= h(date('d M Y, h:i A', strtotime($opp['start_datetime']))) ?>
                                    </span>
                                    <span class="opportunity-meta-item">
                                        <strong>Needed:</strong> <?= $requiredCount ?> volunteers
                                    </span>
                                </div>

                                <?php if ($opp['opp_description'] !== '' && $opp['opp_description'] !== null): ?>
                                    <p class="opportunity-description"><?= h($opp['opp_description']) ?></p>
                                <?php endif; ?>

                                <div class="opportunity-card-footer">
                                    <div class="slots-indicator">
                                        <div class="slots-bar">
                                            <div class="slots-bar-fill <?= $isFull ? 'slots-full' : '' ?>"
                                                 style="width: <?= $slotsPercent ?>%"></div>
                                        </div>
                                        <span class="slots-text"><?= $slotsFilled ?> / <?= $requiredCount ?> assigned</span>
                                    </div>

                                    <?php if ($hasApplied): ?>
                                        <button type="button" class="btn btn-apply btn-disabled" disabled>Already Applied</button>
                                    <?php elseif ($isFull): ?>
                                        <button type="button" class="btn btn-apply btn-disabled" disabled>Slots Full</button>
                                    <?php else: ?>
                                        <form method="post" action="volunteer-opportunities.php#opp-<?= $oppId ?>" style="margin:0">
                                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="opportunity_id" value="<?= $oppId ?>">
                                            <button type="submit" class="btn btn-apply">Apply Now</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" style="font-size:2.5rem;margin-bottom:0.75rem;">&#128203;</div>
                        <h3>No Opportunities Available</h3>
                        <p>There are no volunteer opportunities posted for upcoming events right now. Check back later!</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- My Applications -->
            <section class="dashboard-section" aria-labelledby="my-applications-heading">
                <div class="section-header">
                    <div>
                        <h2 id="my-applications-heading">My Applications</h2>
                        <p>Track the status of your volunteer applications and active assignments.</p>
                    </div>
                </div>

                <?php if (!empty($myApplications)): ?>
                    <div class="my-applications-list">
                        <?php foreach ($myApplications as $app):
                            $appStatusVal = (string) $app['app_status'];
                            $assignStatus = (string) $app['assignment_status'];
                            $statusClass  = 'status-' . $appStatusVal;
                        ?>
                            <div class="my-application-item">
                                <div class="my-application-info">
                                    <strong><?= h($app['role_name']) ?></strong>
                                    <span><?= h($app['event_title']) ?> &bull; Applied <?= h(date('d M Y', strtotime($app['applied_at']))) ?></span>
                                </div>
                                <div class="my-application-badges">
                                    <span class="status <?= h($statusClass) ?>"><?= h(ucfirst($appStatusVal)) ?></span>
                                    <?php if ($assignStatus !== ''): ?>
                                        <span class="status status-<?= h($assignStatus) ?>"><?= h(ucfirst($assignStatus)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon" style="font-size:2.5rem;margin-bottom:0.75rem;">&#128221;</div>
                        <h3>No Applications Yet</h3>
                        <p>You haven't applied for any volunteer opportunities. Browse the available roles above and apply!</p>
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
