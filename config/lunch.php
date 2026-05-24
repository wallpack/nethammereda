<?php

return [
    'business_timezone' => env('LUNCH_BUSINESS_TIMEZONE', 'Asia/Yekaterinburg'),

    'order_deadline_time' => env('LUNCH_ORDER_DEADLINE_TIME', '17:00'),

    'demo_reset_allowed' => filter_var(env('DEMO_RESET_ALLOWED', false), FILTER_VALIDATE_BOOLEAN),

    // Set only during the guarded demo:reset command execution.
    'demo_reset_execution_authorized' => false,
];
