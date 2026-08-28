<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class Header extends Component
{
    public object $user;
    public int $unreadCount;
    public Collection $latestNotifications;
    public array $searchItems = [];
    public Collection $cartItems;
    public int $cartTotal; 

    public function __construct()
    {
        // 1. Ambil Role User saat ini
        $userRole = auth()->check() ? auth()->user()->role : '';

        // Simulasi Data User
        $this->user = (object) [
            'name' => auth()->check() ? auth()->user()->name : 'Guest',
            'email' => auth()->check() ? auth()->user()->email : '',
            'avatar' => null, 
            'initials' => '',
            'role' => $userRole,
        ];

        $this->unreadCount = 3;

        // Simulasi Data Notifikasi
        $this->latestNotifications = collect([
            (object) [
                'read_at' => null,
                'created_at' => now()->subMinutes(5),
                'data' => [
                    'title' => 'Pesanan Baru',
                    'message' => 'Anda menerima pesanan baru dari Toko Cabang Jakarta.',
                    'icon' => 'ti-shopping-cart',
                    'url' => '#'
                ]
            ],
            (object) [
                'read_at' => null,
                'created_at' => now()->subHour(),
                'data' => [
                    'title' => 'Server Re-boot',
                    'message' => 'Sistem mendeteksi reboot otomatis pada server pusat.',
                    'icon' => 'ti-server',
                    'url' => '#'
                ]
            ],
            (object) [
                'read_at' => now(),
                'created_at' => now()->subDays(1),
                'data' => [
                    'title' => 'Update Aplikasi',
                    'message' => 'Versi v2.4.0 sekarang sudah tersedia untuk dideploy.',
                    'icon' => 'ti-refresh',
                    'url' => '#'
                ]
            ]
        ]);

        // Simulasi Data Keranjang
        $this->cartItems = collect([
            (object) [
                'id' => 1,
                'name' => 'Macbook Pro M3 Max',
                'price' => 52000000,
                'qty' => 1,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=100&q=80'
            ],
            (object) [
                'id' => 2,
                'name' => 'Sony WH-1000XM5',
                'price' => 5999000,
                'qty' => 2,
                'image' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100&q=80'
            ]
        ]);

        $this->cartTotal = $this->cartItems->sum(fn($item) => $item->price * $item->qty);

         // ====================================================================
        // 2. GENERATE SEARCH ITEMS DINAMIS BERDASARKAN MENU & ROLE
        // ====================================================================
        
        // Copy array menu dari Sidebar.php Anda di sini
        $allMenus = [
            [
                'title' => 'Menu Utama',
                'items' => [
                    [
                        'name' => 'Dashboard', 
                        'icon' => 'smart-home', 
                        'activePattern' => 'admin.dashboard',
                        'route' => 'admin.dashboard',
                        'roles' => ['admin',]
                    ],
                ]
            ],
            // Jika Anda sudah membuka komentar menu lain di Sidebar, 
            // pastikan menaruh array yang sama persis di sini.
        ];

        // Looping untuk membuat daftar pencarian
        foreach ($allMenus as $group) {
            foreach ($group['items'] as $item) {
                // Cek apakah parent menu diizinkan untuk role user
                if (isset($item['roles']) && !in_array($userRole, $item['roles'])) {
                    continue; // Jika tidak ada akses, lewati
                }

                // Jika menu punya route langsung (bukan dropdown)
                if (isset($item['route'])) {
                    $this->searchItems[] = [
                        'title' => $item['name'],
                        'category' => 'Menu / ' . $group['title'],
                        'icon' => 'ti-' . $item['icon'], // Sesuaikan penamaan icon dengan format Tabler Icon (ti-...)
                        // Gunakan Route::has untuk mencegah error jika route belum dibuat di web.php
                        'url' => Route::has($item['route']) ? route($item['route']) : '#',
                    ];
                }

                // Jika menu punya sub-menu (dropdown)
                if (isset($item['subItems'])) {
                    foreach ($item['subItems'] as $subItem) {
                        // Cek apakah sub-menu diizinkan untuk role user
                        if (!isset($subItem['roles']) || in_array($userRole, $subItem['roles'])) {
                            $this->searchItems[] = [
                                'title' => $subItem['name'],
                                'category' => $group['title'] . ' / ' . $item['name'],
                                'icon' => 'ti-corner-down-right', // Icon penanda sub-menu
                                'url' => Route::has($subItem['route']) ? route($subItem['route']) : '#',
                            ];
                        }
                    }
                }
            }
        }
        
        // Anda juga bisa menambahkan item pencarian statis tambahan di luar menu (misal: produk)
        // $this->searchItems[] = ['title' => 'Macbook Pro M3', 'category' => 'Product', 'icon' => 'ti-device-laptop', 'url' => '#'];
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.layouts.header');
    }
}