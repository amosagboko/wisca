<?php

namespace App\Http\Controllers;

use App\Support\WiscaOperationalCatalog;
use Illuminate\View\View;

class ComingSoonActivityController extends Controller
{
    public function __invoke(string $activity): View
    {
        $item = WiscaOperationalCatalog::findActivity($activity);
        abort_unless($item, 404);

        return view('activities.coming-soon', [
            'activity' => $item,
        ]);
    }
}
