# 🎵 BooKing

> **ATTENTION**: This is under development. Don't try this at home!

BooKing is a PHP/MariaDB web application for managing and visualizing venue locations on an interactive map. It features an integrated email client that lets you organize emails into _Conversations_. Each Conversation automatically "cools down" after the last message, helping you easily track which replies you’re still waiting for and keeping your inbox organized.

## 🚀 Setup

BooKing ships with compiled assets and a browser-based installation wizard (`install.php`), so no terminal access is required. You only need FTP access and a hosting panel (e.g. Plesk).

### Before you start

- The app must be reached over **HTTPS** (e.g. Let's Encrypt certificate in Plesk).
- PHP 8.0+ with the extensions `pdo_mysql`, `openssl`, `mbstring` and `imap` (all standard on Plesk).
- The `config/` directory must be writable by PHP (usually the default after FTP upload).

### Step-by-step

1. **Upload the app** — upload all files to your webspace via FTP (binary/auto mode).
2. **Create an empty database** — in Plesk: *Databases → Add Database*. Note host, port, database name, user and password. The database must be **empty**.
3. **Open the installer** — browse to `https://your-domain.example/install.php`. The wizard checks the requirements, then guides you through:
   - database credentials (writes `config/config.php` with a generated encryption key and imports the database schema),
   - the first **admin account** (login works via one-time codes sent to this email address),
   - the **global SMTP server** (required — it sends the login codes),
   - an email **verification code** — the installation only completes after you enter it,
   - optional **Mapbox** and **Brave Search** API keys (can also be added later under Admin → Settings).
4. **Add the scheduled task** — in Plesk: *Tools & Settings → Scheduled Tasks → Add Task*, run every minute:
   ```
   php /var/www/vhosts/your-domain.example/httpdocs/app/controllers/tasks/taskhandler.php
   ```
   Replace the path with the full server path to your BooKing directory (Plesk shows it under *Websites & Domains*).
5. **Back up the configuration** — download `config/config.php` via FTP and store it safely. The `ENCRYPTION_KEY` inside cannot be regenerated; without it, encrypted settings (e.g. mailbox passwords) are unrecoverable.
6. **Clean up** — delete `install.php` from the webspace via FTP. The installer is additionally and permanently disabled by the lock file `config/installer.lock`.

### If PHP cannot write `config/config.php`

The wizard detects this automatically: it offers the generated `config.php` as a download. Download it, upload it into the `config/` directory via FTP, then press **"I uploaded the file"** in the wizard.

### Interrupted installation / removing the lock

The wizard locks itself to the browser session that started it (`config/installer.lock`). If you lose that session (browser closed, cookies deleted) and need to continue:

1. Delete `config/installer.lock` via FTP.
2. Reload `install.php` and run the wizard again.

The lock only guards the *setup process*. A **completed** installation stays locked even if the file is removed: the installer refuses to run when `config/config.php` already exists. If a schema import failed halfway, drop all tables of the database in Plesk first, then run the wizard again with a fresh `config/installer.lock`.

### Manual configuration (advanced)

Instead of the wizard you can rename `config/config.example.php` to `config/config.php`, fill in the database credentials and a random encryption key (32+ characters), import `sql/schema.sql` into an empty database, and insert the first admin user into the `users` table yourself (e.g. via phpMyAdmin). The wizard does all of this for you — use it whenever possible.

## ✨ Features

### Venues

Organize venues in the database and visualize them on the map.

<img width="250" height="auto" alt="venues involo ch_app_pages_map_index php" src="https://github.com/user-attachments/assets/21bd4ec0-bb37-4aa5-aac8-1a2c66ab9b78" /> <img width="250" height="auto" alt="venues_list" src="https://github.com/user-attachments/assets/67075f56-874c-4d64-818e-0231fae124b7" />

Add new venues by simply doing a websearch (Brave Search & Mapbox API Keys required).

<img width="250" height="auto" alt="add venue" src="https://github.com/user-attachments/assets/7a74c300-0fe6-4c98-9b4e-5aa290223a18" />

### Communication

Keep track of your communication with _Conversations_. eMail client integrated.

<img width="250" height="auto" alt="conversations" src="https://github.com/user-attachments/assets/e584c0a3-5013-4dfb-b089-4edd4105c717" /> <img width="250" height="auto" alt="mails" src="https://github.com/user-attachments/assets/87abc4d5-f034-494b-a398-8aefa017f404" />

## 📋 Requirements

- **PHP** 8.0 or higher
- **Web Server**: Apache or Nginx
- **Node.js & npm** (for development only)
- **MariaDB/MySQL**

**Made with ❤️ and AI for musicians and booking agents**
