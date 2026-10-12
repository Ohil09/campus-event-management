<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/auth_check.php';
require_role('organizer');
try {
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    $conn = null;
}

$organizerId   = (int) ($_SESSION['user_id'] ?? 0);
$organizerName = (string) ($_SESSION['full_name'] ?? 'Organizer');

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$notice    = '';
$formError = '';

if ($conn === null && !isset($_SESSION['mock_org'])) {
    $_SESSION['mock_org'] = [
        'events' => [
            ['event_id' => 1, 'title' => 'Web Development Workshop'],
            ['event_id' => 2, 'title' => 'Coding Competition']
        ],
        'opportunities' => [
            [
                'opportunity_id' => 1,
                'role_name' => 'Registration Desk',
                'opp_description' => 'Assist with participant registration and welcome desk.',
                'required_count' => 3,
                'event_id' => 1,
                'event_title' => 'Web Development Workshop',
                'assigned_count' => 2
            ],
            [
                'opportunity_id' => 2,
                'role_name' => 'Technical Support',
                'opp_description' => 'Assist participants and speakers with technical requirements.',
                'required_count' => 2,
                'event_id' => 1,
                'event_title' => 'Web Development Workshop',
                'assigned_count' => 0
            ]
        ],
        'applications' => [
            [
                'application_id' => 1,
                'opportunity_id' => 1,
                'user_id' => 5,
                'app_status' => 'approved',
                'applied_at' => '2026-10-10 11:20:00',
                'full_name' => 'Arjun Shetty',
                'email' => 'arjun@example.com',
                'role_name' => 'Registration Desk',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ],
            [
                'application_id' => 2,
                'opportunity_id' => 1,
                'user_id' => 6,
                'app_status' => 'pending',
                'applied_at' => '2026-10-11 09:15:00',
                'full_name' => 'Priya Menon',
                'email' => 'priya@example.com',
                'role_name' => 'Registration Desk',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ],
            [
                'application_id' => 3,
                'opportunity_id' => 1,
                'user_id' => 7,
                'app_status' => 'approved',
                'applied_at' => '2026-10-11 14:40:00',
                'full_name' => 'Karan Pai',
                'email' => 'karan@example.com',
                'role_name' => 'Registration Desk',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ],
            [
                'application_id' => 4,
                'opportunity_id' => 2,
                'user_id' => 8,
                'app_status' => 'pending',
                'applied_at' => '2026-10-12 10:00:00',
                'full_name' => 'Sneha Kulkarni',
                'email' => 'sneha@example.com',
                'role_name' => 'Technical Support',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ],
            [
                'application_id' => 5,
                'opportunity_id' => 2,
                'user_id' => 9,
                'app_status' => 'rejected',
                'applied_at' => '2026-10-11 16:00:00',
                'full_name' => 'Amit Roy',
                'email' => 'amit@example.com',
                'role_name' => 'Technical Support',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ]
        ],
        'assignments' => [
            [
                'assignment_id' => 1,
                'application_id' => 1,
                'asn_status' => 'assigned',
                'assigned_at' => '2026-10-11 10:00:00',
                'opportunity_id' => 1,
                'user_id' => 5,
                'full_name' => 'Arjun Shetty',
                'email' => 'arjun@example.com',
                'role_name' => 'Registration Desk',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ],
            [
                'assignment_id' => 2,
                'application_id' => 3,
                'asn_status' => 'completed',
                'assigned_at' => '2026-10-11 16:30:00',
                'opportunity_id' => 1,
                'user_id' => 7,
                'full_name' => 'Karan Pai',
                'email' => 'karan@example.com',
                'role_name' => 'Registration Desk',
                'event_id' => 1,
                'event_title' => 'Web Development Workshop'
            ]
        ]
    ];
}

