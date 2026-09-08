<?php

return [
    'auth' => (int) env('AUTH_RATE_LIMIT', 1000),
    'exam' => (int) env('EXAM_RATE_LIMIT', 1000),
];
