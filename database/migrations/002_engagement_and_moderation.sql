-- Version 2: login history, progression goals, learning content and content reports.

CREATE TABLE login_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    event VARCHAR(20) NOT NULL DEFAULT 'login',
    ip_masked VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_login_history_user (user_id, created_at),
    CONSTRAINT fk_login_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_goals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    goal_type ENUM('average_score','sessions_completed','skill_score') NOT NULL,
    target_value SMALLINT UNSIGNED NOT NULL,
    skill_id SMALLINT UNSIGNED NULL,
    deadline DATE NULL,
    achieved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_goals_user (user_id),
    CONSTRAINT fk_goals_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_goals_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learning_contents (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    content_type ENUM('article','tip','checklist') NOT NULL DEFAULT 'article',
    question_type VARCHAR(30) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_learning_slug (slug),
    KEY idx_learning_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learning_content_translations (
    content_id SMALLINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    title VARCHAR(200) NOT NULL,
    summary VARCHAR(500) NULL,
    body TEXT NOT NULL,
    PRIMARY KEY (content_id, locale),
    CONSTRAINT fk_learning_tr FOREIGN KEY (content_id) REFERENCES learning_contents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_reports (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    question_id INT UNSIGNED NOT NULL,
    reason ENUM('incorrect','unclear','translation','inappropriate','other') NOT NULL,
    message VARCHAR(1000) NULL,
    status ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
    admin_note VARCHAR(1000) NULL,
    resolved_by INT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    KEY idx_reports_status (status, created_at),
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_reports_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reports_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
