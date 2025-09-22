-- TaskMaster Avatar Fix
-- Run this SQL to fix comment avatar persistence

-- Step 1: Check if author_avatar column exists
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'comments'
AND COLUMN_NAME = 'author_avatar';

-- Step 2: Add the column if it doesn't exist
-- NOTE: Run this command in your database management tool (phpMyAdmin, etc.)

ALTER TABLE comments
ADD COLUMN author_avatar TEXT NULL
AFTER author_email;

-- Step 3: Verify the column was added
DESCRIBE comments;

-- Step 4: Test by updating existing comments with mock avatar
-- (Optional - for testing)
UPDATE comments
SET author_avatar = 'https://via.placeholder.com/64'
WHERE author_name = 'Alex Mazzei'
LIMIT 1;

-- Step 5: Check if the update worked
SELECT id, author_name, author_avatar
FROM comments
WHERE author_name = 'Alex Mazzei'
LIMIT 1;