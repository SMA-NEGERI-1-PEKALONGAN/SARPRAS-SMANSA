<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Input extends Component
{
    public ?string $label;
    public ?string $icon;
    public ?string $name;
    public ?string $type;

    public function __construct($label = null, $icon = null, $name = null, $type = 'text')
    {
        $this->label = $label;
        $this->icon = $icon;
        $this->name = $name;
        $this->type = $type;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.input');
    }
}