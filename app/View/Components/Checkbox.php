<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Checkbox extends Component
{
    public ?string $label;
    public ?string $description;
    public ?string $name;
    public bool $checked;

    public function __construct($label = null, $description = null, $name = null, $checked = false)
    {
        $this->label = $label;
        $this->description = $description;
        $this->name = $name;
        $this->checked = $checked;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.checkbox');
    }
}