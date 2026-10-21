-- Paid access per profession: payments, granted access and payment provider notifications.

CREATE TABLE payments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    public_id CHAR(32) NOT NULL,
    user_id INT UNSIGNED NULL,
    profession_id SMALLINT UNSIGNED NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_reference VARCHAR(64) NULL,
    amount INT UNSIGNED NOT NULL,
    currency CHAR(3) NOT NULL,
    status ENUM('created','pending','processing','completed','failed','cancelled','expired','refunded') NOT NULL DEFAULT 'created',
    payment_method VARCHAR(40) NULL,
    checkout_url VARCHAR(500) NULL,
    failure_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    UNIQUE KEY uq_payments_public (public_id),
    UNIQUE KEY uq_payments_provider_ref (provider, provider_reference),
    KEY idx_payments_user (user_id, created_at),
    KEY idx_payments_status (status, created_at),
    CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_payments_profession FOREIGN KEY (profession_id) REFERENCES professions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profession_access (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    profession_id SMALLINT UNSIGNED NOT NULL,
    source ENUM('payment','admin') NOT NULL,
    payment_id INT UNSIGNED NULL,
    granted_by INT UNSIGNED NULL,
    granted_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY uq_access_user_profession (user_id, profession_id),
    KEY idx_access_profession (profession_id),
    CONSTRAINT fk_access_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_access_profession FOREIGN KEY (profession_id) REFERENCES professions (id),
    CONSTRAINT fk_access_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
    CONSTRAINT fk_access_granted_by FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(30) NOT NULL,
    event_id VARCHAR(80) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    provider_reference VARCHAR(64) NULL,
    environment VARCHAR(20) NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    UNIQUE KEY uq_payment_notifications_event (provider, event_id),
    KEY idx_payment_notifications_ref (provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE interview_sessions ADD COLUMN is_trial TINYINT(1) NOT NULL DEFAULT 0 AFTER mode;
