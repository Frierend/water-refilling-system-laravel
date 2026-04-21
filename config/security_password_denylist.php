<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Local / Offline Common Password Denylist
    |--------------------------------------------------------------------------
    |
    | This denylist is intentionally stored in-repository so password checks
    | remain deterministic in local, offline, and air-gapped environments.
    | Keep entries lowercase for readability; runtime checks normalize case.
    |
    */

    'password',
    'password123',
    'qwerty',
    'qwerty123',
    'letmein',
    'welcome',
    'admin123',
    '12345678',
    'abc12345',
    'iloveyou',
];
