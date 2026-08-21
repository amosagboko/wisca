<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\LandingContent;
use App\Support\WiscaNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $preview = request()->boolean('preview');

        if (auth()->check() && ! $preview) {
            return redirect()->to(WiscaNavigation::homeRoute());
        }

        if ($preview && (! auth()->check() || ! auth()->user()->isAdmin())) {
            abort(403);
        }

        $school = School::query()->first();
        $landing = $school
            ? $school->landingContent()
            : LandingContent::resolve(null);

        return view('landing', [
            'school' => $school,
            'schoolName' => $school?->name ?? config('app.name', 'WISCA'),
            'logoUrl' => $school?->logoUrl(),
            'heroUrl' => $school?->landingHeroUrl() ?? asset('images/landing-hero.jpg'),
            'landing' => $landing,
        ]);
    }
}
