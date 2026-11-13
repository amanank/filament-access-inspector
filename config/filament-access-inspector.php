<?php

return [
    'permission' => env('FILAMENT_ACCESS_INSPECTOR_PERMISSION', 'inspect_access'),
    'cache_ttl' => 60 * 24,
    'user_model' => env('FILAMENT_ACCESS_INSPECTOR_USER_MODEL'),
];
