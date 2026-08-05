<?php

return [
    'attendance_timezone' => env('TIMEPAY_ATTENDANCE_TIMEZONE', 'Asia/Manila'),
    'default_geofence_radius' => (int) env('TIMEPAY_DEFAULT_GEOFENCE_RADIUS', 100),
];
