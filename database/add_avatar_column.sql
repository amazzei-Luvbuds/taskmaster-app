-- Add author_avatar column to comments table if it doesn't exist
-- This migration ensures the comments table has the avatar field

-- Check if column exists and add it if missing
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'comments'
AND COLUMN_NAME = 'author_avatar';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE comments ADD COLUMN author_avatar TEXT NULL AFTER author_email',
    'SELECT "Column author_avatar already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the column was added
DESCRIBE comments;