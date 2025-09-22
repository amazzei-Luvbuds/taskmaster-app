-- Add BMAD columns to tasks table for enhanced AI functionality
-- This script adds the missing columns that the API expects

USE taskmaster_db;

-- Add BMAD Analysis JSON column
ALTER TABLE tasks
ADD COLUMN bmad_analysis_json JSON COMMENT 'BMAD AI task analysis data';

-- Add Generated Subtasks JSON column
ALTER TABLE tasks
ADD COLUMN generated_subtasks_json JSON COMMENT 'AI generated subtasks data';

-- Add index for performance on new JSON columns
ALTER TABLE tasks
ADD INDEX idx_bmad_analysis ((JSON_VALID(bmad_analysis_json))),
ADD INDEX idx_generated_subtasks ((JSON_VALID(generated_subtasks_json)));

-- Display the updated table structure
DESCRIBE tasks;