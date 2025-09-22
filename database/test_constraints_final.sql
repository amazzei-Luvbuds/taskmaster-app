-- Final Database Constraint Verification Test
-- This script tests all constraints to ensure they work properly

-- Test 1: Valid personal pin (should succeed)
SELECT 'Test 1: Valid personal pin' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at)
VALUES ('test-valid-personal', 'Test personal pin', 'Tech', 'personal', 'user123', NOW());

-- Verify it was inserted
SELECT task_id, pin_type, pinned_by, pin_priority FROM tasks WHERE task_id = 'test-valid-personal';

-- Test 2: Valid global pin with priority (should succeed)
SELECT 'Test 2: Valid global pin with priority' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority, pin_reason)
VALUES ('test-valid-global', 'Test global pin', 'Tech', 'global', 'admin123', NOW(), 7, 'High priority task');

-- Verify it was inserted
SELECT task_id, pin_type, pinned_by, pin_priority, pin_reason FROM tasks WHERE task_id = 'test-valid-global';

-- Test 3: Personal pin with priority (should FAIL)
SELECT 'Test 3: Personal pin with priority (should fail)' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority)
VALUES ('test-invalid-personal-priority', 'Test invalid', 'Tech', 'personal', 'user123', NOW(), 5);
-- This should fail with constraint violation

-- Test 4: Global pin with priority out of range (should FAIL)
SELECT 'Test 4: Global pin with invalid priority (should fail)' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority)
VALUES ('test-invalid-priority-range', 'Test invalid priority', 'Tech', 'global', 'admin123', NOW(), 15);
-- This should fail with constraint violation

-- Test 5: Pin without pinnedBy (should FAIL)
SELECT 'Test 5: Pin without pinnedBy (should fail)' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_at)
VALUES ('test-missing-pinned-by', 'Test missing pinnedBy', 'Tech', 'personal', NOW());
-- This should fail with constraint violation

-- Test 6: Pin without pinnedAt (should FAIL)
SELECT 'Test 6: Pin without pinnedAt (should fail)' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by)
VALUES ('test-missing-pinned-at', 'Test missing pinnedAt', 'Tech', 'personal', 'user123');
-- This should fail with constraint violation

-- Test 7: All NULL values (should succeed)
SELECT 'Test 7: All NULL pin values (should succeed)' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority, pin_reason)
VALUES ('test-all-null', 'Test all null', 'Tech', NULL, NULL, NULL, NULL, NULL);

-- Verify it was inserted
SELECT task_id, pin_type, pinned_by, pin_priority FROM tasks WHERE task_id = 'test-all-null';

-- Test 8: Valid priority range boundaries
SELECT 'Test 8: Priority boundary values' as test_name;
INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority)
VALUES ('test-priority-1', 'Test priority 1', 'Tech', 'global', 'admin123', NOW(), 1);

INSERT INTO tasks (task_id, action_item, department, pin_type, pinned_by, pinned_at, pin_priority)
VALUES ('test-priority-10', 'Test priority 10', 'Tech', 'global', 'admin123', NOW(), 10);

-- Show all test records
SELECT 'All test records:' as summary;
SELECT task_id, pin_type, pinned_by, pin_priority, pin_reason
FROM tasks
WHERE task_id LIKE 'test-%'
ORDER BY task_id;

-- Clean up test data
DELETE FROM tasks WHERE task_id LIKE 'test-%';

-- Show constraint information
SELECT 'Constraint verification:' as info;
SELECT CONSTRAINT_NAME, CHECK_CLAUSE
FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tasks'
  AND CONSTRAINT_NAME LIKE '%pin%';

-- Show index information
SELECT 'Index verification:' as info;
SHOW INDEX FROM tasks WHERE Key_name LIKE '%pin%';