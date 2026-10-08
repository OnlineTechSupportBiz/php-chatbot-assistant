--
-- Add timezone column to users table for per-user timezone preference.
-- Defaults to 'UTC' to match the Session::login() fallback.
-- Idempotent: skips when the column already exists.
--
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'chatbot_schema' AND table_name = 'users' AND column_name = 'timezone'
    ) THEN
        ALTER TABLE chatbot_schema.users ADD COLUMN timezone VARCHAR(64) DEFAULT 'UTC' NOT NULL;
    END IF;
END $$;
