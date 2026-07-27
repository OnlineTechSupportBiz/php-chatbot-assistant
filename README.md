# Chatbot Assistant — Multi-Tenant AI Chatbot Platform

**A self-hosted, production-grade chatbot SaaS platform with RAG & PageIndex document Q&A, AI lead capture, and a drop-in embeddable widget — built on PostgreSQL + pgvector.**

Manage hundreds of chatbots across dozens of tenants, each with full data isolation, one-click document training, configurable retrieval strategies, granular user permissions, and a complete authentication suite. No external dependencies beyond what you host yourself.

---

## At a Glance

| Area | What It Does |
|---|---|
| **Chatbots** | Create, clone, and manage AI chatbots per-tenant with per-bot model config, styling, and domain restrictions |
| **Document RAG** | Upload PDF/DOCX/TXT/MD → parse via LlamaCloud → chunk → embed via OpenAI → index in pgvector |
| **Two Retrieval Strategies** | Traditional vector search (pgvector cosine similarity) or PageIndex (LLM-guided hierarchical navigation) |
| **Widget** | A single script tag — Shadow DOM, Markdown rendering, light/dark themes, quick answer chips |
| **Lead Capture** | AI-driven contact extraction embedded in the conversation itself — no modals, no forms |
| **Quick Answers** | Trigger-based canned responses that fire before any LLM call — zero token cost |
| **Auth** | Email+password (Argon2id), magic links, TOTP MFA, account lockout, rate limiting |
| **Admin** | Role-based access control, granular permissions, super admin dashboard, full audit trail |
| **Multi-Tenancy** | PostgreSQL Row-Level Security — tenant data is isolated at the database level |

---

## Two Retrieval Strategies

### Traditional RAG

The standard approach: documents are chunked, each chunk is embedded via OpenAI (`text-embedding-3-small`, 1536 dimensions), and stored in pgvector with IVFFlat indexing. At query time, the user's question is embedded and the nearest chunks are retrieved via cosine distance. Reliable, well-understood, and effective when answers live inside individual paragraphs.

### PageIndex — Vectorless Retrieval

Documents are parsed into a hierarchical tree of headings and sections — a structured table of contents. At query time, the LLM skims the outline (headings only, minimal token cost) and selects the relevant sections. Only the content from those sections is fetched.

No embeddings. No chunk boundaries. No "the answer was split across two chunks" problem. The LLM navigates the document the same way a human would — by scanning the structure first, then reading what matters.

**Choose per-chatbot.** Upload the same document under both strategies if you want side-by-side.

---

## Authentication That Ships Complete

| Feature | Implementation |
|---|---|
| Password login | Argon2id hashing, constant-time comparison |
| Magic link login | Token-based, one-click email sign-in |
| MFA | TOTP (via otphp) with recovery codes |
| Email verification | Token-based, resend support |
| Password reset | Rate-limited, one-hour token expiry |
| Account lockout | 5 failed attempts → 15-minute lock |
| Rate limiting | Per-IP on login, configurable per-session message rate |
| CSRF protection | Every form, every POST |
| Session management | HttpOnly cookies, SameSite=Lax, periodic ID rotation, sliding lifetime expiration |
| Remember Me | 30-day persistent cookie |

---

## Abuse Prevention

All guardrails are evaluated **before** any OpenAI API call — a blocked request costs you nothing.

- **Rate limiting** — configurable messages per minute per session
- **Daily token budget** — hard cap per chatbot, configurable
- **Max message length** — prevent absurdly long prompts
- **Max messages per conversation** — limit conversation depth
- **Prompt injection detection** — pattern-based jailbreak scanning with silent polite rejection
- **Audit trail** — every guardrail trigger is logged with context

---

## The Widget

```html
<script src="https://example.com/widget.js"
        data-widget-token="YOUR_TOKEN"
        data-api-base="https://example.com"
        data-bot-name="Support"
        data-primary-color="#2563eb"
        data-position="bottom-right"
        data-widget-theme="light"></script>
```

A single script tag. The widget lives in a Shadow DOM — your site's CSS can't break it, and it can't break your site. It renders Markdown responses, persists sessions via localStorage, and supports:

- Light and dark themes
- Custom positioning, colors, and bot name
- Quick answer suggestion chips loaded on panel open
- Typing indicators with animated dots
- Slide-in panel animation
- Expandable panel — click or drag from the left or right edge to resize the widget width
- Inactivity-based 5-star rating prompt — after 2 minutes of inactivity following a conversation, a rating bar slides up with a closeable option; ratings are stored as 1-5 per conversation


## Lead Capture

When enabled, the system prompt includes behavioral instructions that teach the bot to naturally ask for contact information during conversation — not as a modal popup, but as a natural part of the exchange. A secondary LLM call extracts name, email, and phone from the conversation thread. Leads are stored per-chatbot with a summary of the conversation.

**Two trigger patterns:**
- **Proactive** — after greeting, ask once for name/email/phone together
- **Off-scope** — when the visitor asks for something the bot can't do, offer a human follow-up

