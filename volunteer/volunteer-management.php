<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';

// Allow access to organizers and administrators
if (!in_array($_SESSION['role'] ?? '', ['organizer', 'administrator'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    $conn = null;
}

$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$userName      = (string) ($_SESSION['full_name'] ?? 'Organizer');
$userRole      = (string) ($_SESSION['role'] ?? 'organizer');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$notice    = '';
$formError = '';

// Pick up notice message from PRG redirect
if (isset($_GET['notice']) && $_GET['notice'] !== '') {
    $notice = htmlspecialchars(urldecode((string) $_GET['notice']), ENT_QUOTES, 'UTF-8');
}

// Optional event filter from GET parameter
$filterEventId = filter_var($_GET['filter_event'] ?? 0, FILTER_VALIDATE_INT);
$filterEventId = ($filterEventId !== false && $filterEventId > 0) ? $filterEventId : 0;

/* ---------------------------------------------------------
   Handle POST actions:
   - approve / reject application
   - assign volunteer (with quota & time overlap verification)
   - complete / cancel assignment
   - create new opportunity
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = trim((string) ($_POST['csrf_token'] ?? ''));
    $action      = trim((string) ($_POST['vol_action'] ?? ''));

    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $formError = 'Session expired. Please refresh and try again.';
    } elseif ($conn === null) {
        $formError = 'Database connection failed. Please ensure MariaDB is running.';
    } else {
        try {
            switch ($action) {

                /* ---- Approve / Reject Application ---- */
                case 'approve_application':
                case 'reject_application':
                    $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($applicationId === false || $applicationId < 1) {
                        $formError = 'Invalid application selected.';
                        break;
                    }

                    // Verify application exists and fetch applicant & role details
                    $ownershipSql = 'SELECT va.application_id, va.status, u.full_name AS applicant_name, vo.role_name
                                     FROM volunteer_applications va
                                     INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                                     INNER JOIN events e ON vo.event_id = e.event_id
                                     INNER JOIN users u ON va.user_id = u.user_id
                                     WHERE va.application_id = ?';
                    $verifyStmt = $conn->prepare($ownershipSql);
                    $verifyStmt->bind_param('i', $applicationId);
                    $verifyStmt->execute();
                    $appRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($appRow === null) {
                        $formError = 'Application not found or you do not have permission to manage it.';
                    } elseif ($appRow['status'] !== 'pending') {
                        $formError = 'This application has already been ' . $appRow['status'] . '.';
                    } else {
                        $newStatus = ($action === 'approve_application') ? 'approved' : 'rejected';
                        $updateStmt = $conn->prepare(
                            'UPDATE volunteer_applications SET status = ? WHERE application_id = ?'
                        );
                        $updateStmt->bind_param('si', $newStatus, $applicationId);
                        $updateStmt->execute();
                        $updateStmt->close();
                        // PRG pattern: redirect to prevent form re-submission on refresh
                        $msg = urlencode('Application for ' . $appRow['applicant_name'] . ' (' . $appRow['role_name'] . ') marked as ' . $newStatus . '.');
                        $loc = 'volunteer-management.php' . ($filterEventId > 0 ? '?filter_event=' . $filterEventId . '&' : '?') . 'notice=' . $msg;
                        header('Location: ' . $loc);
                        exit;
                    }
                    break;

                /* ---- Assign Volunteer ---- */
                case 'assign_volunteer':
                    $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($applicationId === false || $applicationId < 1) {
                        $formError = 'Invalid application selected.';
                        break;
                    }

                    // Verify application is approved and fetch event & opportunity details
                    $assignVerifySql = 'SELECT va.application_id, va.opportunity_id, va.user_id, va.status AS app_status,
                                              vo.role_name, vo.required_count,
                                              e.event_id, e.title AS event_title, e.start_datetime, e.end_datetime,
                                              u.full_name AS volunteer_name
                                       FROM volunteer_applications va
                                       INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                                       INNER JOIN events e ON vo.event_id = e.event_id
                                       INNER JOIN users u ON va.user_id = u.user_id
                                       WHERE va.application_id = ? AND va.status = \'approved\'';
                    $verifyStmt = $conn->prepare($assignVerifySql);
                    $verifyStmt->bind_param('i', $applicationId);
                    $verifyStmt->execute();
                    $appRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($appRow === null) {
                        $formError = 'Application not found or not approved.';
                        break;
                    }

                    $userId        = (int) $appRow['user_id'];
                    $oppId         = (int) $appRow['opportunity_id'];
                    $requiredCount = (int) $appRow['required_count'];
                    $newEventStart = (string) $appRow['start_datetime'];
                    $newEventEnd   = (string) $appRow['end_datetime'];
                    $volunteerName = (string) $appRow['volunteer_name'];
                    $roleName      = (string) $appRow['role_name'];

                    // 1. Check if an assignment already exists for this application
                    $existingAsnStmt = $conn->prepare(
                        'SELECT assignment_id, status FROM volunteer_assignments WHERE application_id = ?'
                    );
                    $existingAsnStmt->bind_param('i', $applicationId);
                    $existingAsnStmt->execute();
                    $existingAsn = $existingAsnStmt->get_result()->fetch_assoc();
                    $existingAsnStmt->close();

                    if ($existingAsn !== null && $existingAsn['status'] === 'assigned') {
                        $formError = 'This volunteer is already actively assigned to this role.';
                        break;
                    }

                    // 2. Check opportunity capacity/quota limit
                    $quotaStmt = $conn->prepare(
                        'SELECT COUNT(*) AS active_count
                         FROM volunteer_assignments vasn
                         INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
                         WHERE va.opportunity_id = ? AND vasn.status IN (\'assigned\', \'completed\')'
                    );
                    $quotaStmt->bind_param('i', $oppId);
                    $quotaStmt->execute();
                    $quotaRow = $quotaStmt->get_result()->fetch_assoc();
                    $quotaStmt->close();
                    $activeCount = (int) ($quotaRow['active_count'] ?? 0);

                    if ($activeCount >= $requiredCount) {
                        $formError = sprintf(
                            'Cannot assign volunteer: Quota for role "%s" has already been reached (%d/%d filled).',
                            $roleName,
                            $activeCount,
                            $requiredCount
                        );
                        break;
                    }

                    // 3. Prevent overlapping volunteer assignments:
                    // Check if this volunteer has any active assignment ('assigned') whose event time overlaps:
                    // existing_start < new_end AND existing_end > new_start
                    $overlapStmt = $conn->prepare(
                        'SELECT e2.title AS conflicting_event,
                                e2.start_datetime AS conflict_start,
                                e2.end_datetime AS conflict_end,
                                vo2.role_name AS conflicting_role
                         FROM volunteer_assignments vasn2
                         INNER JOIN volunteer_applications va2 ON vasn2.application_id = va2.application_id
                         INNER JOIN volunteer_opportunities vo2 ON va2.opportunity_id = vo2.opportunity_id
                         INNER JOIN events e2 ON vo2.event_id = e2.event_id
                         WHERE va2.user_id = ?
                           AND vasn2.status = \'assigned\'
                           AND e2.start_datetime < ?
                           AND e2.end_datetime > ?
                         LIMIT 1'
                    );
                    $overlapStmt->bind_param('iss', $userId, $newEventEnd, $newEventStart);
                    $overlapStmt->execute();
                    $conflict = $overlapStmt->get_result()->fetch_assoc();
                    $overlapStmt->close();

                    if ($conflict !== null) {
                        $formError = sprintf(
                            'Schedule Conflict: %s is already assigned to "%s" (%s) from %s to %s. Overlapping volunteer shifts are not permitted.',
                            $volunteerName,
                            $conflict['conflicting_event'],
                            $conflict['conflicting_role'],
                            date('d M Y, h:i A', strtotime($conflict['conflict_start'])),
                            date('h:i A', strtotime($conflict['conflict_end']))
                        );
                        break;
                    }

                    // 4. Create or reactivate assignment
                    if ($existingAsn !== null) {
                        $updateAsnStmt = $conn->prepare(
                            'UPDATE volunteer_assignments SET status = \'assigned\', assigned_at = CURRENT_TIMESTAMP WHERE assignment_id = ?'
                        );
                        $updateAsnStmt->bind_param('i', $existingAsn['assignment_id']);
                        $updateAsnStmt->execute();
                        $updateAsnStmt->close();
                    } else {
                        $assignStmt = $conn->prepare(
                            'INSERT INTO volunteer_assignments (application_id, status) VALUES (?, \'assigned\')'
                        );
                        $assignStmt->bind_param('i', $applicationId);
                        $assignStmt->execute();
                        $assignStmt->close();
                    }

                    // PRG: redirect to prevent re-submission on refresh
                    $msg = urlencode(sprintf('Volunteer %s successfully assigned to "%s".', $volunteerName, $roleName));
                    $loc = 'volunteer-management.php' . ($filterEventId > 0 ? '?filter_event=' . $filterEventId . '&' : '?') . 'notice=' . $msg;
                    header('Location: ' . $loc);
                    exit;

                /* ---- Complete / Cancel Assignment ---- */
                case 'complete_assignment':
                case 'cancel_assignment':
                    $assignmentId = filter_var($_POST['assignment_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($assignmentId === false || $assignmentId < 1) {
                        $formError = 'Invalid assignment selected.';
                        break;
                    }
                    // Verify assignment exists and fetch volunteer & role details
                    $asnVerifySql = 'SELECT vasn.assignment_id, vasn.status, u.full_name AS volunteer_name, vo.role_name
                                     FROM volunteer_assignments vasn
                                     INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
                                     INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                                     INNER JOIN events e ON vo.event_id = e.event_id
                                     INNER JOIN users u ON va.user_id = u.user_id
                                     WHERE vasn.assignment_id = ?';
                    $verifyStmt = $conn->prepare($asnVerifySql);
                    $verifyStmt->bind_param('i', $assignmentId);
                    $verifyStmt->execute();
                    $asnRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($asnRow === null) {
                        $formError = 'Assignment not found or you do not have permission to manage it.';
                    } else {
                        $newStatus = ($action === 'complete_assignment') ? 'completed' : 'cancelled';
                        $updateStmt = $conn->prepare(
                            'UPDATE volunteer_assignments SET status = ? WHERE assignment_id = ?'
                        );
                        $updateStmt->bind_param('si', $newStatus, $assignmentId);
                        $updateStmt->execute();
                        $updateStmt->close();
                        // PRG: redirect to prevent re-submission on refresh
                        $msg = urlencode('Assignment for ' . $asnRow['volunteer_name'] . ' (' . $asnRow['role_name'] . ') marked as ' . $newStatus . '.');
                        $loc = 'volunteer-management.php' . ($filterEventId > 0 ? '?filter_event=' . $filterEventId . '&' : '?') . 'notice=' . $msg;
                        header('Location: ' . $loc);
                        exit;
                    }
                    break;

                /* ---- Create New Opportunity ---- */
                case 'create_opportunity':
                    $eventId       = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT);
                    $roleName      = trim((string) ($_POST['role_name'] ?? ''));
                    $description   = trim((string) ($_POST['opp_description'] ?? ''));
                    $requiredCount = filter_var($_POST['required_count'] ?? 0, FILTER_VALIDATE_INT);

                    if ($eventId === false || $eventId < 1) {
                        $formError = 'Please select a valid event.';
                    } elseif ($roleName === '' || mb_strlen($roleName) > 100) {
                        $formError = 'Enter a valid role name (max 100 characters).';
                    } elseif ($requiredCount === false || $requiredCount < 1 || $requiredCount > 100) {
                        $formError = 'Required volunteers count must be between 1 and 100.';
                    } else {
                        // Verify event exists and is not cancelled
                        $evCheckSql = 'SELECT event_id FROM events WHERE event_id = ? AND status != \'cancelled\'';
                        $ownerStmt = $conn->prepare($evCheckSql);
                        $ownerStmt->bind_param('i', $eventId);
                        $ownerStmt->execute();
                        $ownerStmt->store_result();
                        if ($ownerStmt->num_rows === 0) {
                            $formError = 'Event not found, is cancelled, or you do not have permission to add roles to it.';
                        } else {
                            $insertStmt = $conn->prepare(
                                'INSERT INTO volunteer_opportunities (event_id, role_name, description, required_count)
                                 VALUES (?, ?, ?, ?)'
                            );
                            $insertStmt->bind_param('issi', $eventId, $roleName, $description, $requiredCount);
                            $insertStmt->execute();
                            $insertStmt->close();
                            // PRG: redirect to prevent re-submission on refresh
                            $msg = urlencode('Volunteer opportunity for "' . $roleName . '" created successfully.');
                            header('Location: volunteer-management.php?notice=' . $msg . '#create-opportunity');
                            exit;
                        }
                        $ownerStmt->close();
                    }
                    break;

                default:
                    $formError = 'Invalid action requested.';
            }
        } catch (Throwable $e) {
            error_log('Volunteer management error: ' . $e->getMessage());
            $formError = 'An error occurred while processing your request. Please try again.';
        }
    }
}

