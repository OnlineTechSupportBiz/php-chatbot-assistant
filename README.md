# PHP Chatbot Assistant

**A self-hosted AI chatbot platform.** Train it on your own documents and drop one script tag on your site. Answers come out of your content, the conversations stay in your PostgreSQL database, and nobody bills you per seat, per bot or per message.

Open source · MIT licensed · PHP 8.2+

[Product overview and screenshots](https://onlinetechsupport.biz/portfolio/php-chatbot-assistant/index.html) · [GitHub repository](https://github.com/OnlineTechSupportBiz/php-chatbot-assistant) · [Installation](https://github.com/OnlineTechSupportBiz/php-chatbot-assistant#installation)

## A complete chatbot product, built for paying clients

Clone it, run the installer, add your API keys. Tenancy, the training pipeline, the widget, lead capture, permissions and the audit trail are all in the box: the parts a client engagement needs from day one, written and maintained by someone who has had to do exactly that.

### Chatbots per tenant

Create, clone and manage as many bots as you like. Each one carries its own model settings, system prompt, widget styling and allowed domains.

### Document Q&A

Upload PDF, DOC, DOCX, TXT or Markdown. The pipeline parses, chunks, embeds and indexes it automatically, and shows you the status of every step.

### Two retrieval strategies

Classic vector search in pgvector, or PageIndex: an LLM navigating a document outline with no embeddings at all. Choose per chatbot.

### AI lead capture

The bot asks for a name, email and phone inside the conversation, with no modal and no form. Leads are extracted, stored and summarised per conversation.

### Quick answers

Trigger-based canned replies that fire before the model is ever called. Common questions cost nothing and come back instantly.

### Authentication, finished

Argon2id passwords, magic links, TOTP with recovery codes, account lockout after five failed attempts, per-IP rate limiting on login, CSRF tokens and rotating sessions.

## Features

| Area | What is in it |
|---|---|
| Core platform | Multi-tenant chatbot management · Document Q&A pipeline · Two retrieval strategies · One-tag embed widget · AI lead capture · Quick answer chips · Full authentication system |
| Retrieval | Vector search (pgvector) · PageIndex vectorless navigation · Per-chatbot strategy choice |
| Document handling | PDF/DOCX/TXT/Markdown upload · Automatic parse, chunk, embed and index · LlamaCloud parsing |
| Widget | Shadow DOM isolation · Light and dark themes · Custom colour styling · Typing indicators · Resizable panel · Star ratings · Domain-restricted CORS |
| Security and auth | Argon2id password hashing · Magic link login · TOTP with recovery codes · Account lockout · Per-IP rate limiting · CSRF protection · Rotating sessions |
| Abuse protection | Message rate limiting · Daily token budgets · Maximum message length · Maximum messages per conversation · Prompt-injection detection · Audit trail logging |
| Multi-tenancy | Row-Level Security isolation · Per-tenant API keys · Super admin dashboard · Per-account permissions |
| Admin and analytics | Dashboard with stats · Response-source chart · Conversation history view · Captured leads table · Chatbot cloning |
| Licensing and cost | MIT license (free) · No per-seat fees · Self-hosted deployment |

### Use cases

| Audience | What they build with it |
|---|---|
| Agencies and freelancers | White-label chatbots for clients · Website support widget as a deliverable |
| Small and medium businesses | FAQ and support deflection · Lead qualification on a marketing site · Internal knowledge base bot |
| Content-heavy sites | Long-document Q&A · Product documentation assistant |
| Regulated or privacy-sensitive industries | Data residency requirements · Cost-capped deployments |
| Developers and technical users | SaaS chatbot product foundation · Learning reference |

#### Pros

- No recurring platform fees
- Data stays under your control
- Two retrieval strategies, chosen per bot
- Real multi-tenancy, not bolted on
- Cost guardrails built in
- Simple tech stack
- Complete test coverage
- Widget isolation
- Fast to embed

#### Cons

- Self-hosting is on you, though we offer installation services

## Two ways to answer from your documents

Most chatbots force one retrieval model on you. Here it is a per-chatbot setting, so you can use embeddings where they are strongest and skip them where they get in the way.

### Traditional RAG: vector search

Documents are chunked, embedded with `text-embedding-3-small` (1536 dimensions) and stored in pgvector with an IVFFlat index. At query time the question is embedded and the nearest chunks are returned by cosine distance.

- Fast, proven and predictable on cost
- Ideal when answers live inside individual paragraphs
- Index is ready as soon as ingestion finishes

### PageIndex: vectorless navigation

Documents are parsed into a hierarchy of headings and sections. The model skims the outline, picks the sections that matter and reads only those, the way a person uses a table of contents.

- No chunk boundaries to split an answer in half
- Strong on long, well-structured documents
- Fewer embedding calls; outline skimming stays cheap

Upload the same document under both strategies if you want to compare answers side by side.

## Architecture

```
Widget (browser) → Public API → LLM chat completion
                       │
                       ├── pgvector · vector search
                       │
                       └── PageIndex · outline navigation
```

Tenants are isolated at the database level with PostgreSQL Row-Level Security on a session-scoped tenant id. Application code never trusts a user id sent from the client.

## One script tag, and it is on your site

```html
<script src="https://your-server.com/widget.js"
        data-widget-token="YOUR_WIDGET_TOKEN"
        data-api-base="https://your-server.com"
        data-bot-name="Support"
        data-primary-color="#2563eb"
        data-position="bottom-right"
        data-widget-theme="light"></script>
```

The snippet is generated from the chatbot's own settings, so colours, header text, placeholder and position all match as you edit them. The widget runs in a Shadow DOM: your site's CSS cannot break it, and it cannot break your site.

- Light and dark panels, custom primary colour, bot name and position
- Quick answer chips loaded when the panel opens, plus typing indicators
- Expandable: drag either edge to resize the panel width
- Ratings: an inactivity-triggered 1 to 5 star bar per conversation, two minutes after the last message
- Locked down: allowed-domain CORS restriction, so a copied snippet is useless on someone else's site

## Lead capture inside the conversation

When a chatbot has lead capture enabled, its system prompt teaches the bot to ask for contact details as part of the exchange rather than through a popup. A second model call extracts the name, email and phone from the thread, and the lead is stored against that chatbot with a summary of the conversation.

Two trigger patterns are available:

- **Proactive:** after the greeting, ask once for name, email and phone together
- **Off-scope:** when the visitor wants something the bot cannot do, offer a human follow-up

The bot asks once, in one message. If the visitor declines, it drops the subject and carries on helping.

## Industry prompt presets

26 industries, 101 presets, plus a blank custom prompt if you would rather write your own. Every preset uses a `{company}` placeholder that resolves to the tenant's company name, and the variants are written for their own domain: a fine dining concierge, a crop advisory bot, a welding service assistant.

Agriculture · Automotive · Construction & Engineering · E-Commerce & Retail · Education & E-Learning · Energy & Utilities · Finance & Banking · Fitness & Wellness · Food & Beverage (11) · Government & Public Sector · Healthcare · Home Services (10) · Hospitality & Travel · Human Resources · Insurance · Legal · Logistics & Transportation · Manufacturing & Industrial · Marketing & Advertising · Media & Entertainment · Nonprofit & Social Services · Pet Services · Pharmaceuticals & Biotech · Real Estate · Technology & SaaS · Telecommunications

## Guardrails before the model is called

Every guardrail runs before any API request goes out, so a blocked message costs you nothing and a hostile one never reaches your invoice.

- Rate limiting, with a configurable number of messages per minute per session
- A daily token budget as a hard cap per chatbot
- A maximum message length, so oversized prompts are rejected outright
- A maximum number of messages per conversation, so one session cannot run away
- Prompt-injection patterns, scanned for and quietly refused
- An audit trail: every guardrail trigger is logged with its context
- Tenant isolation through Row-Level Security, never a user id taken from client input

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2+, PSR-4, no framework |
| Database | PostgreSQL 16 with pgvector (IVFFlat index) |
| Tenancy | Row-Level Security on a session-scoped tenant id |
| Embeddings | OpenAI text-embedding-3-small, 1536 dimensions |
| Chat model | Configurable per chatbot from 20 OpenAI models, GPT-4.1-mini by default |
| Parsing | LlamaCloud Parse for PDF, DOC, DOCX, TXT and Markdown |
| Auth | Argon2id, TOTP via otphp, magic links |
| Widget | Vanilla JavaScript in a Shadow DOM, with Marked for Markdown |
| Email | PHPMailer over your own SMTP server |

## Chat models

Twenty models are in the dropdown on the create and edit screens, chosen per chatbot. GPT-4.1-mini is the default. The API id is what gets sent to OpenAI.

| Model | API id |
|---|---|
| GPT-4.1-mini | `gpt-4.1-mini` |
| GPT-4.1 | `gpt-4.1` |
| GPT-5-nano | `gpt-5-nano` |
| GPT-5-mini | `gpt-5-mini` |
| GPT-5 | `gpt-5` |
| GPT-5-Pro | `gpt-5-pro` |
| GPT-5.1 | `gpt-5.1` |
| GPT-5.2 | `gpt-5.2` |
| GPT-5.2-Pro | `gpt-5.2-pro` |
| GPT-5.3-Codex | `gpt-5.3-codex` |
| GPT-5.4-nano | `gpt-5.4-nano` |
| GPT-5.4-mini | `gpt-5.4-mini` |
| GPT-5.4 | `gpt-5.4` |
| GPT-5.4-Pro | `gpt-5.4-pro` |
| GPT-5.5 | `gpt-5.5` |
| GPT-5.5-Pro | `gpt-5.5-pro` |
| GPT-5.6-Luna | `gpt-5.6-luna` |
| GPT-5.6-Terra | `gpt-5.6-terra` |
| GPT-5.6-Sol | `gpt-5.6-sol` |
| GPT-6-Astr | `gpt-6-astr` |

Temperature and max tokens sit beside the model on the same screen, and the form shows a running cost estimate at your daily token budget. Adding a model means adding it to the dropdown and to the pricing map in `pca/src/App/Views/chatbots/form.php`.

## Directory layout

The repository keeps two directories, so it does not sprawl across a hosting home directory. Point your web server's document root at `public_html/`; everything else lives in `pca/` and is never web-reachable.

```
php-chatbot-assistant/        ← clone this into your hosting home directory
├── public_html/              ← document root: front controller, installer, widget, assets
├── pca/                      ← app: config, migrations, src, storage, tests, vendor, composer
├── README.md                 ← repo docs, kept at the root for GitHub
└── LICENSE
```

## Installation

Running on your own server in about fifteen minutes. You need PHP 8.2+ with `pdo_pgsql` and `mbstring`, PostgreSQL 16+ with the pgvector extension, Composer and an OpenAI API key.

**1. Clone the repository and install its two dependencies**

```bash
git clone https://github.com/OnlineTechSupportBiz/php-chatbot-assistant.git
cd php-chatbot-assistant/pca
composer install
```

**2. Point your web server at `public_html/`**

The document root is the sibling of `pca/`. For a local run:

```bash
# from the repository root
php -S localhost:8000 -t public_html

# or, from pca/
composer serve
```

**3. Open `install.php` and work through the installer**

Visit `https://your-server.com/install.php`. The three-step wizard configures the database connection, runs the migrations and creates the super-admin account. Before it writes anything it checks the PHP extensions, the PostgreSQL version and the vector extension.

**4. Register a user account and add its API keys**

Create a user account from the login screen, sign in, then open Settings to save the OpenAI key (embeddings and chat) and the LlamaCloud key (document parsing). Keys belong to the account, not the server, so each tenant brings its own. You can switch registration off in the admin dashboard and create client accounts yourself.

**5. Create a chatbot, train it, then embed it**

Pick an industry preset or write your own system prompt, choose vector RAG or PageIndex, and upload a document. Then copy the snippet from the chatbot page, paste it into your site before `</body>`, and add the domains allowed to use that widget token.

Delete `install.php` once the first admin exists. It is the one file in the repository that must not stay on a production server.

## Tests

Run these from the `pca/` directory, where `composer.json` and `phpunit.xml.dist` live:

```bash
php vendor/bin/phpunit tests/Unit/
```

282 tests cover the models, controllers, routing, auth, rate limiting, prompt-injection detection, retrieval strategies and XSS sanitisation, using mocked databases so nothing outside your machine is contacted. One test is skipped unless you opt into the live SMTP check below.

### The live SMTP check

By default the email tests use a mock PHPMailer and never contact a server. To send a real message through your own SMTP setup, export the same values your `.env` holds, set a recipient, and run the email test:

```bash
export SMTP_HOST=mail.example.com
export SMTP_PORT=465
export SMTP_AUTH=true
export SMTP_USER=noreply@example.com
export SMTP_PASS="your-password"
export SMTP_ENCRYPTION=ssl
export MAIL_FROM_ADDRESS=noreply@example.com
export MAIL_FROM_NAME="Your App"

# recipient for the real send
export SMTP_REAL_TEST=you@example.com

php vendor/bin/phpunit tests/Unit/Service/EmailServiceTest.php
```

A failure prints the PHPMailer diagnostic: connection refused, authentication failure, certificate problem.

## Environment variables

`.env` lives in `pca/`, beside the composer files. `pca/.env.example` lists the same keys.

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | PostgreSQL host |
| `DB_PORT` | `5432` | PostgreSQL port |
| `DB_NAME` | `chatbot_assistant` | Database name |
| `DB_SCHEMA` | `chatbot_schema` | Schema |
| `DB_USER` | `chatbot_user` | Database user |
| `DB_PASS` | (none) | Database password |
| `APP_ENV` | `production` | Controls HSTS and error handling |
| `APP_URL` | `https://example.com` | Application URL, used in email links |
| `SESSION_LIFETIME` | `1440` | Session idle timeout in minutes |
| `SMTP_HOST` | `localhost` | SMTP server |
| `SMTP_PORT` | `465` | SMTP port |
| `SMTP_AUTH` | `true` | Enable SMTP authentication |
| `SMTP_USER` | (none) | SMTP username |
| `SMTP_PASS` | (none) | SMTP password |
| `SMTP_ENCRYPTION` | `ssl` | SMTP encryption |

## Questions

### What does it cost to run?

The software is free under the MIT license, with no per-seat, per-bot or per-conversation fee. Your real costs are the server you already have, your OpenAI usage (embeddings at ingestion plus chat completions) and a LlamaCloud key for parsing. Quick answers and guardrail rejections never reach the model, and each chatbot can carry a daily token budget as a hard ceiling. The two things we charge for are optional: installing it for you at $150 USD once, and processing your documents at a flat rate or per document, both under a signed NDA.

### Do I need Docker or a Node toolchain?

No. The application is plain PHP 8.2+ with two Composer dependencies, PHPMailer and otphp. The widget is vanilla JavaScript served straight from the repository. You need a PostgreSQL 16 database with pgvector and a web server pointed at `public_html`.

### Where does my data, and my clients' data, live?

In your PostgreSQL database, on your server. The only outbound calls are the ones you configure: OpenAI for embeddings and chat, LlamaCloud for parsing, and your own SMTP server for email.

### Traditional RAG or PageIndex?

Start with vector RAG for FAQ-shaped content where answers sit inside individual paragraphs. Choose PageIndex for long, well-structured documents such as manuals, policies and lengthy reports, where chunk boundaries tend to cut an answer in half. It is a per-chatbot setting, so you can upload the same document under both and compare.

## Links

- **Product overview and screenshots:** https://onlinetechsupport.biz/portfolio/php-chatbot-assistant/index.html
- **Source and issue tracker:** https://github.com/OnlineTechSupportBiz/php-chatbot-assistant

## License

[MIT](LICENSE). Run it on your own infrastructure, keep the data on your own server, and pay nothing per seat or per conversation.

## Prefer it installed for you?

If you would rather not wrestle with the server and database setup yourself, we will install it on your hosting for **$150 USD**. The price covers the whole job: the PHP and PostgreSQL prerequisites checked, pgvector enabled, the installer run end to end, your OpenAI and LlamaCloud keys saved, one chatbot trained on your own document, and the widget snippet handed over ready to paste. Email [contact@onlinetechsupport.biz](mailto:contact@onlinetechsupport.biz) to arrange it.

## Prefer we do document processing and conversion for you?

Most of the work behind a document chatbot is the paperwork itself: scanned PDFs with no text layer, pages that need OCR, DOCX exports with the headings mangled, sheets and manuals spread across dozens of files. None of it can be indexed until it is cleaned up. Send it to us and we will convert it, cut it into chunks that suit your retrieval strategy, and hand back files that upload cleanly under vector RAG or PageIndex.

We price it whichever way suits you: a flat rate agreed up front for the whole batch, or a per-document fee if neither of us knows the volume until we have seen the files. Tell us roughly what you have and we will quote it. A signed NDA comes as standard, yours or ours, and we keep your documents only for the conversion and delete them on request.

## Donations

The project stays free and MIT licensed. If it saved you time and you want to send something back, the addresses below are ours. Send on the network named in each row, never a testnet, and any amount is welcome.

| Network | Address |
|---|---|
| Bitcoin (BTC) | `bc1qnkclf2pzg3pvyhgfz75vj74tdptk82h7k90xpa` |
| Ethereum (ETH) | `0x33f6EcA24DF4D68cDd3A0A01f1EFE9dF9710d4a7` |
| XRP (XRP Ledger) | `rGHexxe7EpYzkvDoq2HLdiCqQeLdH8HWus` |
| Solana (SOL) | `DMCsqz2s52QvyyuSbA943JLjt7tYR1uGAZr6iNCXJQTV` |
| VeChain (VET) | `0x86806548E8CA67e58c6D1c1B3fB32FBF3b08878D` |
| Cardano (ADA) | `addr1qxpusclv4kzp36xqvq55mlmlcs4v3cugd5z96ctup55pg5vrep37etvyrr5vqcpffhlhl3p2er3csmgyt4shcrfgz3gsjmt9hh` |

Ethereum and VeChain are both EVM chains: the two `0x` addresses look alike but they are not interchangeable, so check the whole address before you send.
