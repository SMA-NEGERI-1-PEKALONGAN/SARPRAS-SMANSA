<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Sidebar extends Component
{
    public array $menuGroups;

    public function __construct()
    {
        // Ambil role user yang sedang login. Sesuaikan dengan field di database Anda.
        // Jika belum login, kita beri default kosong agar tidak error.
        $userRole = auth()->check() ? auth()->user()->role : '';

        // 1. Definisikan semua menu beserta role yang diizinkan
        // Asumsi role: 'admin', 'kasir', 'owner' (Silakan sesuaikan dengan database Anda)
        $allMenus = [
            [
                'title' => 'Menu Utama',
                'items' => [
                    [
                        'name' => 'Dashboard', 
                        'icon' => 'smart-home', 
                        'activePattern' => 'admin.dashboard',
                        'route' => 'admin.dashboard',
                        'roles' => ['admin'],
                    ],
                ]
            ],
            [
                'title' => 'Aplikasi',
                'items' => [
                    [
                        'name' => 'Master Data',
                        'icon' => 'ti ti-database',
                        'activePattern' => 'admin.master-data',
                        'roles' => ['admin'], 
                        'subItems' => [
                            [
                                'name' => 'Ruangan',
                                'icon' => 'ti ti-database',
                                'activePattern' => 'admin.room',
                                'route' => 'admin.room',
                                'roles' => ['admin'],
                            ],
                            [
                                'name' => 'Barang',
                                'icon' => 'ti ti-database',
                                'activePattern' => 'admin.item',
                                'route' => 'admin.item',
                                'roles' => ['admin'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Peminjaman', 
                        'icon' => 'ti ti-layers-subtract', 
                        'activePattern' => 'admin.booking',
                        'route' => 'admin.booking',
                        'roles' => ['admin'],
                    ],
                ]
            ],
            
            [
                'title' => 'Pengaturan & Pengguna',
                'items' => [
                    [
                        'name' => 'Users', 
                        'icon' => 'users', 
                        'activePattern' => 'admin.user',
                        'route' => 'admin.user',
                        'roles' => ['admin'], // Hanya admin
                    ],
                    
                ]
            ],
            
            [
                'title' => 'Laporan',
                'items' => [
                    [
                        'name' => 'Laporan',
                        'icon' => 'ti ti-chart-pie',
                        'activePattern' => 'admin.report',
                        'roles' => ['admin'], 
                        'subItems' => [
                            [
                                'name' => 'Laporan Peminjaman',
                                'icon' => 'ti ti-chart-pie',
                                'activePattern' => 'admin.booking-report',
                                'route' => 'admin.booking-report',
                                'roles' => ['admin'],
                            ],
                        ],
                    ],
                ]
            ],

        ];

        // 2. Filter menu berdasarkan Role User
        $this->menuGroups = $this->filterMenusByRole($allMenus, $userRole);
    }

    /**
     * Fungsi untuk memfilter array menu berdasarkan role
     */
    private function filterMenusByRole(array $groups, string $userRole): array
    {
        $filteredGroups = [];

        foreach ($groups as $group) {
            $filteredItems = [];

            foreach ($group['items'] as $item) {
                // Cek apakah item ini diizinkan untuk role user saat ini
                if (isset($item['roles']) && !in_array($userRole, $item['roles'])) {
                    continue; // Lewati item ini (tidak dimasukkan ke menu)
                }

                // Jika item memiliki subItems, filter juga subItems-nya
                if (isset($item['subItems'])) {
                    $filteredSubItems = [];
                    foreach ($item['subItems'] as $subItem) {
                        if (!isset($subItem['roles']) || in_array($userRole, $subItem['roles'])) {
                            $filteredSubItems[] = $subItem;
                        }
                    }
                    
                    // Update subItems dengan yang sudah difilter
                    $item['subItems'] = $filteredSubItems;

                    // Jika setelah difilter ternyata subItems-nya kosong, jangan tampilkan parent-nya
                    if (empty($item['subItems'])) {
                        continue;
                    }
                }

                $filteredItems[] = $item;
            }

            // Jika group ini masih memiliki item setelah difilter, masukkan ke hasil akhir
            if (!empty($filteredItems)) {
                $group['items'] = $filteredItems;
                $filteredGroups[] = $group;
            }
        }

        return $filteredGroups;
    }

    public function render(): View|Closure|string
    {
        return view('components.admin.layouts.sidebar');
    }
}