<?php

return [
    'auth' => (int) env('AUTH_RATE_LIMIT', 1000),
    'exam' => (int) env('EXAM_RATE_LIMIT', 1000),
    // Hot write endpoints (answer/violation POSTs), keyed per session + IP.
    'exam_answers' => (int) env('EXAM_ANSWERS_RATE_LIMIT', 60),
];
