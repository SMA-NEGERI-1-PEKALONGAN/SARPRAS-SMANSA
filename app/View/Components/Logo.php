<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Logo extends Component
{
    public string $size;
    public bool $withText;

    /**
     * @param string $size (sm, md, lg)
     * @param bool $withText
     */
    public function __construct($size = 'md', $withText = true)
    {
        $this->size = $size;
        $this->withText = $withText;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.logo');
    }
}