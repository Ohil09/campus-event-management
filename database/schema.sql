-- =========================================================
-- Campus Event & Volunteer Management System
-- Database Schema
-- =========================================================

CREATE DATABASE IF NOT EXISTS event_management_db;

USE event_management_db;


-- =========================================================
-- USERS
-- Stores authentication and role information.
-- =========================================================

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    full_name VARCHAR(100) NOT NULL,

    email VARCHAR(150) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    role ENUM(
        'student',
        'organizer',
        'administrator'
    ) NOT NULL DEFAULT 'student',

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    category_name VARCHAR(50) NOT NULL UNIQUE,

    description VARCHAR(255),

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    organizer_id INT UNSIGNED NOT NULL,

    category_id INT UNSIGNED NOT NULL,

    title VARCHAR(150) NOT NULL,

    description TEXT NOT NULL,

    start_datetime DATETIME NOT NULL,

    end_datetime DATETIME NOT NULL,

    venue VARCHAR(150) NOT NULL,

    capacity INT UNSIGNED NOT NULL,

    registration_deadline DATETIME NOT NULL,

    status ENUM(
        'draft',
        'published',
        'closed',
        'cancelled'
    ) NOT NULL DEFAULT 'draft',

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_organizer
        FOREIGN KEY (organizer_id)
        REFERENCES users(user_id),

    CONSTRAINT fk_events_category
        FOREIGN KEY (category_id)
        REFERENCES categories(category_id),

    CONSTRAINT chk_event_capacity
        CHECK (capacity > 0),

    CONSTRAINT chk_event_time
        CHECK (end_datetime > start_datetime)
);


CREATE TABLE event_registrations (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    status ENUM(
        'registered',
        'cancelled'
    ) NOT NULL DEFAULT 'registered',

    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_registration_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id),

    CONSTRAINT fk_registration_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT uq_event_user
        UNIQUE (event_id, user_id)
);


CREATE TABLE volunteer_opportunities (
    opportunity_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id INT UNSIGNED NOT NULL,

    role_name VARCHAR(100) NOT NULL,

    description TEXT,

    required_count INT UNSIGNED NOT NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_opportunity_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id),

    CONSTRAINT chk_required_count
        CHECK (required_count > 0)
);


CREATE TABLE volunteer_applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    opportunity_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    status ENUM(
        'pending',
        'approved',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_application_opportunity
        FOREIGN KEY (opportunity_id)
        REFERENCES volunteer_opportunities(opportunity_id),

    CONSTRAINT fk_application_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT uq_opportunity_user
        UNIQUE (opportunity_id, user_id)
);

CREATE TABLE volunteer_assignments (
    assignment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    application_id INT UNSIGNED NOT NULL UNIQUE,

    status ENUM(
        'assigned',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'assigned',

    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_assignment_application
        FOREIGN KEY (application_id)
        REFERENCES volunteer_applications(application_id)
);

CREATE TABLE attendance (
    attendance_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    status ENUM(
        'present',
        'absent'
    ) NOT NULL DEFAULT 'present',

    marked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attendance_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id),

    CONSTRAINT fk_attendance_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT uq_attendance_event_user
        UNIQUE (event_id, user_id)
);


CREATE TABLE feedback (
    feedback_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    event_id INT UNSIGNED NOT NULL,

    user_id INT UNSIGNED NOT NULL,

    rating TINYINT UNSIGNED NOT NULL,

    comment TEXT,

    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_feedback_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id),

    CONSTRAINT fk_feedback_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id),

    CONSTRAINT uq_feedback_event_user
        UNIQUE (event_id, user_id),

    CONSTRAINT chk_feedback_rating
        CHECK (rating BETWEEN 1 AND 5)
);