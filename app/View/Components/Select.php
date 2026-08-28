<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Select extends Component
{
    public ?string $label;
    public ?string $icon;
    public ?string $name;
    public ?string $placeholder;

    public function __construct($label = null, $icon = null, $name = null, $placeholder = 'Pilih opsi...')
    {
        $this->label = $label;
        $this->icon = $icon;
        $this->name = $name;
        $this->placeholder = $placeholder;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.utils.select');
    }
}