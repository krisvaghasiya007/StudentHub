-- =====================================================
-- StudentHub database  (MySQL 5.7+ / MariaDB 10.3+)
-- Import:  mysql -u root -p < database/studenthub.sql
-- =====================================================
CREATE DATABASE IF NOT EXISTS studenthub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE studenthub;

DROP TABLE IF EXISTS remember_tokens;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS students;

CREATE TABLE students (
    student_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    roll_no       VARCHAR(15)  NOT NULL,
    full_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(100) NOT NULL,
    mobile        CHAR(10)     NOT NULL,
    course        VARCHAR(60)  NOT NULL,
    study_year    VARCHAR(10)  NOT NULL,
    gender        ENUM('Female','Male','Other') NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('student','admin') NOT NULL DEFAULT 'student',
    last_login    DATETIME NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_roll (roll_no),
    UNIQUE KEY uq_students_email (email),
    KEY idx_students_course (course),
    KEY idx_students_role (role)
) ENGINE=InnoDB;

CREATE TABLE events (
    event_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(100) NOT NULL,
    event_date  DATE NOT NULL,
    venue       VARCHAR(100) NOT NULL,
    category    VARCHAR(30)  NOT NULL,
    description TEXT NOT NULL,
    capacity    INT UNSIGNED NOT NULL DEFAULT 100,
    poster      VARCHAR(60) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_date (event_date),
    KEY idx_events_category (category)
) ENGINE=InnoDB;

-- students <-> events is many-to-many, resolved by registrations
CREATE TABLE registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED NOT NULL,
    event_id        INT UNSIGNED NOT NULL,
    status          ENUM('registered','cancelled') NOT NULL DEFAULT 'registered',
    registered_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_student_event (student_id, event_id),
    CONSTRAINT fk_reg_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_event   FOREIGN KEY (event_id)   REFERENCES events (event_id)     ON DELETE CASCADE
) ENGINE=InnoDB;

-- "Remember me": only a SHA-256 hash of the validator is stored
CREATE TABLE remember_tokens (
    token_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    selector   CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_selector (selector),
    CONSTRAINT fk_token_student FOREIGN KEY (student_id) REFERENCES students (student_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Sample data ----------
INSERT INTO events (title, event_date, venue, category, description, capacity) VALUES
('Hackathon 2026', '2026-11-05', 'Computer Lab', 'Technical', 'A 24-hour coding hackathon where teams build a working prototype around a surprise theme. Open to all departments; laptops and Wi-Fi provided.', 120),
('Coding Competition', '2026-11-12', 'Seminar Hall', 'Programming', 'A timed competitive-programming contest covering data structures, algorithms, and problem solving. Top 3 scorers receive certificates and prizes.', 100),
('Sports Day', '2026-11-20', 'College Ground', 'Sports', 'Annual inter-department sports meet featuring athletics, cricket, and kabaddi. Registration open for both individual and team events.', 300),
('Career Guidance Seminar', '2026-11-25', 'Auditorium', 'Seminar', 'Industry professionals share advice on resumes, interviews, and career paths in software and core engineering roles.', 250),
('Cultural Festival', '2026-11-30', 'Open Stage', 'Cultural', 'A day of music, dance, and drama performances by students, closing with a prize distribution for the best acts.', 500),
('Cybersecurity Workshop', '2026-12-04', 'Computer Lab', 'Technical', 'Hands-on session covering network scanning, common web vulnerabilities, and safe practices, led by the CSE faculty.', 60),
('Debate Competition', '2026-12-10', 'Seminar Hall', 'Cultural', 'Teams argue for and against current-affairs topics in front of a panel of judges from the languages department.', 80),
('Alumni Meet', '2026-12-18', 'Auditorium', 'Seminar', 'Graduates return to share their career journeys and network with current students over informal sessions.', 200);

-- Default admin  ->  email: admin@studenthub.local   password: Admin@123
-- CHANGE THIS PASSWORD after the first login (Admin > Students > Edit).
INSERT INTO students (roll_no, full_name, email, mobile, course, study_year, gender, password_hash, role) VALUES
('ADMIN001', 'Portal Administrator', 'admin@studenthub.local', '9000000000', 'Computer Science Engineering', '4th Year', 'Other',
 '$2y$12$SNySyeZSNg7G9usP4gOdK.KmHN6WvNZftaRF7iqhoZ7hDiBjnqoTq', 'admin');
