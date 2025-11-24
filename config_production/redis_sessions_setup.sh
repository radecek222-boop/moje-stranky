#!/bin/bash
# ==========================================
# Redis Session Handler Setup for WGS
# ==========================================
# Tento script nainstaluje a nakonfiguruje Redis
# jako session handler místo file-based sessions
#
# Spuštění: sudo bash redis_sessions_setup.sh
# ==========================================

set -e  # Exit on error

echo "=========================================="
echo "🚀 WGS Redis Session Handler Setup"
echo "=========================================="
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo "❌ Tento script musí být spuštěn jako root (sudo)"
   exit 1
fi

# ==========================================
# 1. INSTALACE REDIS
# ==========================================

echo "📦 Instaluji Redis server..."
apt-get update
apt-get install -y redis-server php-redis

echo "✅ Redis nainstalován"
echo ""

# ==========================================
# 2. KONFIGURACE REDIS
# ==========================================

echo "⚙️  Konfiguruji Redis..."

REDIS_CONF="/etc/redis/redis.conf"

# Backup original config
cp $REDIS_CONF ${REDIS_CONF}.backup.$(date +%Y%m%d_%H%M%S)

# Set supervised mode to systemd
sed -i 's/^supervised no/supervised systemd/' $REDIS_CONF

# Set maxmemory (2GB for sessions)
sed -i 's/^# maxmemory <bytes>/maxmemory 2gb/' $REDIS_CONF

# Set maxmemory policy (LRU = Least Recently Used)
# Když Redis dosáhne max paměti, smaže nejstarší sessions
sed -i 's/^# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' $REDIS_CONF

# Enable persistence (optional - pro session persistence across restarts)
# RDB snapshot každých 900s pokud alespoň 1 změna
sed -i 's/^# save ""/save 900 1/' $REDIS_CONF

# Bind to localhost only (security)
sed -i 's/^bind .*/bind 127.0.0.1 ::1/' $REDIS_CONF

# Disable protected mode (not needed with bind localhost)
sed -i 's/^protected-mode yes/protected-mode no/' $REDIS_CONF

echo "✅ Redis konfigurován"
echo ""

# ==========================================
# 3. START REDIS
# ==========================================

echo "🔄 Startuji Redis..."
systemctl enable redis-server
systemctl restart redis-server

# Check if Redis is running
if systemctl is-active --quiet redis-server; then
    echo "✅ Redis běží"
else
    echo "❌ Redis se nepodařilo nastartovat!"
    systemctl status redis-server
    exit 1
fi

echo ""

# ==========================================
# 4. TEST REDIS CONNECTION
# ==========================================

echo "🧪 Testuji Redis připojení..."
if redis-cli ping | grep -q PONG; then
    echo "✅ Redis odpovídá (PONG)"
else
    echo "❌ Redis neodpovídá!"
    exit 1
fi

echo ""

# ==========================================
# 5. KONFIGURACE PHP PRO REDIS SESSIONS
# ==========================================

echo "⚙️  Konfiguruji PHP pro Redis sessions..."

# PHP-FPM config (update pool)
PHP_FPM_POOL="/etc/php/8.4/fpm/pool.d/wgs.conf"

if [ -f "$PHP_FPM_POOL" ]; then
    echo "📝 Aktualizuji PHP-FPM pool config..."

    # Backup
    cp $PHP_FPM_POOL ${PHP_FPM_POOL}.backup.$(date +%Y%m%d_%H%M%S)

    # Zakomentovat file-based sessions
    sed -i 's/^php_value\[session.save_handler\] = files/; php_value[session.save_handler] = files/' $PHP_FPM_POOL
    sed -i 's/^php_value\[session.save_path\] = "\/var\/lib\/php\/sessions"/; php_value[session.save_path] = "\/var\/lib\/php\/sessions"/' $PHP_FPM_POOL

    # Přidat Redis sessions
    if ! grep -q "Redis sessions" $PHP_FPM_POOL; then
        cat >> $PHP_FPM_POOL << 'EOF'