/* ---------------------------------------------------------
   Fetch data for organizer display
--------------------------------------------------------- */
$allActiveEvents = [];
$opportunities   = [];
$applications    = [];
$assignments     = [];
$summaryStats    = ['total_opportunities' => 0, 'pending_apps' => 0, 'approved_apps' => 0, 'total_assigned' => 0, 'completed' => 0];

if ($conn !== null) {
    // All active events (for filter dropdown and create opportunity form)
    $evQuery = $conn->query(
        "SELECT event_id, title FROM events WHERE status != 'cancelled' ORDER BY start_datetime DESC"
    );
    if ($evQuery) {
        $allActiveEvents = $evQuery->fetch_all(MYSQLI_ASSOC);
    }

    // Base condition for event filter
    $eventFilterSql = ($filterEventId > 0) ? ' WHERE e.event_id = ? ' : ' ';

    // 1. Volunteer Opportunities
    $oppSql = "SELECT vo.opportunity_id, vo.role_name, vo.description AS opp_description,
                      vo.required_count, vo.event_id,
                      e.title AS event_title,
                      (SELECT COUNT(*) FROM volunteer_assignments vsub
                       INNER JOIN volunteer_applications vasub ON vsub.application_id = vasub.application_id
                       WHERE vasub.opportunity_id = vo.opportunity_id AND vsub.status IN ('assigned','completed')) AS assigned_count
               FROM volunteer_opportunities vo
               INNER JOIN events e ON vo.event_id = e.event_id"
               . $eventFilterSql .
               "ORDER BY e.start_datetime DESC, vo.role_name ASC";

    $oppStmt = $conn->prepare($oppSql);
    if ($filterEventId > 0) {
        $oppStmt->bind_param('i', $filterEventId);
    }
    $oppStmt->execute();
    $opportunities = $oppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $oppStmt->close();

    // 2. Volunteer Applications
    $appSql = 'SELECT va.application_id, va.opportunity_id, va.user_id, va.status AS app_status, va.applied_at,
                      u.full_name, u.email,
                      vo.role_name, vo.event_id,
                      e.title AS event_title
               FROM volunteer_applications va
               INNER JOIN users u ON va.user_id = u.user_id
               INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
               INNER JOIN events e ON vo.event_id = e.event_id'
               . $eventFilterSql .
               'ORDER BY va.applied_at DESC';

    $appStmt = $conn->prepare($appSql);
    if ($filterEventId > 0) {
        $appStmt->bind_param('i', $filterEventId);
    }
    $appStmt->execute();
    $applications = $appStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $appStmt->close();

    // 3. Volunteer Assignments
    $asnSql = 'SELECT vasn.assignment_id, vasn.application_id, vasn.status AS asn_status, vasn.assigned_at,
                      va.opportunity_id, va.user_id,
                      u.full_name, u.email,
                      vo.role_name, vo.event_id,
                      e.title AS event_title
               FROM volunteer_assignments vasn
               INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
               INNER JOIN users u ON va.user_id = u.user_id
               INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
               INNER JOIN events e ON vo.event_id = e.event_id'
               . $eventFilterSql .
               'ORDER BY vasn.assigned_at DESC';

    $asnStmt = $conn->prepare($asnSql);
    if ($filterEventId > 0) {
        $asnStmt->bind_param('i', $filterEventId);
    }
    $asnStmt->execute();
    $assignments = $asnStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $asnStmt->close();

    // Summary statistics
    $summaryStats['total_opportunities'] = count($opportunities);
    foreach ($applications as $app) {
        if ($app['app_status'] === 'pending')  $summaryStats['pending_apps']++;
        if ($app['app_status'] === 'approved') $summaryStats['approved_apps']++;
    }
    foreach ($assignments as $asn) {
        if ($asn['asn_status'] === 'assigned')  $summaryStats['total_assigned']++;
        if ($asn['asn_status'] === 'completed') $summaryStats['completed']++;
    }
}

