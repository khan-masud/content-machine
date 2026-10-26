# Self-hosting on a small VPS

This guide runs Content Machine on one small Ubuntu server, without Docker.
It is sized for 1 vCPU, 2 GB RAM, and 25 GB of disk. Everything it uses is
free except the server itself:

- AI drafts: Gemini or Groq free tier ([post categories guide](../guides/post-categories.md))
- Voice notes: Groq's free Whisper (see [Voice notes with Groq](#voice-notes-with-groq))
- Photo cards: Pillow on the server ([photo cards guide](../guides/photo-cards.md))
- Card backgrounds: Cloudflare Workers AI, Pollinations, or Pexels
- Publishing: the Facebook Graph API ([Facebook Page guide](../guides/facebook-page.md))

Rough memory use once running:

| Process | Memory |
|---|---|
| PostgreSQL (tuned below) | 150–250 MB |
| PHP-FPM, 4 workers at most | 150–300 MB |
| Two queue workers | 120–250 MB |
| Nginx | 10 MB |
| One photo-card render, while it runs | about 35 MB |

That leaves room on a 2 GB server. A 2 GB swap file covers the one heavy
step, `npm run build` during updates.

## What you need

- An Ubuntu 24.04 server with SSH access as a user that can `sudo`.
- A domain or subdomain pointing at the server's IP address (an `A` record),
  for example `cm.example.com`. Telegram only sends webhooks to HTTPS URLs,
  and a free Let's Encrypt certificate needs a domain. A free subdomain
  from a dynamic-DNS service works too.

Every command below runs on the server. Replace `cm.example.com` with your
domain.

## 1. Swap and basics

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
# Use swap only when memory is nearly full.
echo 'vm.swappiness=10' | sudo tee /etc/sysctl.d/99-swappiness.conf
sudo sysctl --system

sudo apt update && sudo apt upgrade -y
sudo apt install -y git unzip curl acl software-properties-common \
    nginx supervisor certbot python3-certbot-nginx ufw

sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
```

## 2. PHP 8.4, Composer, Node, Python Pillow

```bash
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-intl \
    php8.4-zip php8.4-bcmath php8.4-gd php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-opcache

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node is only used to build the dashboard's assets.
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs

# Photo cards: Pillow plus libraqm, which shapes Bangla text correctly.
sudo apt install -y python3-pil libraqm0
python3 -c "from PIL import features; print('raqm:', features.check('raqm'))"
```

The last line must print `raqm: True`. If it prints `False`, Bangla letters
on cards will break apart; check that `libraqm0` installed.

Link previews use `yt-dlp` for video links (YouTube, Facebook, TikTok):

```bash
sudo curl -fsSL -o /usr/local/bin/yt-dlp \
    https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp_linux
sudo chmod 755 /usr/local/bin/yt-dlp
```

### Keep PHP-FPM small

Edit `/etc/php/8.4/fpm/pool.d/www.conf` and set:

```ini
pm = ondemand
pm.max_children = 4
pm.process_idle_timeout = 30s
pm.max_requests = 300
```

Then edit `/etc/php/8.4/fpm/php.ini` and set:

```ini
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
opcache.memory_consumption = 64
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.4-fpm
```

## 3. PostgreSQL

```bash
sudo apt install -y postgresql
sudo -u postgres psql -c "CREATE USER content_machine WITH PASSWORD 'choose-a-long-password';"
sudo -u postgres psql -c "CREATE DATABASE content_machine OWNER content_machine;"
```

For a 2 GB server, create `/etc/postgresql/16/main/conf.d/small-vps.conf`.
Use your PostgreSQL version number in the path; `ls /etc/postgresql`
shows it.

```ini
shared_buffers = 128MB
effective_cache_size = 512MB
work_mem = 4MB
maintenance_work_mem = 64MB
max_connections = 30
```

```bash
sudo systemctl restart postgresql
```

## 4. Get the code

```bash
sudo mkdir -p /var/www/content-machine /var/log/content-machine
sudo chown "$USER":www-data /var/www/content-machine
sudo chown www-data:www-data /var/log/content-machine
git clone https://github.com/khan-masud/content-machine.git /var/www/content-machine
cd /var/www/content-machine
```

Both you (for deploys) and `www-data` (the web server and workers) write to
`storage/` and `bootstrap/cache/`. Give both of them write access, now and
for files created later:

```bash
sudo setfacl -R -m u:www-data:rwX -m u:"$USER":rwX storage bootstrap/cache
sudo setfacl -dR -m u:www-data:rwX -m u:"$USER":rwX storage bootstrap/cache
```

## 5. Configure `.env`

```bash
cp .env.example .env
nano .env
```

Change these lines:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cm.example.com

DB_DATABASE=content_machine
DB_USERNAME=content_machine
DB_PASSWORD=choose-a-long-password

LOG_CHANNEL=daily
LOG_LEVEL=warning

# Your login. The first deploy creates it and prints a password.
ADMIN_EMAIL=you@example.com
ADMIN_NAME=Your Name
# Close sign-up once your account exists.
DISABLE_REGISTRATION=true

# A fresh install has no old servers to drain, so the Telegram cutover
# migrations can run right away.
CM_TELEGRAM_CUTOVER_READY=true
CM_TELEGRAM_OLD_WEB_FLEET_DRAINED=true
```

