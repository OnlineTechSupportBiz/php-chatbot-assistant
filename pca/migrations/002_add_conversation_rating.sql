--
-- Add rating column to conversations table for 5-star widget feedback.
-- Idempotent: skips when the column already exists (partial prior run).
--
DO $$ BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'chatbot_schema' AND table_name = 'conversations' AND column_name = 'rating'
    ) THEN
        ALTER TABLE chatbot_schema.conversations ADD COLUMN rating INTEGER DEFAULT NULL;
    END IF;
END $$;
