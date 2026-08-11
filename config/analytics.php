<?php

return [
    'report_disk' => env('ANALYTICS_REPORT_DISK', 'local'),
    'minimum_aggregate_cell_size' => (int) env('ANALYTICS_MINIMUM_AGGREGATE_CELL_SIZE', 5),
];
