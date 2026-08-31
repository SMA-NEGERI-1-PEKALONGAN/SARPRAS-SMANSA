<?php

use Livewire\Component;
use App\Models\SystemNotification;

new class extends Component
{
    public function getNotificationsProperty()
    {
        return SystemNotification::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        return SystemNotification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->count();
    }

    public function markAllAsRead(): void
    {
        SystemNotification::query()
            ->where('user_id', auth()->id())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
            ]);
    }

    public function openNotification(int $id): mixed
    {
        $notification = SystemNotification::query()
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$notification) {
            return null;
        }

        $notification->update([
            'is_read' => true,
        ]);

        return $notification->url
            ? redirect($notification->url)
            : redirect()->route('history');
    }
};
?>
<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="open = false"
    class="relative">
    <div wire:poll.15s>
        <button
            type="button"
            @click="open = !open"
            class="relative flex items-center justify-center {{ auth()->user()->role == 'admin' ? 'w-10 h-10 text-gray-500 transition-all duration-300 rounded-xl hover:bg-gray-100 active:scale-90 dark:text-gray-400 dark:hover:bg-gray-900' : 'h-10 w-10 rounded-xl transition-all hover:bg-slate-100 dark:hover:bg-slate-800' }}"
            aria-label="Notifikasi"
        >
            <i class="{{ auth()->user()->role == 'admin' ? 'text-[15px] fa-regular fa-bell' : 'text-[15px] transition-transform duration-300 group-hover:rotate-12 fa-solid fa-bell' }}"></i>

            @if($this->unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-[18px] h-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[8px] font-bold text-white ring-2 ring-white dark:ring-slate-950">
                    {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
                </span>
            @endif
        </button>
    </div>

    <!-- Dropdown Notifikasi (Diperbaiki responsifnya) -->
    <div
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="fixed inset-x-4 top-16 z-[100] mx-auto max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:absolute sm:inset-auto sm:right-0 sm:top-full sm:mt-3 sm:w-[360px] sm:max-w-none"
    >
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Notifikasi</h3>
                <p class="text-[10px] text-slate-400">Pemberitahuan terbaru</p>
            </div>

            @if($this->unreadCount > 0)
                <button
                    type="button"
                    wire:click="markAllAsRead"
                    class="text-[10px] font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                >
                    Tandai semua dibaca
                </button>
            @endif
        </div>

        <div class="max-h-[380px] sm:max-h-[420px] overflow-y-auto">
            @forelse($this->notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="openNotification({{ $notification->id }})"
                    class="block w-full border-b border-slate-100 px-4 py-3 text-left transition-colors dark:border-slate-800 {{ !$notification->is_read ? 'bg-blue-50/70 dark:bg-blue-500/5' : 'bg-white dark:bg-slate-900' }} hover:bg-slate-50 dark:hover:bg-slate-800"
                >
                    <div class="flex gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ !$notification->is_read ? 'bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' }}">
                            <i class="fa-solid fa-bell"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs {{ !$notification->is_read ? 'font-bold text-slate-900 dark:text-white' : 'font-semibold text-slate-700 dark:text-slate-200' }}">
                                    {{ $notification->title }}
                                </p>

                                @if(!$notification->is_read)
                                    <span class="shrink-0 rounded-full bg-blue-100 px-1.5 py-0.5 text-[8px] font-bold text-blue-600 dark:bg-blue-500/20 dark:text-blue-400">
                                        BARU
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 line-clamp-2 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $notification->message }}
                            </p>

                            <p class="mt-1.5 text-[9px] text-slate-400">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>

                        @if(!$notification->is_read)
                            <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>
                        @endif
                    </div>
                </button>
            @empty
                <div class="px-6 py-10 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <i class="fa-regular fa-bell-slash"></i>
                    </div>

                    <p class="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Tidak ada notifikasi
                    </p>

                    <p class="mt-1 text-[10px] text-slate-400">
                        Belum ada pemberitahuan baru.
                    </p>
                </div>
            @endforelse
        </div>

        @if($this->notifications->count())
            <div class="border-t border-slate-100 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-950">
                <a
                    @if(auth()->user()->role === 'admin') href="{{ route('admin.booking') }}" @else href="{{ route('history') }}" @endif
                    wire:navigate
                    @click="open = false"
                    class="flex items-center justify-center gap-2 text-[10px] font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400"
                >
                    Lihat semua data
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        @endif
    </div>
</div>