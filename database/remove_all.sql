-- =========================================================
-- Campus Event & Volunteer Management System
-- Remove all data except the administrator account
-- Execute this entire file, not an individual selected statement.
-- =========================================================

USE event_management_db;

START TRANSACTION;

SET FOREIGN_KEY_CHECKS = 0;

-- Remove the obsolete table if it still exists in an older database.
DROP TABLE IF EXISTS attendance;

DELETE FROM feedback;
DELETE FROM volunteer_assignments;
DELETE FROM volunteer_applications;
DELETE FROM volunteer_opportunities;
DELETE FROM event_registrations;
DELETE FROM events;
DELETE FROM categories;
DELETE FROM users;

ALTER TABLE feedback AUTO_INCREMENT = 1;
ALTER TABLE volunteer_assignments AUTO_INCREMENT = 1;
ALTER TABLE volunteer_applications AUTO_INCREMENT = 1;
ALTER TABLE volunteer_opportunities AUTO_INCREMENT = 1;
ALTER TABLE event_registrations AUTO_INCREMENT = 1;
ALTER TABLE events AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

INSERT INTO users
    (user_id, full_name, email, password_hash, role, status, created_at, updated_at)
VALUES
    (
        1,
        'campus_admin',
        'campus.admin@gmail.com',
        '$2y$10$frpc1vyYmT9SeGc2mqQ8yOImopsTIXJzTXZ0wn4Z36F31vcrQGNb2',
        'administrator',
        'active',
        CURRENT_TIMESTAMP,
        CURRENT_TIMESTAMP
    );

SET FOREIGN_KEY_CHECKS = 1;

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
     
COMMIT;
