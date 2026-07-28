--
-- Add timezone column to users table for per-user timezone preference.
-- Defaults to 'UTC' to match the Session::login() fallback.
--
ALTER TABLE chatbot_schema.users ADD COLUMN timezone VARCHAR(64) DEFAULT 'UTC' NOT NULL;
