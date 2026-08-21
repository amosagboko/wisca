<?php

namespace App\View\Components;

use App\Support\WiscaNavigation;
use Illuminate\View\Component;
use Illuminate\View\View;

class PortalLayout extends Component
{
    public string $title;

    public array $breadcrumbs;

    public function __construct(
        ?string $title = null,
        array $breadcrumbs = [],
    ) {
        $this->title = $title ?: WiscaNavigation::currentTitle();
        $this->breadcrumbs = $breadcrumbs !== [] ? $breadcrumbs : [$this->title];
    }

    public function render(): View
    {
        return view('layouts.portal');
    }
}
