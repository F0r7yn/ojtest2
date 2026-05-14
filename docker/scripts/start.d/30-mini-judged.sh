#!/bin/sh -eu

if [ -x /scripts/bin/mini_judged ]; then
    if ! pgrep -f -x "/scripts/bin/mini_judged" >/dev/null 2>&1; then
        nohup /scripts/bin/mini_judged >/var/log/mini_judged.log 2>&1 &
    fi
fi
