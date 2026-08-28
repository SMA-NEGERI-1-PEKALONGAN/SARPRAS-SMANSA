<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Badge extends Component
{
    public function __construct(
        public string $variant = 'primary', // primary, success, danger, warning, info, gray
        public string $size = 'md',        // sm, md, lg
        public string $type = 'soft',      // soft, solid, outline
        public bool $rounded = true,
        public ?string $icon = null
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.badge');
    }
}