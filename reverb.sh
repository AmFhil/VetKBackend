#!/bin/sh
APP_DIR="/home/vetkonec/vetkonect.text.dev/vetkonect"
LOG_FILE="$APP_DIR/storage/logs/reverb.log"
PHP_BIN="/usr/local/bin/php"

cd "$APP_DIR" || exit

# Ensure logs folder exists
mkdir -p "$APP_DIR/storage/logs"

# Rotate logs if bigger than 10MB
if [ -f "$LOG_FILE" ] && [ $(stat -c%s "$LOG_FILE") -ge 10485760 ]; then
  mv "$LOG_FILE" "$APP_DIR/storage/logs/reverb-$(date +%F-%H%M).log"
fi

# Ensure fresh log file
touch "$LOG_FILE"
chmod 664 "$LOG_FILE"

echo "[$(date)] Checking for existing Reverb processes..." >> "$LOG_FILE"

# If already running, exit
if pgrep -f "artisan reverb:start" > /dev/null; then
    echo "[$(date)] Reverb already running, exiting." >> "$LOG_FILE"
    exit 0
fi

# Start new instance
echo "[$(date)] Starting new Reverb process..." >> "$LOG_FILE"
$PHP_BIN artisan reverb:start --port=8085 >> "$LOG_FILE" 2>&1 &
PID=$!
sleep 2

# Verify startup
if ps -p $PID > /dev/null; then
    echo "[$(date)] Reverb started successfully with PID $PID" >> "$LOG_FILE"
else
    echo "[$(date)] Reverb failed to start. Check errors above." >> "$LOG_FILE"
fi
