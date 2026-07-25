-- ========================================================================
-- LASU FCIT Secure Examination CMS - v0.7.1 Migration
-- Secure Examination Document Management
--
-- Tables Added: paper_versions, paper_files
-- ========================================================================

USE lasu_exam_enterprise_db;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------
-- 1. paper_versions - Complete immutable version history per paper
-- ------------------------------------------------------------------------
DROP TABLE IF EXISTS paper_versions;
CREATE TABLE paper_versions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    examination_paper_id INT UNSIGNED NOT NULL,
    version_number      INT UNSIGNED NOT NULL DEFAULT 1,
    created_by          INT UNSIGNED NOT NULL,
    submission_status   ENUM('Draft','Submitted','Returned','Approved','Rejected') NOT NULL DEFAULT 'Draft',
    change_notes        TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pv_examination_paper
        FOREIGN KEY (examination_paper_id)
        REFERENCES examination_papers(id) ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_pv_created_by
        FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY uk_pv_paper_version (examination_paper_id, version_number),
    KEY idx_pv_status   (submission_status),
    KEY idx_pv_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------
-- 2. paper_files - Individual documents associated with a paper version
-- ------------------------------------------------------------------------
DROP TABLE IF EXISTS paper_files;
CREATE TABLE paper_files (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paper_version_id    INT UNSIGNED NOT NULL,
    file_type           ENUM('Question Paper','Marking Scheme','Practical Resources','Additional Instructions') NOT NULL,
    original_filename   VARCHAR(255) NOT NULL,
    generated_filename  VARCHAR(255) NOT NULL,
    file_extension      VARCHAR(16) NOT NULL,
    mime_type           VARCHAR(128) NOT NULL,
    file_size           BIGINT UNSIGNED NOT NULL,
    sha256_hash         CHAR(64) NOT NULL,
    storage_path        VARCHAR(512) NOT NULL,
    uploaded_by         INT UNSIGNED NOT NULL,
    uploaded_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_pf_paper_version
        FOREIGN KEY (paper_version_id)
        REFERENCES paper_versions(id) ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_pf_uploaded_by
        FOREIGN KEY (uploaded_by)
        REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY uk_pf_version_type_hash (paper_version_id, file_type, sha256_hash),
    KEY idx_pf_sha256   (sha256_hash),
    KEY idx_pf_uploaded (uploaded_at),
    KEY idx_pf_type     (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