/* ---------------------------------------------------------
   Handle POST actions:
   - approve / reject application
   - assign volunteer (create assignment from approved app)
   - complete / cancel assignment
   - create new opportunity
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = trim((string) ($_POST['csrf_token'] ?? ''));
    $action = trim((string) ($_POST['vol_action'] ?? ''));

    if (!hash_equals((string) $_SESSION['csrf_token'], $postedToken)) {
        $formError = 'Session expired. Please refresh and try again.';
    } elseif ($conn !== null) {
        try {
            switch ($action) {

                /* ---- Approve / Reject Application ---- */
                case 'approve_application':
                case 'reject_application':
                    $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($applicationId === false || $applicationId < 1) {
                        $formError = 'Invalid application.';
                        break;
                    }
                    // Verify the application belongs to an opportunity under organizer's event
                    $verifyStmt = $conn->prepare(
                        'SELECT va.application_id, va.status
                         FROM volunteer_applications va
                         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                         INNER JOIN events e ON vo.event_id = e.event_id
                         WHERE va.application_id = ? AND e.organizer_id = ?'
                    );
                    $verifyStmt->bind_param('ii', $applicationId, $organizerId);
                    $verifyStmt->execute();
                    $appRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($appRow === null) {
                        $formError = 'Application not found or access denied.';
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
                        $notice = 'Application ' . $newStatus . ' successfully.';
                    }
                    break;

                /* ---- Assign Volunteer ---- */
                case 'assign_volunteer':
                    $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($applicationId === false || $applicationId < 1) {
                        $formError = 'Invalid application.';
                        break;
                    }
                    // Verify ownership and that app is approved
                    $verifyStmt = $conn->prepare(
                        'SELECT va.application_id, va.status
                         FROM volunteer_applications va
                         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                         INNER JOIN events e ON vo.event_id = e.event_id
                         WHERE va.application_id = ? AND e.organizer_id = ? AND va.status = \'approved\''
                    );
                    $verifyStmt->bind_param('ii', $applicationId, $organizerId);
                    $verifyStmt->execute();
                    $appRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($appRow === null) {
                        $formError = 'Application not found, not approved, or access denied.';
                    } else {
                        // Check if already assigned
                        $dupStmt = $conn->prepare(
                            'SELECT assignment_id FROM volunteer_assignments WHERE application_id = ?'
                        );
                        $dupStmt->bind_param('i', $applicationId);
                        $dupStmt->execute();
                        $dupStmt->store_result();
                        if ($dupStmt->num_rows > 0) {
                            $formError = 'This volunteer is already assigned.';
                        } else {
                            $assignStmt = $conn->prepare(
                                'INSERT INTO volunteer_assignments (application_id, status) VALUES (?, \'assigned\')'
                            );
                            $assignStmt->bind_param('i', $applicationId);
                            $assignStmt->execute();
                            $assignStmt->close();
                            $notice = 'Volunteer assigned successfully.';
                        }
                        $dupStmt->close();
                    }
                    break;

                /* ---- Complete / Cancel Assignment ---- */
                case 'complete_assignment':
                case 'cancel_assignment':
                    $assignmentId = filter_var($_POST['assignment_id'] ?? 0, FILTER_VALIDATE_INT);
                    if ($assignmentId === false || $assignmentId < 1) {
                        $formError = 'Invalid assignment.';
                        break;
                    }
                    $verifyStmt = $conn->prepare(
                        'SELECT vasn.assignment_id, vasn.status
                         FROM volunteer_assignments vasn
                         INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
                         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
                         INNER JOIN events e ON vo.event_id = e.event_id
                         WHERE vasn.assignment_id = ? AND e.organizer_id = ?'
                    );
                    $verifyStmt->bind_param('ii', $assignmentId, $organizerId);
                    $verifyStmt->execute();
                    $asnRow = $verifyStmt->get_result()->fetch_assoc();
                    $verifyStmt->close();

                    if ($asnRow === null) {
                        $formError = 'Assignment not found or access denied.';
                    } else {
                        $newStatus = ($action === 'complete_assignment') ? 'completed' : 'cancelled';
                        $updateStmt = $conn->prepare(
                            'UPDATE volunteer_assignments SET status = ? WHERE assignment_id = ?'
                        );
                        $updateStmt->bind_param('si', $newStatus, $assignmentId);
                        $updateStmt->execute();
                        $updateStmt->close();
                        $notice = 'Assignment marked as ' . $newStatus . '.';
                    }
                    break;

                /* ---- Create New Opportunity ---- */
                case 'create_opportunity':
                    $eventId       = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT);
                    $roleName      = trim((string) ($_POST['role_name'] ?? ''));
                    $description   = trim((string) ($_POST['opp_description'] ?? ''));
                    $requiredCount = filter_var($_POST['required_count'] ?? 0, FILTER_VALIDATE_INT);

                    if ($eventId === false || $eventId < 1) {
                        $formError = 'Please select an event.';
                    } elseif ($roleName === '' || mb_strlen($roleName) > 100) {
                        $formError = 'Enter a role name (max 100 characters).';
                    } elseif ($requiredCount === false || $requiredCount < 1 || $requiredCount > 100) {
                        $formError = 'Required count must be between 1 and 100.';
                    } else {
                        // Verify event belongs to organizer
                        $ownerStmt = $conn->prepare(
                            'SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?'
                        );
                        $ownerStmt->bind_param('ii', $eventId, $organizerId);
                        $ownerStmt->execute();
                        $ownerStmt->store_result();
                        if ($ownerStmt->num_rows === 0) {
                            $formError = 'Event not found or access denied.';
                        } else {
                            $insertStmt = $conn->prepare(
                                'INSERT INTO volunteer_opportunities (event_id, role_name, description, required_count)
                                 VALUES (?, ?, ?, ?)'
                            );
                            $insertStmt->bind_param('issi', $eventId, $roleName, $description, $requiredCount);
                            $insertStmt->execute();
                            $insertStmt->close();
                            $notice = 'Volunteer opportunity created successfully.';
                        }
                        $ownerStmt->close();
                    }
                    break;

                default:
                    $formError = 'Unknown action.';
            }
        } catch (Throwable $e) {
            error_log('Volunteer management error: ' . $e->getMessage());
            $formError = 'An error occurred. Please try again.';
        }
    } else {
        // Offline demo mode handling
        switch ($action) {
            case 'approve_application':
            case 'reject_application':
                $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                $newStatus = ($action === 'approve_application') ? 'approved' : 'rejected';
                $found = false;
                foreach ($_SESSION['mock_org']['applications'] as &$app) {
                    if ($app['application_id'] === $applicationId) {
                        $app['app_status'] = $newStatus;
                        $found = true;
                        break;
                    }
                }
                unset($app);
                if ($found) {
                    $notice = 'Application ' . $newStatus . ' successfully.';
                } else {
                    $formError = 'Application not found.';
                }
                break;

            case 'assign_volunteer':
                $applicationId = filter_var($_POST['application_id'] ?? 0, FILTER_VALIDATE_INT);
                $foundApp = null;
                foreach ($_SESSION['mock_org']['applications'] as $app) {
                    if ($app['application_id'] === $applicationId) {
                        $foundApp = $app;
                        break;
                    }
                }
                if (!$foundApp || $foundApp['app_status'] !== 'approved') {
                    $formError = 'Application not found or not approved.';
                } else {
                    $alreadyAsn = false;
                    foreach ($_SESSION['mock_org']['assignments'] as $asn) {
                        if ($asn['application_id'] === $applicationId) {
                            $alreadyAsn = true;
                            break;
                        }
                    }
                    if ($alreadyAsn) {
                        $formError = 'This volunteer is already assigned.';
                    } else {
                        $_SESSION['mock_org']['assignments'][] = [
                            'assignment_id' => time(),
                            'application_id' => $applicationId,
                            'asn_status' => 'assigned',
                            'assigned_at' => date('Y-m-d H:i:s'),
                            'opportunity_id' => $foundApp['opportunity_id'],
                            'user_id' => $foundApp['user_id'],
                            'full_name' => $foundApp['full_name'],
                            'email' => $foundApp['email'],
                            'role_name' => $foundApp['role_name'],
                            'event_id' => $foundApp['event_id'],
                            'event_title' => $foundApp['event_title']
                        ];
                        foreach ($_SESSION['mock_org']['opportunities'] as &$opp) {
                            if ($opp['opportunity_id'] === $foundApp['opportunity_id']) {
                                $opp['assigned_count']++;
                                break;
                            }
                        }
                        unset($opp);
                        $notice = 'Volunteer assigned successfully.';
                    }
                }
                break;

            case 'complete_assignment':
            case 'cancel_assignment':
                $assignmentId = filter_var($_POST['assignment_id'] ?? 0, FILTER_VALIDATE_INT);
                $newStatus = ($action === 'complete_assignment') ? 'completed' : 'cancelled';
                $found = false;
                foreach ($_SESSION['mock_org']['assignments'] as &$asn) {
                    if ($asn['assignment_id'] === $assignmentId) {
                        $asn['asn_status'] = $newStatus;
                        $found = true;
                        break;
                    }
                }
                unset($asn);
                if ($found) {
                    $notice = 'Assignment marked as ' . $newStatus . '.';
                } else {
                    $formError = 'Assignment not found.';
                }
                break;

            case 'create_opportunity':
                $eventId = filter_var($_POST['event_id'] ?? 0, FILTER_VALIDATE_INT);
                $roleName = trim((string) ($_POST['role_name'] ?? ''));
                $description = trim((string) ($_POST['opp_description'] ?? ''));
                $requiredCount = filter_var($_POST['required_count'] ?? 0, FILTER_VALIDATE_INT);
                if ($eventId < 1 || $roleName === '' || $requiredCount < 1) {
                    $formError = 'Please fill out all required fields.';
                } else {
                    $eventTitle = 'Web Development Workshop';
                    foreach ($_SESSION['mock_org']['events'] as $e) {
                        if ($e['event_id'] === $eventId) { $eventTitle = $e['title']; break; }
                    }
                    $_SESSION['mock_org']['opportunities'][] = [
                        'opportunity_id' => time(),
                        'role_name' => $roleName,
                        'opp_description' => $description,
                        'required_count' => $requiredCount,
                        'event_id' => $eventId,
                        'event_title' => $eventTitle,
                        'assigned_count' => 0
                    ];
                    $notice = 'Volunteer opportunity created successfully.';
                }
                break;
        }
    }
}

