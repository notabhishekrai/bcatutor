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
    type ENUM('note', 'blog', 'solution', 'question_paper') NOT NULL DEFAULT 'blog',
    semester TINYINT NULL,
    subject VARCHAR(100) NULL,
    content LONGTEXT NOT NULL,
    created_by INT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    meta_description VARCHAR(160) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Do NOT insert an admin here yet.
-- After uploading your files to Hostinger, visit make_hash.php in your browser
-- to generate a secure password hash, then come back and run an INSERT
-- statement here with that hash. Full steps are in the setup guide.

-- If you're upgrading an existing database instead of starting fresh,
-- run this instead of the CREATE TABLE above:
-- ALTER TABLE posts MODIFY COLUMN semester TINYINT NULL, MODIFY COLUMN subject VARCHAR(100) NULL;
-- ALTER TABLE posts MODIFY COLUMN type ENUM('note', 'blog', 'solution', 'question_paper') NOT NULL DEFAULT 'blog';
-- ALTER TABLE posts ADD COLUMN created_by INT NULL, ADD COLUMN sort_order INT NOT NULL DEFAULT 0, ADD COLUMN meta_description VARCHAR(160) NULL;
-- ALTER TABLE posts ADD FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL;

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
    question_image VARCHAR(255) NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option ENUM('a', 'b', 'c', 'd') NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- If you already ran the quiz_questions CREATE TABLE above before this column
-- existed, run this instead to add image support to an existing database:
-- ALTER TABLE quiz_questions ADD COLUMN question_image VARCHAR(255) NULL AFTER question_text;

-- ---- Quiz leaderboard (no login required) ----
-- Visitors are identified by a random ID stored in a browser cookie (see
-- getPlayerId() in config.php), not an account. These are brand new tables —
-- on an existing database just run both CREATE TABLE statements below.

CREATE TABLE players (
    player_id CHAR(32) PRIMARY KEY,
    nickname VARCHAR(40) NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE quiz_leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    player_id CHAR(32) NOT NULL,
    score INT NOT NULL,
    total INT NOT NULL,
    percent DECIMAL(5,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY quiz_player (quiz_id, player_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(player_id) ON DELETE CASCADE
);