; Redis sessions (enabled)
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379?database=1&timeout=2.5"
php_value[session.gc_maxlifetime] = 86400
EOF
    fi

    echo "✅ PHP-FPM pool aktualizován"
else
    echo "⚠️  Soubor $PHP_FPM_POOL nenalezen"
    echo "    Musíte ručně upravit PHP konfiguraci:"
    echo "    session.save_handler = redis"
    echo "    session.save_path = \"tcp://127.0.0.1:6379?database=1\""
fi

echo ""

# ==========================================
# 6. RESTART PHP-FPM
# ==========================================

echo "🔄 Restartuji PHP-FPM..."
systemctl restart php8.4-fpm

if systemctl is-active --quiet php8.4-fpm; then
    echo "✅ PHP-FPM restartován"
else
    echo "❌ PHP-FPM se nepodařilo restartovat!"
    systemctl status php8.4-fpm
    exit 1
fi

echo ""

# ==========================================
# 7. TEST PHP REDIS SESSIONS
# ==========================================

echo "🧪 Testuji PHP Redis sessions..."

# Create test PHP script
TEST_SCRIPT="/tmp/test_redis_session.php"
cat > $TEST_SCRIPT << 'EOF'
<?php
session_start();

// Set session variable
$_SESSION['redis_test'] = 'Redis session working!';
$_SESSION['timestamp'] = time();

// Check session handler
$handler = ini_get('session.save_handler');
$path = ini_get('session.save_path');

echo "Session Handler: $handler\n";
echo "Session Path: $path\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data: " . print_r($_SESSION, true) . "\n";

// Verify in Redis
if ($handler === 'redis') {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        $redis->select(1);

        $key = 'PHPREDIS_SESSION:' . session_id();
        $data = $redis->get($key);

        echo "Redis Key: $key\n";
        echo "Redis Data: " . ($data ? "EXISTS" : "NOT FOUND") . "\n";
        echo "\n✅ Redis sessions FUNGUJÍ!\n";
    } catch (Exception $e) {
        echo "\n❌ Chyba při připojení k Redis: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n❌ Session handler není Redis!\n";
}
EOF

php $TEST_SCRIPT
rm $TEST_SCRIPT

echo ""

# ==========================================
# 8. MONITORING SETUP
# ==========================================

echo "📊 Monitoring příkazy:"
echo ""
echo "  Redis status:"
echo "    systemctl status redis-server"
echo ""
echo "  Redis info:"
echo "    redis-cli info"
echo ""
echo "  Redis keys (sessions):"
echo "    redis-cli -n 1 keys 'PHPREDIS_SESSION:*' | wc -l"
echo ""
echo "  Redis memory usage:"
echo "    redis-cli info memory | grep used_memory_human"
echo ""
echo "  PHP-FPM status:"
echo "    systemctl status php8.4-fpm"
echo ""
echo "  Check session config:"
echo "    php -i | grep session.save"
echo ""

# ==========================================
# 9. CLEANUP OLD FILE SESSIONS
# ==========================================

echo ""
read -p "🗑️  Chcete smazat staré file-based sessions? (y/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🗑️  Mažu staré sessions..."
    rm -rf /var/lib/php/sessions/*
    echo "✅ Staré sessions smazány"
fi

echo ""
echo "=========================================="
echo "✅ HOTOVO!"
echo "=========================================="
echo ""
echo "Redis sessions jsou nyní aktivní."
echo ""
echo "Další kroky:"
echo "  1. Otestujte přihlášení do WGS aplikace"
echo "  2. Zkontrolujte že sessions fungují:"
echo "     redis-cli -n 1 keys 'PHPREDIS_SESSION:*'"
echo "  3. Monitorujte výkon:"
echo "     redis-cli --stat"
echo ""
echo "⚠️  DŮLEŽITÉ:"
echo "  - Všichni uživatelé budou odhlášeni (staré sessions)"
echo "  - Monitorujte Redis memory usage"
echo "  - Backupujte Redis data pokud potřebujete persistence"
echo ""

exit 0
