# PHP Chatbot Assistant

**A free chatbot you run on your own server.** Upload your documents, train a bot on them, and paste one line of code into your website. Visitors ask questions, and the bot answers using your documents.

- Free and open source (MIT license)
- You host it, so your data stays with you
- No subscription and no account with us

[Product page](https://onlinetechsupport.biz/portfolio/php-chatbot-assistant/index.html) · [GitHub](https://github.com/OnlineTechSupportBiz/php-chatbot-assistant)

## What it costs

The software itself costs **$0**. Download it, use it for anything, change it however you like.

The only things you may ever pay for:

| Thing | Who you pay |
|---|---|
| The software | Nobody — it's free |
| An OpenAI API key (powers the bot's answers) | OpenAI, at their normal prices |
| A LlamaCloud key (reads your PDF and Word files) | LlamaCloud, at their normal prices |
| A server to run it on | Your hosting company |
| Optional: we install it for you | Us — $150 one time |
| Optional: we clean up your documents for you | Us — quoted up front |

The OpenAI and LlamaCloud keys are accounts **you** open with **them**. We never resell their services and never see your keys' bills.

## What it does

- **Many chatbots.** Make as many as you want. Each one has its own name, colors, personality and settings.
- **Answers from your documents.** Upload PDF, Word, TXT or Markdown files. The bot reads them and answers questions using what's in them.
- **Two ways to read documents.** Regular mode searches small pieces of text (fast and simple). PageIndex mode reads the document's table of contents like a person would (better for long manuals). You pick per chatbot.
- **Gets you leads.** The bot can ask visitors for their name, email and phone during the chat, then saves them for you.
- **Quick answers.** Set up ready-made replies for common questions. They're instant and free.
- **Safe to put on the internet.** Login protection, rate limits, spending caps, and each account can only see its own data — enforced by the database itself, not just the code.
- **Looks the way you want.** Light or dark theme, your colors, your bot name. It won't clash with your website's styles.
- **A dashboard.** See how many conversations and messages you've had, with a bar chart of the last 7, 30 or 90 days.

## Add it to your website

One script tag. The chatbot page builds it for you with your colors already set:

```html
<script src="https://your-server.com/widget.js"
        data-widget-token="YOUR_WIDGET_TOKEN"
        data-api-base="https://your-server.com"
        data-bot-name="Support"
        data-primary-color="#2563eb"
        data-position="bottom-right"
        data-widget-theme="light"></script>
```

Paste it into your site before `</body>`. You also list which domains are allowed to use it, so nobody can steal the snippet for their own site.

## How to install it

You need PHP 8.2+ (with `pdo_pgsql` and `mbstring`), PostgreSQL 16+ with pgvector, and Composer.

**1. Download the code**

```bash
git clone https://github.com/OnlineTechSupportBiz/php-chatbot-assistant.git
cd php-chatbot-assistant/pca
composer install
```

**2. Create the database and its two users**

```bash
sudo -u postgres psql << 'SQL'
CREATE ROLE chatbot_migrator LOGIN PASSWORD 'your-migrator-password';
CREATE ROLE chatbot_user LOGIN PASSWORD 'your-app-password';
CREATE DATABASE chatbot_assistant OWNER chatbot_migrator;
SQL
```

The migrator owns the tables; the app user is what the website logs in as. This split is **required** — it keeps each customer's data locked away from the others.

**3. Enable the pgvector extension**

Only a superuser can install it — the migrator cannot:

```bash
sudo -u postgres psql -d chatbot_assistant << 'SQL'
CREATE SCHEMA IF NOT EXISTS chatbot_schema AUTHORIZATION chatbot_migrator;
CREATE EXTENSION IF NOT EXISTS vector WITH SCHEMA chatbot_schema;
SQL
```

The schema must belong to the migrator — it creates the tables and manages access rights later.

**4. Create the tables**

```bash
cd migrations

DB_HOST=127.0.0.1 DB_PORT=5432 DB_NAME=chatbot_assistant \
DB_USER=chatbot_user DB_PASS='your-app-password' \
DB_MIGRATOR_USER=chatbot_migrator DB_MIGRATOR_PASS='your-migrator-password' \
PG_SCHEMA=chatbot_schema \
php run.php
```

`php run.php --fresh` drops all tables and rebuilds. The command refuses to run without the migrator role.

**Starting over?** To wipe everything and begin again, do the drop AND the recreate — dropping alone leaves nothing for the next run to connect with:

```bash
sudo -u postgres psql << 'SQL'
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='chatbot_assistant' AND pid <> pg_backend_pid();
DROP DATABASE IF EXISTS chatbot_assistant;
DROP ROLE IF EXISTS chatbot_user;
DROP ROLE IF EXISTS chatbot_migrator;
SQL
```

…then re-run steps 2 and 3 above.

**5. Write the settings file**

```bash
cd ..
cp .env.example .env
nano .env            # fill in the DB_*, APP_URL and SMTP values
chmod 600 .env
```

(There's also `public_html/install.php`, a browser wizard that does steps 2–5. If you use it, **delete it afterwards** — it must never stay on a live server.)

**6. Start the app**

Point your web server at `public_html/`. For a quick local test:

```bash
php -S localhost:8000 -t public_html
```

**7. Register and add your keys**

Register on the site, log in, open Settings, and paste in your OpenAI and LlamaCloud keys.

**8. Create a chatbot**

Pick an industry preset (or write your own instructions), upload a document, wait for training to finish, then copy the snippet onto your website.

**Check the security setup** (optional): every table should show `t | t` (RLS enabled and forced) and be owned by `chatbot_migrator`, not `chatbot_user`:

```bash
sudo -u postgres psql -d chatbot_assistant -c \
  "SELECT c.relname, c.relrowsecurity, c.relforcerowsecurity, pg_get_userbyid(c.relowner) AS owner
   FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
   WHERE n.nspname = 'chatbot_schema' AND c.relkind = 'r';"
```

## Configuring your own AI models

You don't have to use OpenAI's website models. In Settings, add any AI model you want by giving it a name, an API key, and a URL (this is how you use OpenAI, OpenRouter, or a self-hosted model). Then each chatbot picks one of those models, with an automatic backup model if the first one fails.

## Keeping the bot under control

Every chatbot has guardrails you can set:

- A limit on messages per minute
- A daily spending cap (token budget)
- A maximum message length
- A maximum number of messages per conversation
- Detection of trick prompts ("ignore your instructions and...")

Anything blocked this way costs you nothing, and it all gets logged.

## What's inside

```
php-chatbot-assistant/
├── public_html/   ← the only folder your web server exposes
└── pca/           ← the application code (never web-visible)
```

Built with plain PHP 8.2+ (no framework), PostgreSQL 16 with pgvector, and a small JavaScript widget. No Docker and no Node.js tools needed.

## Tests

From the `pca/` folder:

```bash
php vendor/bin/phpunit tests/Unit/
```

282 tests, all running on a fake database, so nothing outside your computer is contacted.

## Settings file

The app reads its settings from `pca/.env`. Copy `pca/.env.example` to `pca/.env` and fill in your values (the installer can do this for you):

| Setting | Default | What it is |
|---|---|---|
| `DB_HOST` | `127.0.0.1` | Database server address |
| `DB_PORT` | `5432` | Database port |
| `DB_NAME` | `chatbot_assistant` | Database name |
| `DB_SCHEMA` | `chatbot_schema` | Database schema |
| `DB_USER` | `chatbot_user` | Database user the app logs in as |
| `DB_PASS` | (none) | Database password |
| `DB_MIGRATOR_USER` | (none) | Owner user that sets up the tables |
| `DB_MIGRATOR_PASS` | (none) | Its password |
| `APP_ENV` | `production` | `production` or `development` |
| `APP_URL` | `https://example.com` | Your site's address (used in emails) |
| `SESSION_LIFETIME` | `1440` | Minutes before idle users are logged out |
| `SMTP_HOST` | `localhost` | Mail server for sending email |
| `SMTP_PORT` | `465` | Mail server port |
| `SMTP_AUTH` | `true` | Does the mail server need a login? |
| `SMTP_USER` | (none) | Mail username |
| `SMTP_PASS` | (none) | Mail password |
| `SMTP_ENCRYPTION` | `ssl` | Mail encryption type |

## Common questions

**Is this a subscription service?**
No. It's software you download and run yourself. Nothing is hosted by us and nothing bills through us.

**Where does my data live?**
In your own database, on your own server. The only outside calls are to OpenAI, LlamaCloud and your own mail server.

**Which mode should I pick for my documents?**
Start with the regular vector search — it's fast and works well for most sites. Use PageIndex for long documents with clear headings, like manuals and reports. You can try both and compare.

**Can I get help installing it?**
Yes — $150 one time and we set everything up, train one chatbot, and hand you the snippet. Email [contact@onlinetechsupport.biz](mailto:contact@onlinetechsupport.biz).

## Links

- **Product page:** https://onlinetechsupport.biz/portfolio/php-chatbot-assistant/index.html
- **Source code and issues:** https://github.com/OnlineTechSupportBiz/php-chatbot-assistant

## License

[MIT](LICENSE) — free for anything, including commercial use.

## We can also handle your documents

Getting documents ready for a chatbot is often the hard part: scanned PDFs with no text, messy Word exports, files spread everywhere. Send them to us and we'll clean them up and format them so they upload and train properly. Flat rate or per document, quoted up front, with an NDA signed before you send anything.

## Donations

The project stays free. If it helped you and you want to chip in:

| Network | Address |
|---|---|
| Bitcoin (BTC) | `bc1qnkclf2pzg3pvyhgfz75vj74tdptk82h7k90xpa` |
| Ethereum (ETH) | `0x33f6EcA24DF4D68cDd3A0A01f1EFE9dF9710d4a7` |
| XRP (XRP Ledger) | `rGHexxe7EpYzkvDoq2HLdiCqQeLdH8HWus` |
| Solana (SOL) | `DMCsqz2s52QvyyuSbA943JLjt7tYR1uGAZr6iNCXJQTV` |
| VeChain (VET) | `0x86806548E8CA67e58c6D1c1B3fB32FBF3b08878D` |
| Cardano (ADA) | `addr1qxpusclv4kzp36xqvq55mlmlcs4v3cugd5z96ctup55pg5vrep37etvyrr5vqcpffhlhl3p2er3csmgyt4shcrfgz3gsjmt9hh` |

Ethereum and VeChain addresses both start with `0x` but are on different networks — check the whole address before sending.