The bot asks once, in one message. If the visitor declines, it drops it and continues helping.

---

## Industry Presets

30+ industries with multiple persona variants each — every preset uses a `{company}` placeholder that resolves to the tenant's company name. From a fine dining concierge prompt to a crop advisory bot to a welding service assistant, the system prompts are written by someone who understands the domain, not a generic "you are a helpful assistant."

Agriculture · Automotive · Construction & Engineering · E-Commerce · Education & E-Learning · Energy & Utilities · Finance & Banking · Fitness & Wellness · Food & Beverage (14 variants) · Government · Healthcare · Home Services (10 variants) · Hospitality & Travel · Human Resources · Insurance · Legal · Logistics & Transportation · Manufacturing · Marketing & Advertising · Media & Entertainment · Nonprofit · Pet Services · Pharmaceuticals & Biotech · Real Estate · Technology & SaaS · Telecommunications

---

## Architecture

```
Widget (browser) ──→ Public API ──→ OpenAI Chat Completions
                        │
                        ├── pgvector (Traditional RAG — vector similarity search)
                        │
                        └── PageIndex Tables (LLM-guided document tree navigation)
```

**Tenant isolation:** PostgreSQL Row-Level Security enforced by a session-scoped `app.admin_id` variable. The application code never trusts user IDs from client input — the database enforces the boundary.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, clean PSR-4 architecture, no framework |
| Database | PostgreSQL 16 + pgvector (IVFFlat index) |
| Embeddings | OpenAI text-embedding-3-small (1536d) |
| LLM | OpenAI GPT-4o-mini (configurable per-chatbot) |
| Document parsing | LlamaCloud Parse |
| Auth | Argon2id, TOTP (otphp), Magic Links |
| Widget | Vanilla JS, Shadow DOM, Marked |
| Email | PHPMailer (SMTP) |
| Schema | `chatbot_schema` — fully indexed, RLS-protected |

---

## Quick Start

```bash
# Requirements
#   PHP 8.2+ (pdo_pgsql, mbstring)
#   PostgreSQL 16+ with pgvector extension
#   Composer

git clone https://github.com/OnlineTechSupportBiz/php-chatbot-assistant
cd php-chatbot-assistant
composer install
```

Open `https://example.com/install.php` to create the admin account. Then:

1. At the login screen, register a user account
2. Configure your OpenAI and LlamaCloud API keys in **Settings**
3. Create a chatbot (or pick from 30+ industry presets)
4. Upload a document (PDF, DOCX, TXT, MD) — after upload the pipeline runs: parse → chunk → embed → index
5. Grab the widget embed code from the chatbot detail page
6. Drop the script tag on your website

**Make sure to delete the install.php**

That's it. Your website has an AI assistant that answers questions from your own content.

---

## Running Tests

```bash
# Run all unit tests
php vendor/bin/phpunit tests/Unit/
```

The test suite uses mocked databases and injected dependencies — no external services required. 276 tests covering models, controllers, HTTP routing, auth, rate limiting, prompt injection detection, retrieval strategies, and XSS sanitization.

### Real SMTP Integration Test

By default, email tests use a mock PHPMailer and never contact an SMTP server. To test actual email delivery against your configured SMTP server:

```bash
# Export your SMTP credentials (must match your .env or environment config)
export SMTP_HOST=mail.example.com
export SMTP_PORT=465
export SMTP_AUTH=true
export SMTP_USER=noreply@example.com
export SMTP_PASS="your-password"
export SMTP_ENCRYPTION=ssl
export MAIL_FROM_ADDRESS=noreply@example.com
export MAIL_FROM_NAME="Your App"

# Set the recipient for the real send test
export SMTP_REAL_TEST=you@example.com

# Or run the full suite
php vendor/bin/phpunit tests/Unit/
```

If the SMTP test fails, the error message includes the PHPMailer diagnostic (connection refused, auth failure, certificate issue, etc.).

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | PostgreSQL host |
| `DB_PORT` | `5432` | PostgreSQL port |
| `DB_NAME` | `chatbot_assistant` | Database name |
| `DB_SCHEMA` | `chatbot_schema` | Schema |
| `DB_USER` | `chatbot_user` | Database user |
| `DB_PASS` | — | Database password |
| `APP_ENV` | `production` | Controls HSTS and error handling |
| `APP_URL` | `https://example.com` | Application URL (used in email links) |
| `SESSION_LIFETIME` | `1440` | Session idle timeout in minutes |
| `SMTP_HOST` | `localhost` | SMTP server |
| `SMTP_PORT` | `465` | SMTP port |
| `SMTP_AUTH` | `true` | Enable SMTP authentication |
| `SMTP_USER` | — | SMTP username |
| `SMTP_PASS` | — | SMTP password |
| `SMTP_ENCRYPTION` | `ssl` | SMTP encryption |

---

## License

[MIT](LICENSE). Deploy it on your own infrastructure, own your data, and never pay per-seat or per-conversation fees.
