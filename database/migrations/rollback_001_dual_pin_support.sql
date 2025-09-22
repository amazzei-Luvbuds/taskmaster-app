-- Rollback Migration: Remove Dual-Pin Support from Tasks Table
-- Version: 1.0
-- Date: 2025-09-20
-- Description: Rollback script to remove dual-pin columns and constraints

-- WARNING: This will permanently delete all pin data!
-- Make sure to backup the database before running this rollback.

SET @migration_version = '001_dual_pin_support';

-- Check if migration exists to rollback
SET @migration_exists = (SELECT COUNT(*) FROM schema_migrations WHERE version = @migration_version);

-- Only proceed if migration has been applied
IF @migration_exists > 0 THEN
    -- Drop indexes
    DROP INDEX IF EXISTS idx_pin_type ON tasks;
    DROP INDEX IF EXISTS idx_pinned_by ON tasks;
    DROP INDEX IF EXISTS idx_pinned_at ON tasks;
    DROP INDEX IF EXISTS idx_pin_type_priority ON tasks;
    DROP INDEX IF EXISTS idx_pin_type_date ON tasks;

    -- Drop constraints
    ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_pin_priority;
    ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_pin_priority_range;
    ALTER TABLE tasks DROP CONSTRAINT IF EXISTS chk_pinned_by_when_pinned;

    -- Drop columns
    ALTER TABLE tasks DROP COLUMN IF EXISTS pin_type;
    ALTER TABLE tasks DROP COLUMN IF EXISTS pinned_by;
    ALTER TABLE tasks DROP COLUMN IF EXISTS pinned_at;
    ALTER TABLE tasks DROP COLUMN IF EXISTS pin_priority;
    ALTER TABLE tasks DROP COLUMN IF EXISTS pin_reason;
END IF;

-- Remove migration record
DELETE FROM schema_migrations WHERE version = @migration_version;

-- Display rollback status
SELECT
    CASE
        WHEN @migration_exists > 0 THEN 'Rollback 001_dual_pin_support completed successfully'
        ELSE 'Migration 001_dual_pin_support was not applied - nothing to rollback'
    END as status;

-- Show current table structure
DESCRIBE tasks;