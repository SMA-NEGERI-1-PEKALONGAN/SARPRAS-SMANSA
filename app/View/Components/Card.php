<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Card extends Component
{
    public ?string $title;
    public ?string $image;
    public bool $padding;

    public function __construct($title = null, $image = null, $padding = true)
    {
        $this->title = $title;
        $this->image = $image;
        $this->padding = $padding;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.card');
    }
}