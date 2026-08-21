<?php

/**
 * WISCA Board rules — thresholds independently configurable (Excel §3).
 * Achievement rates are decimals (1.0 = 100% of target).
 */
return [

    'status_thresholds' => [

        // A. KPI status (kpi_periodic_data.status)
        'kpi' => [
            'on_track' => 1.0,
            'needs_attention' => 0.90,
        ],

        // B. Pillar headline (EXCEEDING / ON TRACK / NEEDS ATTENTION)
        'pillar_headline' => [
            'exceeding' => 1.0,
            'on_track' => 0.90,
        ],

        // C. Overall health (HEALTHY / SATISFACTORY / CRITICAL)
        'overall_health' => [
            'healthy' => 0.95,
            'satisfactory' => 0.90,
        ],

    ],

];
