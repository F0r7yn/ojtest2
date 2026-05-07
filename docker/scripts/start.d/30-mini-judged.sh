#!/bin/sh -eu

if [ -x /scripts/bin/mini_judged ]; then
    if ! ps -eo args | grep -F "/scripts/bin/mini_judged" | grep -v grep >/dev/null 2>&1; then
        nohup /scripts/bin/mini_judged >/var/log/mini_judged.log 2>&1 &
    fi
fi
