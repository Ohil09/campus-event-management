<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Organizer dashboard for the Campus Event and Volunteer Management System">

    <title>Organizer Dashboard | Campus Event Management System</title>
    <link rel="stylesheet" href="../public/css/organizer.css">
</head>

<body>
    <!-- Skip Link -->
    <a class="skip-link" href="#main-content">
        Skip to main content
    </a>
    <header class="site-header">
        <div class="header-container">
            <!-- Logo -->
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">CampusEvent</a>
            <!-- Mobile navigation-->
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false"
                aria-controls="organizer-navigation">
                ☰
            </button>


            <!-- Account -->
            <div class="header-user">

                <span class="welcome-text">
                    Welcome, Organizer
                </span>

                <a href="../auth/logout.php" class="logout-link">
                    Logout
                </a>

            </div>

        </div>

    </header>

    <div class="page-layout">

        <!-- Sidebar_nav -->
        <aside class="sidebar" id="organizer-navigation" aria-label="Organizer navigation">
            <nav>
                <h2 class="sidebar-title">
                    Organizer
                </h2>
                <ul class="sidebar-menu">
                    <!-- Dashboard -->
                    <li>
                        <a href="organizer-dashboard.php" class="nav-link active" aria-current="page">
                            <span aria-hidden="true">⌂</span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <!-- Event Management -->
                    <li>
                        <a href="event-management.php" class="nav-link">
                            <span aria-hidden="true">▣</span>
                            <span>Manage Events</span>
                        </a>
                    </li>
                    <!-- Participants -->
                    <li>
                        <a href="event-management.php#participants" class="nav-link">
                            <span aria-hidden="true">👥</span>
                            <span>Participants</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        <!-- Main_Content -->
        <main id="main-content" class="main-content">
            <!-- Page_Header-->
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">Organizer</p>
                    <h1>Organizer Dashboard</h1>
                    <p class="page-description">
                        Manage your campus events, registrations and
                        participant activity from one place.
                    </p>
                </div>
                <!-- Primary action -->
                <a href="event-management.php#create-event" class="btn btn-primary">
                    Create New Event
                </a>
            </section>

            <!-- Event_Summary -->
            <section class="dashboard-stats" aria-labelledby="dashboard-summary-heading">
                <h2 id="dashboard-summary-heading" class="visually-hidden">
                    Event summary
                </h2>
                <!-- Total Events -->
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Total Events</h3>
                    </div>
                    <p class="stat-value">
                        12
                    </p>
                    <p class="stat-description">
                        Events created by you
                    </p>
                </article>
                <!-- Upcoming_Events -->
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Upcoming Events</h3>
                    </div>

                    <p class="stat-value">
                        5
                    </p>

                    <p class="stat-description">
                        Scheduled events
                    </p>
                </article>
                <!-- Registrations -->
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Registrations</h3>
                    </div>
                    <p class="stat-value">
                        148
                    </p>
                    <p class="stat-description">
                        Total participant registrations
                    </p>
                </article>
                <!-- Volunteer_Requirement -->
                <article class="stat-card">
                    <div class="stat-card-header">
                        <h3>Volunteers</h3>
                    </div>
                    <p class="stat-value">
                        24
                    </p>
                    <p class="stat-description">
                        Volunteer positions required
                    </p>
                </article>
            </section>
            <!-- Upcoming_Events-->
            <section class="dashboard-section" aria-labelledby="upcoming-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="upcoming-events-heading">
                            Upcoming Events
                        </h2>
                        <p>
                            Recently scheduled events managed by you.
                        </p>
                    </div>
                    <a href="event-management.php" class="text-link">
                        View All Events
                    </a>
                </div>
                <!-- Event table -->
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">
                            Upcoming events managed by the organizer
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Event</th>
                                <th scope="col">Date</th>
                                <th scope="col">Venue</th>
                                <th scope="col">Registrations</th>
                                <th scope="col">Status</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    Web Development Workshop
                                </td>
                                <td>
                                    15 Oct 2026
                                </td>
                                <td>
                                    Seminar Hall
                                </td>
                                <td>
                                    42 / 60
                                </td>
                                <td>
                                    <span class="status status-open">
                                        Open
                                    </span>
                                </td>
                                <td>
                                    <a href="event-management.php?event_id=1" class="table-action">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    Coding Competition
                                </td>
                                <td>
                                    18 Oct 2026
                                </td>
                                <td>
                                    Computer Lab
                                </td>
                                <td>
                                    30 / 40
                                </td>
                                <td>
                                    <span class="status status-open">
                                        Open
                                    </span>
                                </td>
                                <td>
                                    <a href="event-management.php?event_id=2" class="table-action">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    Cultural Fest
                                </td>
                                <td>
                                    22 Oct 2026
                                </td>
                                <td>
                                    Main Auditorium
                                </td>
                                <td>
                                    100 / 100
                                </td>
                                <td>
                                    <span class="status status-full">
                                        Full
                                    </span>
                                </td>
                                <td>
                                    <a href="event-management.php?event_id=3" class="table-action">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <!-- Quick_Actions -->
            <section class="dashboard-section" aria-labelledby="quick-actions-heading">
                <div class="section-header">
                    <div>
                        <h2 id="quick-actions-heading">
                            Quick Actions
                        </h2>

                        <p>
                            Common organizer tasks.
                        </p>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="event-management.php#create-event" class="action-card">
                        <h3>
                            Create Event
                        </h3>
                        <p>
                            Add a new campus event.
                        </p>
                    </a>
                    <a href="event-management.php#participants" class="action-card">
                        <h3>
                            View Participants
                        </h3>
                        <p>
                            Review event registrations.
                        </p>
                    </a>
                    <a href="../volunteer/volunteer-management.php" class="action-card">
                        <h3>
                            Manage Volunteers
                        </h3>
                        <p>
                            Review volunteer requirements.
                        </p>
                    </a>
                </div>
            </section>
            <!-- Recent_activity -->
            <section class="dashboard-section" aria-labelledby="recent-activity-heading">
                <div class="section-header">
                    <div>
                        <h2 id="recent-activity-heading">
                            Recent Activity
                        </h2>
                        <p>
                            Latest activity related to your events.
                        </p>
                    </div>
                </div>
                <ul class="activity-list">
                    <li class="activity-item">
                        <div>
                            <strong>
                                5 new registrations
                            </strong>

                            <p>
                                Web Development Workshop
                            </p>
                        </div>
                        <time datetime="2026-10-01T15:30">
                            2 hours ago
                        </time>
                    </li>
                    <li class="activity-item">
                        <div>
                            <strong>
                                Event updated
                            </strong>
                            <p>
                                Coding Competition venue was changed.
                            </p>
                        </div>
                        <time datetime="2026-10-01T12:15">
                            5 hours ago
                        </time>
                    </li>
                    <li class="activity-item">
                        <div>
                            <strong>
                                Volunteer applications received
                            </strong>
                            <p>
                                Cultural Fest
                            </p>
                        </div>
                        <time datetime="2026-09-30T17:45">
                            Yesterday
                        </time>
                    </li>
                </ul>
            </section>
        </main>
    </div>


    <!-- Footer_section -->
    <footer class="site-footer">
        <p>
            &copy; 2026 Campus Event & Volunteer Management System
        </p>
    </footer>
    <!-- JavaScript -->
    <script src="../public/js/main.js"></script>

</body>

</html>