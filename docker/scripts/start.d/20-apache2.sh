#! /bin/sh -eu

apache_escape() {
    printf '%s' "$1" | sed 's/[\\"]/\\&/g'
}

{
    for name in DEEPSEEK_API_KEY DEEPSEEK_BASE_URL DEEPSEEK_MODEL DEEPSEEK_MOCK DEEPSEEK_INSECURE_SSL; do
        value=$(printenv "$name" || true)
        if [ -n "$value" ]; then
            printf 'SetEnv %s "%s"\n' "$name" "$(apache_escape "$value")"
        fi
    done
} >/etc/apache2/conf-available/deepseek-env.conf

a2enconf deepseek-env >/dev/null 2>&1 || true
service apache2 restart