/* ---------------------------------------------------------
   Fetch data for display
--------------------------------------------------------- */
$organizerEvents    = [];
$opportunities      = [];
$applications       = [];
$assignments        = [];
$summaryStats       = ['total_opportunities' => 0, 'pending_apps' => 0, 'approved_apps' => 0, 'total_assigned' => 0, 'completed' => 0];

if ($conn !== null) {
    // Organizer's events (for the create opportunity form)
    $evStmt = $conn->prepare(
        'SELECT event_id, title FROM events WHERE organizer_id = ? ORDER BY start_datetime DESC'
    );
    $evStmt->bind_param('i', $organizerId);
    $evStmt->execute();
    $organizerEvents = $evStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $evStmt->close();

    // All opportunities for organizer's events
    $oppStmt = $conn->prepare(
        "SELECT vo.opportunity_id, vo.role_name, vo.description AS opp_description,
                vo.required_count, vo.event_id,
                e.title AS event_title,
                (SELECT COUNT(*) FROM volunteer_assignments vsub
                 INNER JOIN volunteer_applications vasub ON vsub.application_id = vasub.application_id
                 WHERE vasub.opportunity_id = vo.opportunity_id AND vsub.status IN ('assigned','completed')) AS assigned_count
         FROM volunteer_opportunities vo
         INNER JOIN events e ON vo.event_id = e.event_id
         WHERE e.organizer_id = ?
         ORDER BY e.start_datetime DESC, vo.role_name ASC"
    );
    $oppStmt->bind_param('i', $organizerId);
    $oppStmt->execute();
    $opportunities = $oppStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $oppStmt->close();

    // All applications for organizer's events
    $appStmt = $conn->prepare(
        'SELECT va.application_id, va.opportunity_id, va.user_id, va.status AS app_status, va.applied_at,
                u.full_name, u.email,
                vo.role_name, vo.event_id,
                e.title AS event_title
         FROM volunteer_applications va
         INNER JOIN users u ON va.user_id = u.user_id
         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
         INNER JOIN events e ON vo.event_id = e.event_id
         WHERE e.organizer_id = ?
         ORDER BY va.applied_at DESC'
    );
    $appStmt->bind_param('i', $organizerId);
    $appStmt->execute();
    $applications = $appStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $appStmt->close();

    // All assignments for organizer's events
    $asnStmt = $conn->prepare(
        'SELECT vasn.assignment_id, vasn.application_id, vasn.status AS asn_status, vasn.assigned_at,
                va.opportunity_id, va.user_id,
                u.full_name, u.email,
                vo.role_name, vo.event_id,
                e.title AS event_title
         FROM volunteer_assignments vasn
         INNER JOIN volunteer_applications va ON vasn.application_id = va.application_id
         INNER JOIN users u ON va.user_id = u.user_id
         INNER JOIN volunteer_opportunities vo ON va.opportunity_id = vo.opportunity_id
         INNER JOIN events e ON vo.event_id = e.event_id
         WHERE e.organizer_id = ?
         ORDER BY vasn.assigned_at DESC'
    );
    $asnStmt->bind_param('i', $organizerId);
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
} else {
    // Database offline demo mode data
    $organizerEvents = $_SESSION['mock_org']['events'];
    $opportunities   = $_SESSION['mock_org']['opportunities'];
    $applications    = $_SESSION['mock_org']['applications'];
    $assignments     = $_SESSION['mock_org']['assignments'];

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

// Build lookup: which application_ids already have assignments
$assignedAppIds = [];
foreach ($assignments as $asn) {
    $assignedAppIds[(int) $asn['application_id']] = true;
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
    <meta name="description" content="Manage volunteer applications and assignments for your campus events">
    <title>Volunteer Management | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/organizer.css">
    <link rel="stylesheet" href="../public/css/volunteer.css">
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
                    <li><a href="../organizer/organizer-dashboard.php" class="nav-link"><span aria-hidden="true">⌂</span><span>Dashboard</span></a></li>
                    <li><a href="../organizer/event-management.php" class="nav-link"><span aria-hidden="true">▣</span><span>Manage Events</span></a></li>
                    <li><a href="volunteer-management.php" class="nav-link active" aria-current="page"><span aria-hidden="true">🤝</span><span>Volunteers</span></a></li>
                </ul>
            </nav>
        </aside>

        <main id="main-content" class="main-content">
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Organizer / Volunteer Management</p>
                    <h1>Volunteer Management</h1>
                    <p class="page-description">Review volunteer applications, approve or reject them, assign volunteers, and track contributions.</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('create-opportunity').scrollIntoView({behavior:'smooth'})">Create Opportunity</button>
            </section>

            <?php if ($notice !== ''): ?>
                <p role="status" class="form-message"><?= h($notice) ?></p>
            <?php endif; ?>
            <?php if ($formError !== ''): ?>
                <p role="alert" class="form-message"><?= h($formError) ?></p>
            <?php endif; ?>

            <!-- Summary Stats -->
            <div class="vol-summary-cards">
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['total_opportunities'] ?></p>
                    <p class="stat-description">Total Opportunities</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['pending_apps'] ?></p>
                    <p class="stat-description">Pending Applications</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['total_assigned'] ?></p>
                    <p class="stat-description">Assigned Volunteers</p>
                </article>
                <article class="vol-summary-card">
                    <p class="stat-value"><?= $summaryStats['completed'] ?></p>
                    <p class="stat-description">Completed</p>
                </article>
            </div>

            <!-- Opportunities Table -->
            <section class="dashboard-section" aria-labelledby="opportunities-table-heading">
                <div class="section-header">
                    <div>
                        <h2 id="opportunities-table-heading">Volunteer Opportunities</h2>
                        <p>All volunteer roles across your events, with required vs assigned counts.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Volunteer opportunities for organizer's events</caption>
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
                                        <td><?= h($opp['role_name']) ?></td>
                                        <td><?= h($opp['event_title']) ?></td>
                                        <td><?= $req ?></td>
                                        <td><?= $asn ?></td>
                                        <td><span class="status <?= h($fillClass) ?>"><?= h($fillStatus) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No volunteer opportunities created yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Applications Section with Tabs -->
            <section class="dashboard-section" aria-labelledby="applications-heading">
                <div class="section-header">
                    <div>
                        <h2 id="applications-heading">Volunteer Applications</h2>
                        <p>Review, approve, or reject volunteer applications.</p>
                    </div>
                </div>

                <div class="vol-tabs" role="tablist" aria-label="Application status tabs">
                    <button class="vol-tab active" role="tab" aria-selected="true" aria-controls="tab-pending" id="tab-btn-pending" data-tab="pending">
                        Pending <span class="count-badge"><?= count($pendingApps) ?></span>
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
                                    <th scope="col">Applied</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pendingApps)): ?>
                                    <?php foreach ($pendingApps as $app): ?>
                                        <tr>
                                            <td><?= h($app['full_name']) ?></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><?= h($app['role_name']) ?></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y', strtotime($app['applied_at']))) ?></td>
                                            <td>
                                                <div class="action-btn-group">
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="approve_application">
                                                        <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                                        <button type="submit" class="btn-approve">Approve</button>
                                                    </form>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="reject_application">
                                                        <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                                        <button type="submit" class="btn-reject">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">No pending applications.</td></tr>
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
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($approvedApps)): ?>
                                    <?php foreach ($approvedApps as $app):
                                        $isAssigned = isset($assignedAppIds[(int) $app['application_id']]);
                                    ?>
                                        <tr>
                                            <td><?= h($app['full_name']) ?></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><?= h($app['role_name']) ?></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y', strtotime($app['applied_at']))) ?></td>
                                            <td>
                                                <?php if ($isAssigned): ?>
                                                    <span class="status status-assigned">Assigned</span>
                                                <?php else: ?>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="assign_volunteer">
                                                        <input type="hidden" name="application_id" value="<?= (int) $app['application_id'] ?>">
                                                        <button type="submit" class="btn-assign">Assign</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">No approved applications.</td></tr>
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
                                            <td><?= h($app['full_name']) ?></td>
                                            <td><?= h($app['email']) ?></td>
                                            <td><?= h($app['role_name']) ?></td>
                                            <td><?= h($app['event_title']) ?></td>
                                            <td><?= h(date('d M Y', strtotime($app['applied_at']))) ?></td>
                                            <td><span class="status status-rejected">Rejected</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">No rejected applications.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Assignments Section -->
            <section class="dashboard-section" aria-labelledby="assignments-heading">
                <div class="section-header">
                    <div>
                        <h2 id="assignments-heading">Volunteer Assignments</h2>
                        <p>Track assigned volunteers and update their contribution status.</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">Volunteer assignments for organizer's events</caption>
                        <thead>
                            <tr>
                                <th scope="col">Volunteer</th>
                                <th scope="col">Role</th>
                                <th scope="col">Event</th>
                                <th scope="col">Assigned</th>
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
                                        <td><?= h($asn['full_name']) ?></td>
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
                                                        <button type="submit" class="btn-complete">Complete</button>
                                                    </form>
                                                    <form method="post" style="margin:0;display:inline">
                                                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                                        <input type="hidden" name="vol_action" value="cancel_assignment">
                                                        <input type="hidden" name="assignment_id" value="<?= (int) $asn['assignment_id'] ?>">
                                                        <button type="submit" class="btn-cancel-assignment">Cancel</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span>—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6">No volunteer assignments yet.</td></tr>
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
                        <p>Add a new volunteer role for one of your events.</p>
                    </div>
                </div>
                <form method="post" action="volunteer-management.php#create-opportunity" class="event-form">
                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="vol_action" value="create_opportunity">

                    <div class="form-group">
                        <label for="opp-event">Event</label>
                        <select id="opp-event" name="event_id" required>
                            <option value="">Select an event</option>
                            <?php foreach ($organizerEvents as $ev): ?>
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
                document.getElementById('tab-' + target).classList.add('active');
            });
        });
    </script>
</body>
</html>