The app stores times in UTC. Your own timezone is set per workspace in the
dashboard, and schedule times are converted from it.

Then install and build:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate
npm ci
npm run build
php artisan cm:deploy
php artisan optimize
```

`cm:deploy` runs the migrations and creates the `ADMIN_EMAIL` user. **Copy
the password it prints**; it is shown only once. You can change it after
logging in.

## 6. Nginx and HTTPS

```bash
sudo cp deploy/vps/nginx.conf /etc/nginx/sites-available/content-machine
sudo sed -i 's/cm.example.com/YOUR-DOMAIN/' /etc/nginx/sites-available/content-machine
sudo ln -s /etc/nginx/sites-available/content-machine /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

sudo certbot --nginx -d YOUR-DOMAIN
```

Certbot adds HTTPS to the site and renews the certificate on its own.
Open `https://YOUR-DOMAIN` and log in.

## 7. Queue workers and the scheduler

Drafts, voice notes, photo cards, and Facebook posts run in background
workers. Supervisor keeps them running:

```bash
sudo cp deploy/vps/supervisor.conf /etc/supervisor/conf.d/content-machine.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Both `cm-scratchpad` and `cm-default` should show `RUNNING`.

The scheduler handles scheduled Facebook posts, Telegram retries, and
cleanup. Laravel runs it from one cron line:

```bash
echo '* * * * * www-data cd /var/www/content-machine && php artisan schedule:run >> /dev/null 2>&1' \
    | sudo tee /etc/cron.d/content-machine
```

## 8. Connect your services

In the dashboard:

1. **AI providers**: add Gemini and/or Groq with the free presets.
2. **Telegram**: connect your bot ([Telegram guide](../guides/telegram-bot.md)).
   This needs the HTTPS site from step 6.
3. **Settings → Brand**: brand name, logo, and colours.
4. **Settings → Image AI**: Cloudflare account ID and token, or leave
   Pollinations on.
5. **Settings → Facebook**: Page ID and Page token
   ([Facebook Page guide](../guides/facebook-page.md)).

Then send `/post funny <some text>` to your bot. A draft and its photo card
should arrive in the chat within a minute.

## Voice notes with Groq

Voice notes sent to the bot are transcribed with the workspace's
OpenAI-shaped AI providers, in their priority order. Groq's free tier
includes Whisper, so adding Groq under **AI providers** is enough. The app
picks the right model by the provider's address:

| Provider | Models tried, in order |
|---|---|
| OpenAI (`api.openai.com`) | `gpt-4o-transcribe`, then `whisper-1` |
| Groq (`api.groq.com`) | `whisper-large-v3`, then `whisper-large-v3-turbo` |
| Gemini | skipped: its OpenAI-compatible API has no transcription |

Gemini can stay first for drafts. Voice notes skip it and use Groq. The
list lives in `config/ai_transcription.php` if you want to change it.

## Updating

```bash
sh /var/www/content-machine/deploy/vps/update.sh
```

The script pulls the latest code, installs dependencies, builds assets,
runs migrations, and restarts the workers so they load the new code.

## Backups

Back up the database and the uploaded files (cards, photos, voice notes) in
`storage/app/uploads`. A nightly database dump that keeps 7 days:

```bash
sudo mkdir -p /var/backups/content-machine
sudo chown postgres /var/backups/content-machine
echo '30 3 * * * postgres pg_dump -Fc content_machine > /var/backups/content-machine/db-$(date +\%a).dump' \
    | sudo tee /etc/cron.d/content-machine-backup
```

Copy `/var/backups/content-machine` and `storage/app/uploads` off the server
now and then. A backup kept on the same server is lost with the server.

## Troubleshooting

| Problem | Check |
|---|---|
| A page shows "Server Error" | `tail -50 storage/logs/laravel-*.log` |
| Drafts or cards never arrive | `sudo supervisorctl status`, then `tail -50 /var/log/content-machine/scratchpad.log` |
| Scheduled Facebook posts stay "Scheduled" | Is the cron line in `/etc/cron.d/content-machine`? Run `php artisan schedule:list`. |
| Cards have broken Bangla letters | `python3 -c "from PIL import features; print(features.check('raqm'))"` must print `True`. |
| Telegram bot never answers | The site must be reachable over HTTPS. Reconnect the bot under **Telegram** to register the webhook again. |
| The server runs out of memory | `free -h`. Check that swap is on and `pm.max_children` is 4 or lower. |
| A general health check | `php artisan cm:doctor` |
