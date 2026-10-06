-- =========================================================
-- Campus Event & Volunteer Management System
-- Sample / Test Data
-- =========================================================

USE event_management_db;


-- =========================================================
-- USERS
-- =========================================================

INSERT INTO users
    (user_id, full_name, email, password_hash, role, status)
VALUES
    (1, 'Rahul Sharma', 'rahul.organizer@example.com',
     'TEMP_HASH_REPLACE', 'organizer', 'active'),

    (2, 'Ananya Rao', 'ananya.organizer@example.com',
     'TEMP_HASH_REPLACE', 'organizer', 'active'),

    (3, 'Vivek Nair', 'vivek.organizer@example.com',
     'TEMP_HASH_REPLACE', 'organizer', 'active'),

    (4, 'Admin User', 'admin@example.com',
     'TEMP_HASH_REPLACE', 'administrator', 'active'),

    (5, 'Arjun Shetty', 'arjun@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active'),

    (6, 'Priya Menon', 'priya@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active'),

    (7, 'Karan Pai', 'karan@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active'),

    (8, 'Sneha Rao', 'sneha@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active'),

    (9, 'Aditya Kumar', 'aditya@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active'),

    (10, 'Meera Joshi', 'meera@example.com',
     'TEMP_HASH_REPLACE', 'student', 'active');


-- =========================================================
-- CATEGORIES
-- =========================================================

INSERT INTO categories
    (category_id, category_name, description, status)
VALUES
    (1, 'Technical',
     'Technical and technology-related events', 'active'),

    (2, 'Cultural',
     'Cultural and artistic events', 'active'),

    (3, 'Sports',
     'Sports and fitness events', 'active'),

    (4, 'Workshop',
     'Practical workshops and training sessions', 'active'),

    (5, 'Seminar',
     'Seminars, talks and academic sessions', 'active');


-- =========================================================
-- EVENTS
-- =========================================================

INSERT INTO events
    (
        event_id,
        organizer_id,
        category_id,
        title,
        description,
        start_datetime,
        end_datetime,
        venue,
        capacity,
        registration_deadline,
        status
    )
VALUES

    (
        1,
        1,
        4,
        'Web Development Workshop',
        'Hands-on workshop covering modern web development fundamentals.',
        '2026-10-15 10:00:00',
        '2026-10-15 13:00:00',
        'Seminar Hall',
        60,
        '2026-10-14 18:00:00',
        'published'
    ),

    (
        2,
        1,
        1,
        'Coding Competition',
        'Programming competition for students across departments.',
        '2026-10-18 10:00:00',
        '2026-10-18 14:00:00',
        'Computer Lab',
        40,
        '2026-10-17 18:00:00',
        'published'
    ),

    (
        3,
        2,
        2,
        'Annual Cultural Fest',
        'Campus cultural programme featuring music, dance and performances.',
        '2026-10-22 09:00:00',
        '2026-10-22 17:00:00',
        'Main Auditorium',
        100,
        '2026-10-20 18:00:00',
        'published'
    ),

    (
        4,
        2,
        5,
        'Career Development Seminar',
        'Seminar focused on career planning, employability and professional skills.',
        '2026-10-25 11:00:00',
        '2026-10-25 13:00:00',
        'Conference Hall',
        80,
        '2026-10-24 18:00:00',
        'published'
    ),

    (
        5,
        3,
        3,
        'Inter Department Football Tournament',
        'Football tournament involving student teams from different departments.',
        '2026-10-28 08:00:00',
        '2026-10-28 16:00:00',
        'College Ground',
        100,
        '2026-10-26 18:00:00',
        'published'
    ),

    (
        6,
        3,
        4,
        'Database Design Workshop',
        'Practical session on relational database design and SQL.',
        '2026-11-02 10:00:00',
        '2026-11-02 13:00:00',
        'Computer Lab 2',
        50,
        '2026-11-01 18:00:00',
        'draft'
    );


-- =========================================================
-- EVENT REGISTRATIONS
-- =========================================================

INSERT INTO event_registrations
    (registration_id, event_id, user_id, status)
VALUES
    (1, 1, 5, 'registered'),
    (2, 1, 6, 'registered'),
    (3, 1, 7, 'registered'),

    (4, 2, 5, 'registered'),
    (5, 2, 8, 'registered'),
    (6, 2, 9, 'registered'),

    (7, 3, 5, 'registered'),
    (8, 3, 6, 'registered'),
    (9, 3, 8, 'registered'),
    (10, 3, 10, 'registered'),

    (11, 4, 6, 'registered'),
    (12, 4, 9, 'registered'),

    (13, 5, 7, 'registered'),
    (14, 5, 8, 'registered');


-- =========================================================
-- VOLUNTEER OPPORTUNITIES
-- =========================================================

INSERT INTO volunteer_opportunities
    (
        opportunity_id,
        event_id,
        role_name,
        description,
        required_count
    )
VALUES
    (
        1,
        1,
        'Registration Desk',
        'Assist with participant registration and welcome desk.',
        3
    ),

    (
        2,
        1,
        'Technical Support',
        'Assist participants and speakers with technical requirements.',
        2
    ),

    (
        3,
        3,
        'Event Coordination',
        'Assist organizers during the cultural fest.',
        5
    ),

    (
        4,
        5,
        'Ground Coordination',
        'Assist with tournament coordination and team management.',
        4
    );


-- =========================================================
-- VOLUNTEER APPLICATIONS
-- =========================================================

INSERT INTO volunteer_applications
    (
        application_id,
        opportunity_id,
        user_id,
        status
    )
VALUES
    (1, 1, 5, 'approved'),
    (2, 1, 6, 'pending'),
    (3, 1, 7, 'approved'),

    (4, 2, 8, 'approved'),
    (5, 3, 9, 'approved'),
    (6, 3, 10, 'pending'),

    (7, 4, 7, 'approved');


-- =========================================================
-- VOLUNTEER ASSIGNMENTS
-- =========================================================

INSERT INTO volunteer_assignments
    (
        assignment_id,
        application_id,
        status
    )
VALUES
    (1, 1, 'assigned'),
    (2, 3, 'assigned'),
    (3, 4, 'assigned'),
    (4, 5, 'assigned'),
    (5, 7, 'assigned');


-- =========================================================
-- ATTENDANCE
-- =========================================================

INSERT INTO attendance
    (
        attendance_id,
        event_id,
        user_id,
        status
    )
VALUES
    (1, 1, 5, 'present'),
    (2, 1, 6, 'present'),
    (3, 1, 7, 'absent'),

    (4, 2, 5, 'present'),
    (5, 2, 8, 'present'),
    (6, 2, 9, 'absent');


-- =========================================================
-- FEEDBACK
-- =========================================================

INSERT INTO feedback
    (
        feedback_id,
        event_id,
        user_id,
        rating,
        comment
    )
VALUES
    (
        1,
        1,
        5,
        5,
        'Very useful and well organized workshop.'
    ),

    (
        2,
        1,
        6,
        4,
        'Good workshop with practical examples.'
    ),

    (
        3,
        2,
        5,
        4,
        'Interesting competition and good experience.'
    );


-- =========================================================
-- SAVE CHANGES
-- =========================================================

COMMIT;