--
-- Multi-model LLM support: per-user provider configuration.
--
-- Port of the JS app's users-table columns (js 001_initial.sql 390-394):
--   llm_base_url        — default LLM provider base URL (OpenAI-compatible)
--   embedding_base_url  — separate embeddings endpoint (falls back to llm_base_url)
--   embedding_api_key   — separate embeddings key (falls back to openai_api_key)
--   embedding_model     — embeddings model id (falls back to text-embedding-3-small)
--   llm_models          — ordered JSON list of {name, base_url, api_key, model};
--                         the first is primary, later entries are failover.
-- All nullable/empty so existing single-key installs keep working unchanged.
--
ALTER TABLE chatbot_schema.users ADD COLUMN IF NOT EXISTS llm_base_url       VARCHAR(500) DEFAULT NULL;
ALTER TABLE chatbot_schema.users ADD COLUMN IF NOT EXISTS embedding_base_url VARCHAR(500) DEFAULT NULL;
ALTER TABLE chatbot_schema.users ADD COLUMN IF NOT EXISTS embedding_api_key  VARCHAR(500) DEFAULT NULL;
ALTER TABLE chatbot_schema.users ADD COLUMN IF NOT EXISTS embedding_model    VARCHAR(255) DEFAULT NULL;
ALTER TABLE chatbot_schema.users ADD COLUMN IF NOT EXISTS llm_models         JSONB DEFAULT NULL;