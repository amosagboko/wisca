<?php

namespace App\Support;

use App\Models\Kpi;

class ExamPassMark
{
    public static function percent(?Kpi $kpi = null): float
    {
        $kpi ??= Kpi::where('code', 'AE-02')->first();

        return (float) ($kpi?->config['pass_mark'] ?? 50);
    }
}
