<?php

use App\Core\Env;

return [
    // Empty provider means the optional language model integration is disabled.
    'provider' => (string) Env::get('AI_PROVIDER', ''),
    'api_key' => (string) Env::get('AI_API_KEY', ''),
    'model' => (string) Env::get('AI_MODEL', 'claude-opus-5-5'),
    'base_url' => (string) Env::get('AI_BASE_URL', ''),
    'effort' => (string) Env::get('AI_EFFORT', 'medium'),
    'timeout' => (int) Env::get('AI_TIMEOUT_SECONDS', 45),
    'max_calls_per_user_per_day' => (int) Env::get('AI_MAX_CALLS_PER_USER_PER_DAY', 40),
];
