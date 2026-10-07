--
-- Force Row-Level Security on every tenant table.
--
-- The base migration only ENABLEd RLS, which is inert for the table owner:
-- whichever role ran the migrations owns the tables, and Postgres exempts
-- owners from their own policies. With the two-role installer (DB_MIGRATOR_USER
-- as owner, DB_USER as the RLS-subject app role) these FORCE statements are
-- what make the policies genuinely enforce tenant isolation — including for
-- the owner role itself.
--
-- Matches the JS port (js-chatbot-assistant 001_initial.sql). Tables without
-- policies (password_resets, platform_settings, rate_limits) are untouched.
-- Idempotent: safe to re-run.
--
ALTER TABLE chatbot_schema.audit_logs        FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.chat_stats        FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.chatbots          FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.conversations     FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.document_chunks   FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.document_page_index FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.document_strategies FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.documents         FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.leads             FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.magic_links       FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.messages          FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.quick_answers     FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.sessions          FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.user_permissions  FORCE ROW LEVEL SECURITY;
ALTER TABLE chatbot_schema.users             FORCE ROW LEVEL SECURITY;