-- Migration: Add Dual-Pin Support to Tasks Table (FIXED VERSION)
-- Version: 1.0
-- Date: 2025-09-20
-- Description: Extends tasks table to support both personal and global pins

-- Create migration tracking table if it doesn't exist
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT
);

-- Check if migration has already been applied
SELECT @migration_exists := COUNT(*) FROM schema_migrations WHERE version = '001_dual_pin_support';

-- Step 1: Add columns if migration not applied
SELECT @migration_exists AS 'Checking migration status...';

-- Add columns only if migration hasn't been applied
SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks
     ADD COLUMN pin_type ENUM(''personal'', ''global'') NULL COMMENT ''Type of pin: personal (user) or global (leadership)'',
     ADD COLUMN pinned_by VARCHAR(255) NULL COMMENT ''User ID who created the pin'',
     ADD COLUMN pinned_at TIMESTAMP NULL COMMENT ''When the pin was created'',
     ADD COLUMN pin_priority INT NULL COMMENT ''Priority level 1-10 for global pins only'',
     ADD COLUMN pin_reason TEXT NULL COMMENT ''Optional reason/context for the pin''',
    'SELECT ''Columns already exist'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Add constraints (must be separate from column addition)
SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD CONSTRAINT chk_pin_priority CHECK (pin_type = ''global'' OR pin_priority IS NULL)',
    'SELECT ''Constraint chk_pin_priority already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD CONSTRAINT chk_pin_priority_range CHECK (pin_priority IS NULL OR (pin_priority >= 1 AND pin_priority <= 10))',
    'SELECT ''Constraint chk_pin_priority_range already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD CONSTRAINT chk_pinned_by_when_pinned CHECK ((pin_type IS NULL AND pinned_by IS NULL AND pinned_at IS NULL) OR (pin_type IS NOT NULL AND pinned_by IS NOT NULL AND pinned_at IS NOT NULL))',
    'SELECT ''Constraint chk_pinned_by_when_pinned already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add indexes (separate statements for better compatibility)
SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD INDEX idx_pin_type (pin_type)',
    'SELECT ''Index idx_pin_type already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD INDEX idx_pinned_by (pinned_by)',
    'SELECT ''Index idx_pinned_by already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD INDEX idx_pinned_at (pinned_at)',
    'SELECT ''Index idx_pinned_at already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD INDEX idx_pin_type_priority (pin_type, pin_priority)',
    'SELECT ''Index idx_pin_type_priority already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@migration_exists = 0,
    'ALTER TABLE tasks ADD INDEX idx_pin_type_date (pin_type, pinned_at)',
    'SELECT ''Index idx_pin_type_date already exists'' as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Record migration as applied (only if not already applied)
INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('001_dual_pin_support', 'Added dual-pin support with personal and global pin types');

-- Final status
SELECT
    CASE
        WHEN @migration_exists = 0 THEN 'Migration 001_dual_pin_support applied successfully!'
        ELSE 'Migration 001_dual_pin_support was already applied.'
    END as migration_status;

-- Show final table structure
SELECT 'Final table structure:' as info;
DESCRIBE tasks;