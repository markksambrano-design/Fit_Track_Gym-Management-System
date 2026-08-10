CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

 CREATE TABLE IF NOT EXISTS members (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    membership_duration INT(11) DEFAULT NULL,
    join_date DATE NOT NULL,
    expired_date DATE DEFAULT NULL,
    address TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    age INT(3) DEFAULT NULL,
    with_trainees ENUM('with','without') DEFAULT NULL,
    qr_code_data VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    remember_token VARCHAR(64) DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    membership_type ENUM('regular','student','vip') NOT NULL,
    terms_agreed TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) NOT NULL UNIQUE,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL UNIQUE,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `hire_date` date NOT NULL,
  `address` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `schedule` enum('morning','afternoon') DEFAULT NULL,
  `qr_code_data` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `employment_type` enum('wholeday','half day','contract') DEFAULT 'wholeday',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS attendance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_archive (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    archive_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY date (date),
    KEY archive_date (archive_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create archived_attendance table (old table for backward compatibility)
CREATE TABLE IF NOT EXISTS archived_attendance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create staff_attendance table for staff attendance tracking
CREATE TABLE IF NOT EXISTS staff_attendance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    staff_id INT(11) NOT NULL,
    staff_code VARCHAR(50) NOT NULL,
    staff_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY staff_id (staff_id),
    KEY date (date),
    CONSTRAINT staff_attendance_ibfk_1 FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

 


-- Create member_payroll table for membership fees
CREATE TABLE IF NOT EXISTS member_payroll (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    membership_type ENUM('regular','student','vip') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    status ENUM('pending','paid','overdue') DEFAULT 'pending',
    payment_method ENUM('cash','gcash','bank_transfer','bank_deposit','credit_card','other') DEFAULT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY status (status),
    KEY payment_date (payment_date),
    KEY amount (amount),
    CONSTRAINT member_payroll_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create announcements table for gym announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    target_audience ENUM('all', 'members', 'staff', 'walk_in') DEFAULT 'all',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    views_count INT DEFAULT 0,
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_target_audience (target_audience),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_pinned (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create announcement_views table to track who has seen announcements
CREATE TABLE IF NOT EXISTS announcement_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    user_id INT NOT NULL,
    user_type ENUM('member', 'staff', 'admin', 'walk_in') NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_announcement_id (announcement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_user_type (user_type),
    INDEX idx_viewed_at (viewed_at),
    UNIQUE KEY unique_view (announcement_id, user_id, user_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create walk_in table for tracking walk-in customers
CREATE TABLE IF NOT EXISTS walk_in (
    id INT(11) NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    address TEXT DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    age INT(3) DEFAULT NULL,
    visit_date DATE NOT NULL,
    time_in DATETIME NOT NULL,
    purpose ENUM('gym_visit','consultation','trial','other') DEFAULT 'gym_visit',
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    payment_method ENUM('cash','gcash','bank_transfer','bank_deposit','credit_card','other') DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY visit_date (visit_date),
    KEY status (status),
    KEY created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create walk_in_archive table for historical walk-in data
CREATE TABLE IF NOT EXISTS walk_in_archive (
    id INT(11) NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    age INT(3) DEFAULT NULL,
    visit_date DATE NOT NULL,
    time_in DATETIME NOT NULL,
    time_out DATETIME DEFAULT NULL,
    purpose ENUM('gym_visit','consultation','trial','other') DEFAULT 'gym_visit',
    payment_amount DECIMAL(10,2) DEFAULT NULL,
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    payment_method ENUM('cash','gcash','bank_transfer','bank_deposit','credit_card','other') DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    archive_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY visit_date (visit_date),
    KEY archive_date (archive_date),
    KEY phone (phone),
    KEY payment_amount (payment_amount),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create walk_in_services table for tracking services used by walk-ins
CREATE TABLE IF NOT EXISTS walk_in_services (
    id INT(11) NOT NULL AUTO_INCREMENT,
    walk_in_id INT(11) NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    service_price DECIMAL(10,2) DEFAULT NULL,
    duration_minutes INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY walk_in_id (walk_in_id),
    CONSTRAINT walk_in_services_ibfk_1 FOREIGN KEY (walk_in_id) REFERENCES walk_in(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create separate member_attendance table for members only
CREATE TABLE IF NOT EXISTS member_attendance (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY date (date),
    KEY idx_member_date (member_id, date),
    CONSTRAINT member_attendance_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create member_attendance_archive table for historical member attendance data
CREATE TABLE IF NOT EXISTS member_attendance_archive (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_code VARCHAR(50) NOT NULL,
    member_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    archive_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY date (date),
    KEY archive_date (archive_date),
    KEY idx_member_date (member_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create staff_attendance_archive table for historical staff attendance data
CREATE TABLE IF NOT EXISTS staff_attendance_archive (
    id INT(11) NOT NULL AUTO_INCREMENT,
    staff_id INT(11) NOT NULL,
    staff_code VARCHAR(50) NOT NULL,
    staff_age INT(3) DEFAULT NULL,
    date DATE NOT NULL,
    time_in DATETIME DEFAULT NULL,
    time_out DATETIME DEFAULT NULL,
    archive_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY staff_id (staff_id),
    KEY date (date),
    KEY archive_date (archive_date),
    KEY idx_staff_date (staff_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create bmi_records table for BMI tracking
CREATE TABLE IF NOT EXISTS bmi_records (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    height DECIMAL(5,2) NOT NULL COMMENT 'Height in cm',
    weight DECIMAL(6,2) NOT NULL COMMENT 'Weight in kg',
    bmi DECIMAL(5,2) COMMENT 'BMI calculated value',
    recorded_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY recorded_date (recorded_date),
    CONSTRAINT bmi_records_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create trainers table for personal trainers
CREATE TABLE IF NOT EXISTS trainers (
    id INT(11) NOT NULL AUTO_INCREMENT,
    trainer_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    specialization VARCHAR(100),
    hourly_rate DECIMAL(8,2),
    certification VARCHAR(255),
    hire_date DATE NOT NULL,
    address TEXT DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    age INT(3) DEFAULT NULL,
    qr_code_data VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    remember_token VARCHAR(64) DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create training_sessions table for booked training sessions
CREATE TABLE IF NOT EXISTS training_sessions (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    trainer_id INT(11) NOT NULL,
    session_date DATE NOT NULL,
    session_time TIME NOT NULL,
    duration_minutes INT(11) DEFAULT 60,
    status ENUM('pending','completed','cancelled','no_show') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY trainer_id (trainer_id),
    KEY session_date (session_date),
    CONSTRAINT training_sessions_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT training_sessions_ibfk_2 FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create classes table for group fitness classes
CREATE TABLE IF NOT EXISTS classes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    class_name VARCHAR(100) NOT NULL,
    trainer_id INT(11),
    description TEXT,
    class_type ENUM('yoga','crossfit','cardio','strength','pilates','spinning','zumba','other') DEFAULT 'other',
    schedule_day ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday'),
    schedule_time TIME,
    capacity INT(11) DEFAULT 30,
    duration_minutes INT(11) DEFAULT 60,
    status ENUM('active','inactive','archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY trainer_id (trainer_id),
    KEY schedule_day (schedule_day),
    CONSTRAINT classes_ibfk_1 FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create member_classes table for member enrollment in classes
CREATE TABLE IF NOT EXISTS member_classes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    class_id INT(11) NOT NULL,
    enroll_date DATE NOT NULL,
    status ENUM('active','completed','dropped','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY class_id (class_id),
    KEY enroll_date (enroll_date),
    UNIQUE KEY unique_member_class (member_id, class_id),
    CONSTRAINT member_classes_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT member_classes_ibfk_2 FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create equipment table for gym equipment inventory
CREATE TABLE IF NOT EXISTS equipment (
    id INT(11) NOT NULL AUTO_INCREMENT,
    equipment_name VARCHAR(100) NOT NULL,
    category VARCHAR(50),
    quantity INT(11) DEFAULT 1,
    status ENUM('active','maintenance','damaged','retired') DEFAULT 'active',
    purchase_date DATE,
    equipment_condition ENUM('excellent','good','fair','poor') DEFAULT 'good',
    last_maintenance DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY category (category),
    KEY status (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create nutrition_plans table for member nutrition tracking
CREATE TABLE IF NOT EXISTS nutrition_plans (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    plan_type ENUM('weight_loss','muscle_gain','maintenance','custom') DEFAULT 'custom',
    description TEXT,
    created_by INT(11),
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('active','inactive','archived') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY status (status),
    CONSTRAINT nutrition_plans_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT nutrition_plans_ibfk_2 FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create payroll_history table for staff payroll tracking
CREATE TABLE IF NOT EXISTS payroll_history (
    id INT(11) NOT NULL AUTO_INCREMENT,
    staff_id INT(11) NOT NULL,
    staff_age INT(3) DEFAULT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    hours_worked DECIMAL(8,2) DEFAULT 0.00,
    hourly_rate DECIMAL(8,2) DEFAULT 62.50,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    payment_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY staff_id (staff_id),
    KEY period_start (period_start),
    KEY period_end (period_end),
    KEY status (status),
    KEY payment_date (payment_date),
    CONSTRAINT payroll_history_ibfk_1 FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create feedback table for member feedback system
CREATE TABLE IF NOT EXISTS feedback (
    id INT(11) NOT NULL AUTO_INCREMENT,
    member_id INT(11) NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT(1) DEFAULT 0 COMMENT 'Rating from 1-5 stars',
    category ENUM('gym_equipment', 'staff_service', 'membership', 'classes_training', 'safety_security', 'cleanliness', 'suggestion', 'complaint', 'other') NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL,
    status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
    admin_response TEXT NULL,
    admin_id INT(11) NULL,
    admin_name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    KEY member_id (member_id),
    KEY status (status),
    KEY priority (priority),
    KEY category (category),
    KEY created_at (created_at),
    KEY admin_id (admin_id),
    CONSTRAINT feedback_ibfk_1 FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT feedback_ibfk_2 FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores member feedback and admin responses';

-- =====================================================
-- AGE FIELDS UPDATE - COMPLETED
-- =====================================================
-- The following age fields have been added to support demographic tracking:
--
-- MAIN TABLES:
-- - members.age (INT(3)) - Member age
-- - staff.age (INT(3)) - Staff age
--
-- ATTENDANCE TABLES:
-- - attendance.member_age (INT(3)) - Member age at time of attendance
-- - attendance_archive.member_age (INT(3)) - Member age in archived records
-- - archived_attendance.member_age (INT(3)) - Member age in archived records
-- - member_attendance.member_age (INT(3)) - Member age in member attendance
-- - member_attendance_archive.member_age (INT(3)) - Member age in archived member attendance
-- - staff_attendance.staff_age (INT(3)) - Staff age at time of attendance
-- - staff_attendance_archive.staff_age (INT(3)) - Staff age in archived records
--
-- PAYROLL TABLES:
-- - member_payroll.member_age (INT(3)) - Member age for demographic payroll analysis
-- - payroll_history.staff_age (INT(3)) - Staff age for demographic payroll analysis
--
-- OTHER TABLES:
-- - feedback.member_age (INT(3)) - Member age for demographic feedback analysis
--
-- TABLES ALREADY HAVING AGE FIELDS:
-- - walk_in.age (INT(3)) - Walk-in customer age
-- - walk_in_archive.age (INT(3)) - Walk-in customer age in archived records
--
-- All age fields are INT(3) DEFAULT NULL to allow for optional age tracking
-- and are positioned after the gender field for logical grouping.
-- =====================================================

-- Create member_monthly_sessions table for tracking monthly training package requirements
CREATE TABLE IF NOT EXISTS member_monthly_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    package_sessions INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member_month (member_id, year, month),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member_month (member_id, year, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add training_package column to members table
-- ALTER TABLE members ADD COLUMN training_package INT(11) DEFAULT NULL AFTER with_trainees;

-- Create workout_plans table for pre-defined workout plans
CREATE TABLE IF NOT EXISTS workout_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_sessions INT NOT NULL,
    session_number INT NOT NULL,
    workout_name VARCHAR(255) NOT NULL,
    workout_description TEXT,
    exercises TEXT,
    duration_minutes INT DEFAULT 60,
    difficulty ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_package_session (package_sessions, session_number),
    INDEX idx_package (package_sessions)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create member_workout_sessions table for assigned workout sessions
CREATE TABLE IF NOT EXISTS member_workout_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    monthly_session_id INT,
    workout_plan_id INT,
    session_number INT NOT NULL,
    workout_name VARCHAR(255),
    exercises TEXT,
    status ENUM('pending','in_progress','completed','skipped') DEFAULT 'pending',
    completed_date DATE NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (monthly_session_id) REFERENCES member_monthly_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(id) ON DELETE SET NULL,
    INDEX idx_member_monthly (member_id, monthly_session_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- =====================================================
