<?php

return [
    // Optional alternative to GEMINI_API_KEY and GEMINI_MODEL environment variables.
    // Copy this file outside htdocs, fill it in privately, then set GEMINI_CONFIG_PATH.
    'api_key' => '',
    // Optional; defaults to gemini-3-flash-preview when unset. If a configured model is
    // unavailable, the API selects an available text-generation Flash model.
    'model' => '',
];
