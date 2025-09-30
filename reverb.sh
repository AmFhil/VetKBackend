#!/bin/bash
cd /home/vetkonec/vetkonect.text.dev/vetkonect

# Check if reverb is already running
if ! pgrep -f "artisan reverb:start" > /dev/null
then
  /usr/local/bin/php artisan reverb:start --port=8080 >> storage/logs/reverb.log 2>&1 &
fi
