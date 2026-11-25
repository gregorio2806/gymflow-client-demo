<?php

return [
    // Applies per member and check-in branch; zero disables the duplicate window.
    'duplicate_window_minutes' => (int) env('ATTENDANCE_DUPLICATE_WINDOW_MINUTES', 5),
];
