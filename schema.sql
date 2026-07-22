-- Run this in phpMyAdmin (Hostinger hPanel > Databases > phpMyAdmin)
-- after creating your database, to set up the tables.

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('note', 'blog', 'solution') NOT NULL DEFAULT 'blog',
    semester TINYINT NULL,
    subject VARCHAR(100) NULL,
    content LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Do NOT insert an admin here yet.
-- After uploading your files to Hostinger, visit make_hash.php in your browser
-- to generate a secure password hash, then come back and run an INSERT
-- statement here with that hash. Full steps are in the setup guide.

-- If you're upgrading an existing database instead of starting fresh,
-- run this instead of the CREATE TABLE above:
-- ALTER TABLE posts MODIFY COLUMN semester TINYINT NULL, MODIFY COLUMN subject VARCHAR(100) NULL;

-- ---- Quiz feature ----
-- Run this to add the quiz tables to an existing database.

CREATE TABLE quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

CREATE TABLE quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);
