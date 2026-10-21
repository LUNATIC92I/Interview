-- InterviewPro initial schema (MySQL 8+, also compatible with MariaDB 10.6+)
-- Conventions: utf8mb4, InnoDB, UTC DATETIME columns, RESTRICT foreign keys on every history table.
-- CASCADE is used only for pure metadata children (translations, pivot rows) that carry no history.

CREATE TABLE roles (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name_fr VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profession_categories (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    icon VARCHAR(40) NOT NULL DEFAULT 'briefcase',
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profession_category_translations (
    category_id SMALLINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (category_id, locale),
    CONSTRAINT fk_cat_tr_category FOREIGN KEY (category_id) REFERENCES profession_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE professions (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NOT NULL,
    slug VARCHAR(100) NOT NULL,
    icon VARCHAR(40) NOT NULL DEFAULT 'briefcase',
    level_min TINYINT UNSIGNED NOT NULL DEFAULT 1,
    level_max TINYINT UNSIGNED NOT NULL DEFAULT 5,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_professions_slug (slug),
    KEY idx_professions_category (category_id, is_active, sort_order),
    CONSTRAINT fk_professions_category FOREIGN KEY (category_id) REFERENCES profession_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profession_translations (
    profession_id SMALLINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    name VARCHAR(150) NOT NULL,
    summary TEXT NULL,
    responsibilities TEXT NULL,
    PRIMARY KEY (profession_id, locale),
    KEY idx_prof_tr_name (name),
    CONSTRAINT fk_prof_tr_profession FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    headline VARCHAR(160) NULL,
    current_position VARCHAR(160) NULL,
    target_profession_id SMALLINT UNSIGNED NULL,
    experience_level TINYINT UNSIGNED NULL,
    country VARCHAR(80) NULL,
    bio TEXT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_profiles_profession FOREIGN KEY (target_profession_id) REFERENCES professions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    locale CHAR(2) NOT NULL DEFAULT 'fr',
    default_mode VARCHAR(20) NOT NULL DEFAULT 'training',
    default_question_count TINYINT UNSIGNED NOT NULL DEFAULT 6,
    reduce_motion TINYINT(1) NOT NULL DEFAULT 0,
    email_notifications TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_prefs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_reset_token (token_hash),
    KEY idx_reset_user (user_id),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verification_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_verify_token (token_hash),
    KEY idx_verify_user (user_id),
    CONSTRAINT fk_verify_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    rl_key VARCHAR(191) NOT NULL PRIMARY KEY,
    hits INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    KEY idx_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE skill_categories (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL,
    name_fr VARCHAR(120) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_skill_cat_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE skills (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    skill_category_id SMALLINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    name_fr VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_skills_code (code),
    CONSTRAINT fk_skills_category FOREIGN KEY (skill_category_id) REFERENCES skill_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profession_skills (
    profession_id SMALLINT UNSIGNED NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (profession_id, skill_id),
    KEY idx_prof_skills_skill (skill_id),
    CONSTRAINT fk_prof_skills_profession FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE CASCADE,
    CONSTRAINT fk_prof_skills_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE difficulty_levels (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    name_fr VARCHAR(80) NOT NULL,
    name_en VARCHAR(80) NOT NULL,
    description_fr VARCHAR(255) NULL,
    description_en VARCHAR(255) NULL,
    UNIQUE KEY uq_difficulty_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evaluation_rubrics (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(60) NOT NULL,
    name_fr VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    description_fr TEXT NULL,
    description_en TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_rubrics_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rubric_criteria (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    rubric_id SMALLINT UNSIGNED NOT NULL,
    code VARCHAR(60) NOT NULL,
    evaluator VARCHAR(40) NOT NULL,
    name_fr VARCHAR(150) NOT NULL,
    name_en VARCHAR(150) NOT NULL,
    description_fr VARCHAR(500) NULL,
    description_en VARCHAR(500) NULL,
    weight TINYINT UNSIGNED NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_criteria (rubric_id, code),
    CONSTRAINT fk_criteria_rubric FOREIGN KEY (rubric_id) REFERENCES evaluation_rubrics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL,
    profession_id SMALLINT UNSIGNED NULL,
    category_id SMALLINT UNSIGNED NULL,
    question_type VARCHAR(30) NOT NULL,
    level_min TINYINT UNSIGNED NOT NULL DEFAULT 1,
    level_max TINYINT UNSIGNED NOT NULL DEFAULT 5,
    difficulty TINYINT UNSIGNED NOT NULL DEFAULT 2,
    rubric_id SMALLINT UNSIGNED NOT NULL,
    recommended_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 180,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_questions_code (code),
    KEY idx_questions_profession (profession_id, is_active, question_type),
    KEY idx_questions_category (category_id, is_active, question_type),
    KEY idx_questions_type (question_type, is_active, difficulty),
    CONSTRAINT fk_questions_profession FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES profession_categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_questions_rubric FOREIGN KEY (rubric_id) REFERENCES evaluation_rubrics (id) ON DELETE RESTRICT,
    CONSTRAINT fk_questions_difficulty FOREIGN KEY (difficulty) REFERENCES difficulty_levels (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_translations (
    question_id INT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    body TEXT NOT NULL,
    guidance TEXT NULL,
    evaluation_criteria TEXT NULL,
    expected_elements JSON NULL,
    common_mistakes JSON NULL,
    keywords VARCHAR(500) NULL,
    PRIMARY KEY (question_id, locale),
    CONSTRAINT fk_q_tr_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE answer_examples (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_example (question_id, locale),
    CONSTRAINT fk_examples_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_skills (
    question_id INT UNSIGNED NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (question_id, skill_id),
    KEY idx_qskills_skill (skill_id),
    CONSTRAINT fk_qskills_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE CASCADE,
    CONSTRAINT fk_qskills_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_followups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    trigger_type VARCHAR(30) NOT NULL,
    min_words SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_followups_question (question_id, is_active),
    CONSTRAINT fk_followups_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_followup_translations (
    followup_id INT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    body TEXT NOT NULL,
    trigger_keywords VARCHAR(500) NULL,
    PRIMARY KEY (followup_id, locale),
    CONSTRAINT fk_fu_tr_followup FOREIGN KEY (followup_id) REFERENCES question_followups (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenarios (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    profession_id SMALLINT UNSIGNED NULL,
    category_id SMALLINT UNSIGNED NULL,
    interview_type VARCHAR(40) NOT NULL,
    experience_level TINYINT UNSIGNED NOT NULL DEFAULT 2,
    difficulty TINYINT UNSIGNED NOT NULL DEFAULT 2,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_scenarios_slug (slug),
    KEY idx_scenarios_profession (profession_id, is_active),
    CONSTRAINT fk_scenarios_profession FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_scenarios_category FOREIGN KEY (category_id) REFERENCES profession_categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenario_translations (
    scenario_id SMALLINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (scenario_id, locale),
    CONSTRAINT fk_scen_tr_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scenario_questions (
    scenario_id SMALLINT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (scenario_id, position),
    KEY idx_scen_q_question (question_id),
    CONSTRAINT fk_scen_q_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios (id) ON DELETE CASCADE,
    CONSTRAINT fk_scen_q_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE interview_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(32) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    profession_id SMALLINT UNSIGNED NOT NULL,
    scenario_id SMALLINT UNSIGNED NULL,
    interview_type VARCHAR(40) NOT NULL,
    experience_level TINYINT UNSIGNED NOT NULL,
    locale CHAR(2) NOT NULL,
    difficulty TINYINT UNSIGNED NOT NULL,
    mode VARCHAR(20) NOT NULL,
    question_count TINYINT UNSIGNED NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    per_question_seconds SMALLINT UNSIGNED NULL,
    allow_skip TINYINT(1) NOT NULL DEFAULT 1,
    allow_back TINYINT(1) NOT NULL DEFAULT 0,
    allow_pause TINYINT(1) NOT NULL DEFAULT 1,
    followups_enabled TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('in_progress','paused','completed','abandoned') NOT NULL DEFAULT 'in_progress',
    current_position SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL,
    paused_at DATETIME NULL,
    paused_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    overall_score DECIMAL(5,2) NULL,
    evaluator VARCHAR(20) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_sessions_public (public_id),
    KEY idx_sessions_user_status (user_id, status, updated_at),
    KEY idx_sessions_user_completed (user_id, completed_at),
    KEY idx_sessions_scenario (user_id, scenario_id),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sessions_profession FOREIGN KEY (profession_id) REFERENCES professions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sessions_scenario FOREIGN KEY (scenario_id) REFERENCES scenarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE interview_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    session_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    followup_id INT UNSIGNED NULL,
    parent_answer_id INT UNSIGNED NULL,
    position SMALLINT UNSIGNED NOT NULL,
    status ENUM('pending','draft','answered','skipped','timed_out') NOT NULL DEFAULT 'pending',
    answer_text MEDIUMTEXT NULL,
    word_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    time_spent_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    shown_at DATETIME NULL,
    draft_saved_at DATETIME NULL,
    submitted_at DATETIME NULL,
    UNIQUE KEY uq_answers_position (session_id, position),
    KEY idx_answers_question (question_id),
    CONSTRAINT fk_answers_session FOREIGN KEY (session_id) REFERENCES interview_sessions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_answers_followup FOREIGN KEY (followup_id) REFERENCES question_followups (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE answer_evaluations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    answer_id INT UNSIGNED NOT NULL,
    rubric_id SMALLINT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    criteria_scores JSON NOT NULL,
    strengths JSON NOT NULL,
    gaps JSON NOT NULL,
    skills_demonstrated JSON NOT NULL,
    missing_elements JSON NOT NULL,
    recommendations JSON NOT NULL,
    next_tip JSON NULL,
    evaluator VARCHAR(20) NOT NULL DEFAULT 'rules',
    confidence VARCHAR(10) NOT NULL DEFAULT 'medium',
    model_name VARCHAR(100) NULL,
    llm_feedback MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_evaluations_answer (answer_id),
    CONSTRAINT fk_evaluations_answer FOREIGN KEY (answer_id) REFERENCES interview_answers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_evaluations_rubric FOREIGN KEY (rubric_id) REFERENCES evaluation_rubrics (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_skill_progress (
    user_id INT UNSIGNED NOT NULL,
    skill_id SMALLINT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    total_score DECIMAL(10,2) NOT NULL DEFAULT 0,
    avg_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    last_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    best_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, skill_id),
    KEY idx_progress_skill (skill_id),
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT fk_progress_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id VARCHAR(60) NULL,
    details JSON NULL,
    ip_masked VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    KEY idx_audit_action (action, created_at),
    KEY idx_audit_created (created_at),
    KEY idx_audit_user (user_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    value_type ENUM('string','int','bool') NOT NULL DEFAULT 'string',
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE translation_overrides (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    locale CHAR(2) NOT NULL,
    translation_key VARCHAR(190) NOT NULL,
    value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_override (locale, translation_key),
    CONSTRAINT fk_override_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
