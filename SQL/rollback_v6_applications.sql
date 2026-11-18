-- CafeDash v6 ROLLBACK — undoes migration_v6_applications.sql
-- Only drops the applications table. Cafés and staff accounts created by approving
-- an application are real records and are NOT removed.
USE cafedash_db;
DROP TABLE IF EXISTS Restaurant_Application;
