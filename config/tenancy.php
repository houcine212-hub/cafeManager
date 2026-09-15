<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Console Bypass Commands
    |--------------------------------------------------------------------------
    | الأوامر الوحيدة ديال Artisan المسموح ليها بقراءة وكتابة الجداول
    | بلا سياق tenant محدد (ممنوع إضافة queue:work هنا).
    */
    'console_bypass_commands' => [
        'migrate',
        'migrate:fresh',
        'migrate:rollback',
        'migrate:refresh',
        'migrate:reset',
        'db:seed',
        'db:wipe',
    ],
];
