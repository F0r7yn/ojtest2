<?php
/**
 * DeepSeek client (OpenAI-compatible chat completions)
 */

function deepseek_get_config() {
    $env_api_key = getenv('DEEPSEEK_API_KEY');
    $env_base_url = getenv('DEEPSEEK_BASE_URL');
    $env_model = getenv('DEEPSEEK_MODEL');
    $env_mock = getenv('DEEPSEEK_MOCK');

    $api_key = $env_api_key !== false ? trim($env_api_key) : '';
    $base_url = $env_base_url !== false ? trim($env_base_url) : '';
    $model = $env_model !== false ? trim($env_model) : '';
    $mock = $env_mock !== false ? trim($env_mock) : '';

    if ($base_url === '') {
        $base_url = 'https://api.deepseek.com';
    }
    if ($model === '') {
        // Official default model in current DeepSeek docs.
        $model = 'deepseek-v4-flash';
    }

    // If API key is configured, always prefer real API call even when mock flag is left on.
    // This prevents accidental fixed mock code responses in production.
    $mock_enabled = ($mock === '1' && $api_key === '');
    return array(
        'api_key' => $api_key,
        'base_url' => rtrim($base_url, '/'),
        'model' => $model,
        'mock_enabled' => $mock_enabled,
    );
}

function deepseek_mock_response() {
    $mock_code = <<<PY
import sys
data = sys.stdin.read().strip().split()
nums = list(map(int, data))
if nums:
    print(sum(nums))
PY;
    return array(
        'ok' => true,
        'content' => $mock_code,
        'model' => 'deepseek-mock',
        'error' => '',
    );
}

function deepseek_chat_completion($messages) {
    $cfg = deepseek_get_config();
    if ($cfg['mock_enabled']) {
        return deepseek_mock_response();
    }
    if (!function_exists('curl_init')) {
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'curl extension is not available',
        );
    }

    if ($cfg['api_key'] === '') {
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'DEEPSEEK_API_KEY is missing',
        );
    }

    $payload = array(
        'model' => $cfg['model'],
        'messages' => $messages,
        'temperature' => 0.6,
        'top_p' => 0.95,
        'stream' => false,
    );

    $ch = curl_init($cfg['base_url'] . '/chat/completions');
    if ($ch === false) {
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'Failed to initialize curl',
        );
    }

    $json_body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['api_key'],
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Local development environments may have incomplete CA trust chains.
    // Keep strict verification by default, but allow opt-out explicitly.
    $env_insecure = getenv('DEEPSEEK_INSECURE_SSL');
    if ($env_insecure !== false && trim($env_insecure) === '1') {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'DeepSeek request failed: ' . $err,
        );
    }

    $http_code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    curl_close($ch);

    if ($http_code !== 200) {
        $body = trim($resp);
        if (strlen($body) > 500) {
            $body = substr($body, 0, 500) . '...';
        }
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'DeepSeek HTTP ' . $http_code . ': ' . $body,
        );
    }

    $decoded = json_decode($resp, true);
    if (!is_array($decoded)) {
        return array(
            'ok' => false,
            'content' => '',
            'model' => $cfg['model'],
            'error' => 'DeepSeek response JSON parse failed',
        );
    }

    $content = '';
    if (isset($decoded['choices'][0]['message']['content'])) {
        $content = trim($decoded['choices'][0]['message']['content']);
    }
    if ($content === '') {
        return array(
            'ok' => false,
            'content' => '',
            'model' => isset($decoded['model']) ? $decoded['model'] : $cfg['model'],
            'error' => 'DeepSeek returned empty content',
        );
    }

    return array(
        'ok' => true,
        'content' => $content,
        'model' => isset($decoded['model']) ? $decoded['model'] : $cfg['model'],
        'error' => '',
    );
}
