<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Button extends Component
{
    public string $variant;
    public string $size;
    public ?string $icon;

    public function __construct($variant = 'primary', $size = 'md', $icon = null)
    {
        $this->variant = $variant;
        $this->size = $size;
        $this->icon = $icon;
    }

    public function render(): View|Closure|string
    {
        // Definisi warna berdasarkan varian
        $variants = [
            'primary'   => 'bg-indigo-600 text-white hover:bg-indigo-700 shadow-lg shadow-indigo-500/25',
            'secondary' => 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 shadow-sm',
            'danger'    => 'bg-rose-600 text-white hover:bg-rose-700 shadow-lg shadow-rose-500/25',
            'success'   => 'bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-500/25',
            'ghost'     => 'bg-transparent text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800',
        ];

        // Definisi ukuran
        $sizes = [
            'sm' => 'px-4 py-2 text-xs',
            'md' => 'px-6 py-2.5 text-sm',
            'lg' => 'px-8 py-4 text-base',
        ];

        $variantClass = $variants[$this->variant] ?? $variants['primary'];
        $sizeClass = $sizes[$this->size] ?? $sizes['md'];
        return view('components.admin.utils.button', compact('variantClass', 'sizeClass'));
    }
}