<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Avatar extends Component
{
    public function __construct(
        public ?string $src = null,
        public ?string $name = null,
        public string $size = 'md',
        public string $shape = 'xl', // rounded-xl, full, dsb
        public ?string $status = null, // online, busy, offline
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.avatar');
    }
}