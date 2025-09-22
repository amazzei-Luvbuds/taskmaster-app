-- Migration: Add Dual-Pin Support to Tasks Table
-- Version: 1.0
-- Date: 2025-09-20
-- Description: Extends tasks table to support both personal and global pins

-- Check if migration has already been applied
SET @migration_version = '001_dual_pin_support';
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Only proceed if migration hasn't been applied
SET @migration_exists = (SELECT COUNT(*) FROM schema_migrations WHERE version = @migration_version);

-- Add dual-pin columns if migration hasn't been applied
SET @sql_add_columns = CASE
    WHEN @migration_exists = 0 THEN
        'ALTER TABLE tasks
         ADD COLUMN pin_type ENUM(''personal'', ''global'') NULL COMMENT ''Type of pin: personal (user) or global (leadership)'',
         ADD COLUMN pinned_by VARCHAR(255) NULL COMMENT ''User ID who created the pin'',
         ADD COLUMN pinned_at TIMESTAMP NULL COMMENT ''When the pin was created'',
         ADD COLUMN pin_priority INT NULL COMMENT ''Priority level 1-10 for global pins only'',
         ADD COLUMN pin_reason TEXT NULL COMMENT ''Optional reason/context for the pin'''
    ELSE 'SELECT ''Migration already applied'' as message'
END;

PREPARE stmt FROM @sql_add_columns;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add constraints if migration hasn't been applied
SET @sql_add_constraints = CASE
    WHEN @migration_exists = 0 THEN
        'ALTER TABLE tasks
         ADD CONSTRAINT chk_pin_priority CHECK (pin_type = ''global'' OR pin_priority IS NULL),
         ADD CONSTRAINT chk_pin_priority_range CHECK (pin_priority IS NULL OR (pin_priority >= 1 AND pin_priority <= 10)),
         ADD CONSTRAINT chk_pinned_by_when_pinned CHECK ((pin_type IS NULL AND pinned_by IS NULL AND pinned_at IS NULL) OR (pin_type IS NOT NULL AND pinned_by IS NOT NULL AND pinned_at IS NOT NULL))'
    ELSE 'SELECT ''Constraints already exist'' as message'
END;

PREPARE stmt FROM @sql_add_constraints;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for performance if migration hasn't been applied
SET @sql_add_indexes = CASE
    WHEN @migration_exists = 0 THEN
        'ALTER TABLE tasks
         ADD INDEX idx_pin_type (pin_type),
         ADD INDEX idx_pinned_by (pinned_by),
         ADD INDEX idx_pinned_at (pinned_at),
         ADD INDEX idx_pin_type_priority (pin_type, pin_priority),
         ADD INDEX idx_pin_type_date (pin_type, pinned_at)'
    ELSE 'SELECT ''Indexes already exist'' as message'
END;

PREPARE stmt FROM @sql_add_indexes;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Record migration as applied
INSERT IGNORE INTO schema_migrations (version, description)
VALUES (@migration_version, 'Added dual-pin support with personal and global pin types');

-- Display migration status
SELECT
    CASE
        WHEN @migration_exists = 0 THEN 'Migration 001_dual_pin_support applied successfully'
        ELSE 'Migration 001_dual_pin_support was already applied'
    END as status;

-- Show the new table structure
DESCRIBE tasks;