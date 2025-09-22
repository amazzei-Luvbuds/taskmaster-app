-- Verification script to check if dual-pin columns exist
-- Run this manually in your MySQL client to verify the migration

-- Check if pin columns exist in tasks table
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'cosmichq_luvbudstaskmaster'
  AND TABLE_NAME = 'tasks'
  AND COLUMN_NAME IN ('pin_type', 'pinned_by', 'pinned_at', 'pin_priority', 'pin_reason')
ORDER BY COLUMN_NAME;

-- Check current table structure
DESCRIBE tasks;

-- Check if schema_migrations table exists
SELECT COUNT(*) as migration_table_exists FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'cosmichq_luvbudstaskmaster' AND TABLE_NAME = 'schema_migrations';

-- If migration was applied, check the record
SELECT * FROM schema_migrations WHERE version = '001_dual_pin_support';

-- Check constraints related to pins
SELECT CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = 'cosmichq_luvbudstaskmaster'
  AND CONSTRAINT_NAME LIKE '%pin%';

-- Check indexes related to pins
SHOW INDEX FROM tasks WHERE Key_name LIKE '%pin%';