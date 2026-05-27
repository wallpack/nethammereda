# Production Deploy Checklist (nethammereda.ru)

Use this checklist after uploading code to `/var/www/nethammereda/app`.

## 1. Composer and Laravel caches

```bash
cd /var/www/nethammereda/app
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart nethammereda-queue
```

## 2. Build permissions hardening (required)

Make sure `nginx` user (`www-data`) can read and traverse everything in `public/build`, including `public/build/assets`.

```bash
sudo chown -R clawd:www-data /var/www/nethammereda/app/public/build
sudo chmod -R 775 /var/www/nethammereda/app/public/build
```

## 3. Asset health check (required)

Extract all JS/CSS asset URLs from the homepage and verify every asset returns HTTP `200`.

```bash
curl -s https://nethammereda.ru | grep -oE '/build/assets/[^"]+\.(js|css)' | sort -u
```

For each URL from the previous command:

```bash
curl -I "https://nethammereda.ru<ASSET_PATH>"
```

Expected result: all static assets respond with `HTTP/2 200`.
