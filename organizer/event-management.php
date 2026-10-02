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

    <!-- Skip Link -->
    <a class="skip-link" href="#main-content">
        Skip to main content
    </a>
    <!-- Header_section -->
    <header class="site-header">
        <div class="header-container">
            <a href="../index.php" class="site-logo" aria-label="Campus Event Management System Home">
                CampusEvent
            </a>
            <button type="button" class="menu-toggle" aria-label="Open navigation menu" aria-expanded="false"
                aria-controls="organizer-navigation">
                ☰
            </button>
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
        <!-- Sidebar_nav-->
        <aside class="sidebar" id="organizer-navigation" aria-label="Organizer navigation">
            <nav>
                <h2 class="sidebar-title">
                    Organizer
                </h2>
                <ul class="sidebar-menu">
                    <li>
                        <a href="organizer-dashboard.php" class="nav-link">
                            <span aria-hidden="true">⌂</span>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="event-management.php" class="nav-link active" aria-current="page">
                            <span aria-hidden="true">▣</span>
                            <span>Manage Events</span>
                        </a>
                    </li>
                    <li>
                        <a href="#participants" class="nav-link">
                            <span aria-hidden="true">👥</span>
                            <span>Participants</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        <!-- Main_content -->
        <main id="main-content" class="main-content">
            <!-- Page_heading -->
            <section class="page-heading">
                <div>
                    <p class="breadcrumb">
                        Organizer / Event Management
                    </p>
                    <h1>
                        Event & Participant Management
                    </h1>
                    <p class="page-description">
                        Create, update and manage your campus events
                        and monitor participant registrations.
                    </p>
                </div>
                <a href="#create-event" class="btn btn-primary">
                    Create New Event
                </a>
            </section>
            <!-- Create/edit section-->
            <section id="create-event" class="dashboard-section" aria-labelledby="event-form-heading">
                <div class="section-header">
                    <div>
                        <h2 id="event-form-heading">
                            Create New Event
                        </h2>
                        <p>
                            Enter the details required to publish
                            a campus event.
                        </p>
                    </div>
                </div>
                <form action="#" method="post" class="event-form">
                    <!-- Event Title -->
                    <div class="form-group">
                        <label for="event-title">
                            Event Title
                        </label>
                        <input type="text" id="event-title" name="event_title" placeholder="Enter event title" required
                            maxlength="100">
                    </div>
                    <!-- Category -->
                    <div class="form-group">
                        <label for="event-category">
                            Category
                        </label>
                        <select id="event-category" name="event_category" required>
                            <option value="">
                                Select category
                            </option>
                            <option value="technical">
                                Technical
                            </option>
                            <option value="cultural">
                                Cultural
                            </option>
                            <option value="sports">
                                Sports
                            </option>
                            <option value="workshop">
                                Workshop
                            </option>
                            <option value="seminar">
                                Seminar
                            </option>
                        </select>
                    </div>
                    <!-- Description -->
                    <div class="form-group form-group-full">
                        <label for="event-description">
                            Event Description
                        </label>
                        <textarea id="event-description" name="event_description" rows="5"
                            placeholder="Describe the event" required></textarea>
                    </div>
                    <!-- Date -->
                    <div class="form-group">
                        <label for="event-date">
                            Event Date
                        </label>
                        <input type="date" id="event-date" name="event_date" required>
                    </div>
                    <!-- Start Time -->
                    <div class="form-group">
                        <label for="start-time">
                            Start Time
                        </label>
                        <input type="time" id="start-time" name="start_time" required>
                    </div>
                    <!-- End Time -->
                    <div class="form-group">
                        <label for="end-time">
                            End Time
                        </label>
                        <input type="time" id="end-time" name="end_time" required>
                    </div>
                    <!-- Venue -->
                    <div class="form-group">
                        <label for="venue">
                            Venue
                        </label>
                        <input type="text" id="venue" name="venue" placeholder="Enter venue" required maxlength="100">
                    </div>
                    <!-- Capacity -->
                    <div class="form-group">
                        <label for="capacity">
                            Participant Capacity
                        </label>
                        <input type="number" id="capacity" name="capacity" min="1" max="1000" placeholder="e.g. 100"
                            required>
                    </div>
                    <!-- Registration Deadline -->
                    <div class="form-group">
                        <label for="registration-deadline">
                            Registration Deadline
                        </label>
                        <input type="datetime-local" id="registration-deadline" name="registration_deadline" required>
                    </div>
                    <!-- Event Status -->
                    <div class="form-group">
                        <label for="event-status">
                            Event Status
                        </label>
                        <select id="event-status" name="event_status" required>
                            <option value="draft">
                                Draft
                            </option>

                            <option value="published">
                                Published
                            </option>

                            <option value="closed">
                                Closed
                            </option>

                            <option value="cancelled">
                                Cancelled
                            </option>
                        </select>
                    </div>
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">
                            Clear
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Save Event
                        </button>
                    </div>
                </form>
            </section>
            <!-- My_Events section -->

            <section class="dashboard-section" aria-labelledby="my-events-heading">
                <div class="section-header">
                    <div>
                        <h2 id="my-events-heading">
                            My Events
                        </h2>
                        <p>
                            Manage events created by you.
                        </p>
                    </div>
                </div>
                <!-- Search / Filter -->
                <div class="event-filters">
                    <div class="form-group">
                        <label for="event-search">
                            Search Events
                        </label>
                        <input type="search" id="event-search" name="event_search" placeholder="Search by event name">
                    </div>
                    <div class="form-group">
                        <label for="status-filter">
                            Filter by Status
                        </label>
                        <select id="status-filter" name="status_filter">
                            <option value="all">
                                All
                            </option>
                            <option value="draft">
                                Draft
                            </option>
                            <option value="published">
                                Published
                            </option>
                            <option value="closed">
                                Closed
                            </option>
                            <option value="cancelled">
                                Cancelled
                            </option>
                        </select>
                    </div>
                </div>
                <!-- Event Table -->
                <div class="table-wrapper">
                    <table>
                        <caption class="visually-hidden">
                            Events created by the organizer
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">
                                    Event
                                </th>
                                <th scope="col">
                                    Date
                                </th>
                                <th scope="col">
                                    Venue
                                </th>
                                <th scope="col">
                                    Capacity
                                </th>
                                <th scope="col">
                                    Status
                                </th>
                                <th scope="col">
                                    Actions
                                </th>
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
                                        Published
                                    </span>
                                </td>
                                <td>
                                    <a href="#edit-event" class="table-action">
                                        Edit
                                    </a>
                                    &nbsp;
                                    <a href="#participants" class="table-action">
                                        Participants
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
                                        Published
                                    </span>

                                </td>

                                <td>

                                    <a href="#edit-event" class="table-action">
                                        Edit
                                    </a>

                                    &nbsp;

                                    <a href="#participants" class="table-action">
                                        Participants
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

                                    <a href="#edit-event" class="table-action">
                                        Edit
                                    </a>

                                    &nbsp;

                                    <a href="#participants" class="table-action">
                                        Participants
                                    </a>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </section>

            <!-- Participants-->

            <section id="participants" class="dashboard-section" aria-labelledby="participants-heading">

                <div class="section-header">

                    <div>

                        <h2 id="participants-heading">
                            Participants
                        </h2>

                        <p>
                            Review registrations for your selected event.
                        </p>

                    </div>

                </div>

                <!-- Selected Event -->

                <div class="form-group">

                    <label for="participant-event">
                        Select Event
                    </label>

                    <select id="participant-event" name="participant_event">

                        <option value="">
                            Select an event
                        </option>

                        <option value="1">
                            Web Development Workshop
                        </option>

                        <option value="2">
                            Coding Competition
                        </option>

                        <option value="3">
                            Cultural Fest
                        </option>

                    </select>

                </div>

                <!-- Participant Table -->

                <div class="table-wrapper">

                    <table>

                        <caption class="visually-hidden">
                            Registered participants
                        </caption>

                        <thead>

                            <tr>

                                <th scope="col">
                                    Student Name
                                </th>

                                <th scope="col">
                                    Email
                                </th>

                                <th scope="col">
                                    Registration Date
                                </th>

                                <th scope="col">
                                    Status
                                </th>

                                <th scope="col">
                                    Action
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td>
                                    Rahul Kumar
                                </td>

                                <td>
                                    rahul@example.com
                                </td>

                                <td>
                                    01 Oct 2026
                                </td>

                                <td>

                                    <span class="status status-open">
                                        Registered
                                    </span>

                                </td>

                                <td>

                                    <button type="button" class="table-action">
                                        View
                                    </button>

                                </td>

                            </tr>

                            <tr>

                                <td>
                                    Ananya Rao
                                </td>

                                <td>
                                    ananya@example.com
                                </td>

                                <td>
                                    02 Oct 2026
                                </td>

                                <td>

                                    <span class="status status-open">
                                        Registered
                                    </span>

                                </td>

                                <td>

                                    <button type="button" class="table-action">
                                        View
                                    </button>

                                </td>

                            </tr>

                            <tr>

                                <td>
                                    Arjun Shetty
                                </td>

                                <td>
                                    arjun@example.com
                                </td>

                                <td>
                                    02 Oct 2026
                                </td>

                                <td>

                                    <span class="status status-full">
                                        Cancelled
                                    </span>

                                </td>

                                <td>

                                    <button type="button" class="table-action">
                                        View
                                    </button>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

    <!-- Footer_section -->

    <footer class="site-footer">

        <p>
            &copy; 2026 Campus Event & Volunteer Management System
        </p>

    </footer>

    <script src="../public/js/main.js"></script>

</body>
</html>