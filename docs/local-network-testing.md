# Local Network Testing Guide

Access the POS from a tablet/phone on the same network.

## Setup

### 1. Build assets (no Vite needed)

```bash
npm run build
```

### 2. Update `.env`

```bash
# Change these from:
APP_URL="http://localhost:8000"
SESSION_DOMAIN="localhost"
SANCTUM_STATEFUL_DOMAINS="localhost:8000"

# To (replace 192.168.54.202 with your Mac's IP):
APP_URL="http://192.168.54.202:8000"
#SESSION_DOMAIN=""      # comment out
SANCTUM_STATEFUL_DOMAINS="localhost:8000,192.168.54.202:8000"
```

### 3. Clear config and serve

```bash
php artisan config:clear && php artisan cache:clear
php artisan serve --host=0.0.0.0 --port=8000
```

### 4. Login on tablet

Browse to `http://192.168.54.202:8000/dashboard/pos` (incognito/private tab)

## Revert to local development

```bash
# Restore .env
sed -i '' 's|APP_URL="http://192.168.54.202:8000"|APP_URL="http://localhost:8000"|' .env
sed -i '' 's|SANCTUM_STATEFUL_DOMAINS="localhost:8000,192.168.54.202:8000"|SANCTUM_STATEFUL_DOMAINS="localhost:8000"|' .env
sed -i '' 's|#SESSION_DOMAIN=""|SESSION_DOMAIN="localhost"|' .env

# Clear cache
php artisan config:clear

# Restart Vite for HMR
npm run dev
```
