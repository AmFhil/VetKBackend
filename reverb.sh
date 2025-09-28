#!/bin/bash
cd /home/vetkonec/vetkonect.text.dev/vetkonect
php artisan reverb:start --port=8080 >> storage/logs/reverb.log 2>&1

# pgrep -f "artisan reverb:start" > /dev/null || /usr/local/bin/php /home/vetkonec/vetkonect.text.dev/vetkonect/storage/logs/reverb.log 2>&1 &
