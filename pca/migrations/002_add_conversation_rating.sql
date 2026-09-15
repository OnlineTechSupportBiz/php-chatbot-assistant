--
-- Add rating column to conversations table for 5-star widget feedback.
--
ALTER TABLE chatbot_schema.conversations ADD COLUMN rating INTEGER DEFAULT NULL;