// Build lookup: which application_ids have an active assignment
$assignedAppMap = [];
foreach ($assignments as $asn) {
    if ($asn['asn_status'] === 'assigned' || $asn['asn_status'] === 'completed') {
        $assignedAppMap[(int) $asn['application_id']] = $asn['asn_status'];
    }
}

// Filter applications by tab category
$pendingApps  = array_filter($applications, fn($a) => $a['app_status'] === 'pending');
$approvedApps = array_filter($applications, fn($a) => $a['app_status'] === 'approved');
$rejectedApps = array_filter($applications, fn($a) => $a['app_status'] === 'rejected');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage volunteer applications and assignments across campus events">
    <title>Volunteer Management | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/organizer.css">
    <link rel="stylesheet" href="../public/css/volunteer.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent</a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="organizer-navigation">&#9776;</button>
            <div class="header-user">
                <span class="welcome-text">Welcome, <?= h($userName) ?> (<?= h(ucfirst($userRole)) ?>)</span>
                <a href="../auth/logout.php" class="logout-link">Logout</a>
            </div>
        </div>
    </header>

    <div class="page-layout">
        <aside class="sidebar" id="organizer-navigation" aria-label="Organizer navigation">
            <nav>
                <h2 class="sidebar-title">Management</h2>
                <ul class="sidebar-menu">
                    <li><a href="../organizer/organizer-dashboard.php" class="nav-link"><span aria-hidden="true">&#128202;</span><span>Dashboard</span></a></li>
                    <li><a href="../organizer/event-management.php" class="nav-link"><span aria-hidden="true">&#128197;</span><span>Manage Events</span></a></li>
                    <li><a href="volunteer-management.php" class="nav-link active" aria-current="page"><span aria-hidden="true">&#129309;</span><span>Volunteers</span></a></li>
                    <li><a href="../organizer/event-management.php#participants" class="nav-link"><span aria-hidden="true">&#128101;</span><span>Participants</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Portal / Volunteer Management</p>
                    <h1>Volunteer Management</h1>
                    <p class="page-description">Review volunteer applications, approve or reject them, assign volunteers, and track contributions.</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('create-opportunity').scrollIntoView({behavior:'smooth'})">Create Opportunity</button>
            </section>

            <?php if ($notice !== ''): ?>
                <p role="status" class="form-message"><?= h($notice) ?></p>
            <?php endif; ?>
            <?php if ($formError !== ''): ?>
                <p role="alert" class="form-message" style="background-color:#fee2e2;color:#991b1b;border-left:4px solid #ef4444;"><?= h($formError) ?></p>
            <?php endif; ?>

            <!-- Filter Controls -->
            <div class="dashboard-section" style="padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1rem;">
                <form method="get" action="volunteer-management.php" style="display:flex;align-items:center;gap:0.75rem;margin:0;flex-wrap:wrap;">
                    <label for="filter_event" style="font-weight:600;font-size:0.9rem;color:#163a5f;">Filter Applications by Event:</label>
                    <select id="filter_event" name="filter_event" onchange="this.form.submit()" style="padding:0.45rem 0.85rem;border:1px solid #d1d5db;border-radius:6px;font-size:0.9rem;background:#fff;min-width:240px;">
                        <option value="0">All Campus Events (<?= count($applications) ?> Total Applications)</option>
                        <?php foreach ($allActiveEvents as $ev): ?>
                            <option value="<?= (int) $ev['event_id'] ?>" <?= $filterEventId === (int) $ev['event_id'] ? 'selected' : '' ?>>
                                <?= h($ev['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($filterEventId > 0): ?>
                        <a href="volunteer-management.php" class="btn btn-secondary" style="padding:0.45rem 0.85rem;font-size:0.85rem;text-decoration:none;">Show All Events</a>
                    <?php endif; ?>
                </form>
                <div>
                    <span style="font-size:0.85rem;color:#6b7280;">Showing data for: <strong><?= $filterEventId > 0 ? 'Selected Event' : 'All Events' ?></strong></span>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="vol-summary-cards">
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['total_opportunities'] ?></p>
                    <p class="stat-description">Total Opportunities</p>
                </article>
                <article class="vol-summary-card" style="border-top: 4px solid #f59e0b;">
                    <p class="stat-value" style="color:#b45309;"><?= $summaryStats['pending_apps'] ?></p>
                    <p class="stat-description">Pending Applications</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['approved_apps'] ?></p>
                    <p class="stat-description">Approved Applications</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['total_assigned'] ?></p>
                    <p class="stat-description">Active Assignments</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['completed'] ?></p>
                    <p class="stat-description">Completed</p>
                </article>
            </div>

            <!-- Applications Section with Tabs -->
            <section class="dashboard-section" aria-labelledby="applications-heading">
                <div class="section-header">
                    <div>
                        <h2 id="applications-heading">Volunteer Applications</h2>
                        <p>Review volunteer applicants, approve or reject applications, and assign volunteers to event roles.</p>
                    </div>
                </div>

                <div class="vol-tabs" role="tablist" aria-label="Application status tabs">
                    <button class="vol-tab active" role="tab" aria-selected="true" aria-controls="tab-pending" id="tab-btn-pending" data-tab="pending">
                        Pending <span class="count-badge" style="background:#fef3c7;color:#92400e;font-weight:700;"><?= count($pendingApps) ?></span>
                    </button>
                    <button class="vol-tab" role="tab" aria-selected="false" aria-controls="tab-approved" id="tab-btn-approved" data-tab="approved">
                        Approved <span class="count-badge"><?= count($approvedApps) ?></span>
                    </button>
                    <button class="vol-tab" role="tab" aria-selected="false" aria-controls="tab-rejected" id="tab-btn-rejected" data-tab="rejected">
                        Rejected <span class="count-badge"><?= count($rejectedApps) ?></span>
                    </button>
                </div>

                <!-- Pending Tab -->
                <div class="vol-tab-panel active" role="tabpanel" id="tab-pending" aria-labelledby="tab-btn-pending">
                    <div class="table-wrapper">
                        <table>
                            <caption class="visually-hidden">Pending volunteer applications</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Applied Date</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pendingApps)): ?>
                                    <?php foreach ($pendingApps as $app): ?>
                                        <tr>
                                            <td><strong><?= h($app['full_name']) ?></strong></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><span class="status status-open"><?= h($app['role_name']) ?></span></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y, h:i A', strtotime($app['applied_at']))) ?></td>
                                            <td>
                                                <div class="action-btn-group">
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="approve_application">
                                                        <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                                        <button type="submit" class="btn-approve">&#10003; Approve</button>
                                                    </form>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="reject_application">
                                                        <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                                        <button type="submit" class="btn-reject">&#10005; Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">No pending volunteer applications at this time.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Approved Tab -->
                <div class="vol-tab-panel" role="tabpanel" id="tab-approved" aria-labelledby="tab-btn-approved">
                    <div class="table-wrapper">
                        <table>
                            <caption class="visually-hidden">Approved volunteer applications</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Applied</th>
                                    <th scope="col">Assignment Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($approvedApps)): ?>
                                    <?php foreach ($approvedApps as $app):
                                        $appId        = (int) $app['application_id'];
                                        $activeStatus = $assignedAppMap[$appId] ?? null;
                                    ?>
                                        <tr>
                                            <td><strong><?= h($app['full_name']) ?></strong></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><?= h($app['role_name']) ?></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y', strtotime($app['applied_at']))) ?></td>
                                            <td>
                                                <?php if ($activeStatus === 'assigned'): ?>
                                                    <span class="status status-assigned">Assigned</span>
                                                <?php elseif ($activeStatus === 'completed'): ?>
                                                    <span class="status status-completed">Completed</span>
                                                <?php else: ?>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="assign_volunteer">
                                                        <input type="hidden" name="application_id" value="<?= $appId ?>">
                                                        <button type="submit" class="btn-assign">Assign Shift</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">No approved applications.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Rejected Tab -->
                <div class="vol-tab-panel" role="tabpanel" id="tab-rejected" aria-labelledby="tab-btn-rejected">
                    <div class="table-wrapper">
                        <table>
                            <caption class="visually-hidden">Rejected volunteer applications</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Applicant</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Applied</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rejectedApps)): ?>
                                    <?php foreach ($rejectedApps as $app): ?>
                                        <tr>
                                            <td><strong><?= h($app['full_name']) ?></strong></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><?= h($app['role_name']) ?></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y', strtotime($app['applied_at']))) ?></td>
                                            <td><span class="status status-rejected">Rejected</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">No rejected applications.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Opportunities Table -->
            <section class="dashboard-section" aria-labelledby="opportunities-table-heading">
                <div class="section-header">
                    <div>
                        <h2 id="opportunities-table-heading">Volunteer Opportunities</h2>
                        <p>All volunteer roles across events with required quota vs filled counts.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Volunteer opportunities across events</caption>
                        <thead>
                            <tr>
                                <th scope="col">Role</th>
                                <th scope="col">Event</th>
                                <th scope="col">Required</th>
                                <th scope="col">Assigned</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($opportunities)): ?>
                                <?php foreach ($opportunities as $opp):
                                    $req = (int) $opp['required_count'];
                                    $asn = (int) $opp['assigned_count'];
                                    $fillStatus = $asn >= $req ? 'Filled' : ($asn > 0 ? 'Partial' : 'Open');
                                    $fillClass  = $asn >= $req ? 'status-full' : ($asn > 0 ? 'status-pending' : 'status-open');
                                ?>
                                    <tr>
                                        <td><strong><?= h($opp['role_name']) ?></strong></td>
                                        <td><?= h($opp['event_title']) ?></td>
                                        <td><?= $req ?></td>
                                        <td><?= $asn ?></td>
                                        <td><span class="status <?= h($fillClass) ?>"><?= h($fillStatus) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;padding:2rem;color:#6b7280;">No volunteer opportunities found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Assignments Section -->
            <section class="dashboard-section" aria-labelledby="assignments-heading">
                <div class="section-header">
                    <div>
                        <h2 id="assignments-heading">Volunteer Assignments</h2>
                        <p>Track actively assigned volunteers and update their contribution status.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Volunteer assignments across events</caption>
                        <thead>
                            <tr>
                                <th scope="col">Volunteer</th>
                                <th scope="col">Role</th>
                                <th scope="col">Event</th>
                                <th scope="col">Assigned Date</th>
                                <th scope="col">Status</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($assignments)): ?>
                                <?php foreach ($assignments as $asn):
                                    $asnStatus = (string) $asn['asn_status'];
                                ?>
                                    <tr>
                                        <td><strong><?= h($asn['full_name']) ?></strong></td>
                                        <td><?= h($asn['role_name']) ?></td>
                                        <td><?= h($asn['event_title']) ?></td>
                                        <td><?= h(date('d M Y', strtotime($asn['assigned_at']))) ?></td>
                                        <td><span class="status status-<?= h($asnStatus) ?>"><?= h(ucfirst($asnStatus)) ?></span></td>
                                        <td>
                                            <?php if ($asnStatus === 'assigned'): ?>
                                                <div class="action-btn-group">
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="complete_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?= (int) $asn['assignment_id'] ?>">
                                                        <button type="submit" class="btn-complete">Mark Completed</button>
                                                    </form>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="cancel_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?= (int) $asn['assignment_id'] ?>">
                                                        <button type="submit" class="btn-cancel-assignment">Cancel Shift</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:#6b7280;">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;padding:2rem;color:#6b7280;">No volunteer assignments found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Create New Opportunity -->
            <section id="create-opportunity" class="dashboard-section" aria-labelledby="create-opp-heading">
                <div class="section-header">
                    <div>
                        <h2 id="create-opp-heading">Create Volunteer Opportunity</h2>
                        <p>Add a new volunteer role for a campus event.</p>
                    </div>
                </div>
                <form method="post" action="volunteer-management.php#create-opportunity" class="event-form">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="vol_action" value="create_opportunity">

                    <div class="form-group">
                        <label for="opp-event">Event</label>
                        <select id="opp-event" name="event_id" required>
                            <option value="">Select an event</option>
                            <?php foreach ($allActiveEvents as $ev): ?>
                                <option value="<?= (int) $ev['event_id'] ?>"><?= h($ev['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="opp-role">Role Name</label>
                        <input type="text" id="opp-role" name="role_name" placeholder="e.g. Registration Desk" required maxlength="100">
                    </div>
                    <div class="form-group form-group-full">
                        <label for="opp-description">Description</label>
                        <textarea id="opp-description" name="opp_description" rows="3" placeholder="Describe the volunteer responsibilities"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="opp-count">Required Volunteers</label>
                        <input type="number" id="opp-count" name="required_count" min="1" max="100" placeholder="e.g. 5" required>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">Create Opportunity</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <p>&copy; 2026 Campus Event &amp; Volunteer Management System</p>
    </footer>

    <script src="../public/js/main.js"></script>
    <script>
        /* Tab switching for application status tabs */
        const tabs = document.querySelectorAll('.vol-tab');
        const panels = document.querySelectorAll('.vol-tab-panel');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = tab.dataset.tab;

                tabs.forEach(function (t) {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                panels.forEach(function (p) {
                    p.classList.remove('active');
                });

                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                const panel = document.getElementById('tab-' + target);
                if (panel) {
                    panel.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
