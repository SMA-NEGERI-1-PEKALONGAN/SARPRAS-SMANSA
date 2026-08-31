<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| AREA PUBLIK
|--------------------------------------------------------------------------
| Bisa diakses semua orang, baik sudah login maupun belum.
*/

// Homepage
Volt::route('/', 'pages::user.home')->name('home');

// Home dengan URL /home
Volt::route('/home', 'pages::user.home');


/*
|--------------------------------------------------------------------------
| AREA GUEST
|--------------------------------------------------------------------------
| Hanya bisa diakses user yang BELUM login.
*/

Route::middleware('guest')->group(function () {
    Volt::route('/login', 'pages::auth.login')->name('login');
});


/*
|--------------------------------------------------------------------------
| PROSES LOGOUT
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->get('/logout', function () {
    Auth::logout();

    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');


/*
|--------------------------------------------------------------------------
| AREA USER & ROLE UMUM
|--------------------------------------------------------------------------
| Harus login dan memiliki salah satu role berikut.
*/

Route::middleware(['auth', 'role:siswa,guru/staff,admin'])->group(function () {

    Volt::route('/booking', 'pages::user.booking')->name('booking');

    Volt::route('/history', 'pages::user.history')->name('history');

    Volt::route('/account/settings', 'pages::user.settings')
        ->name('account.settings');
});


/*
|--------------------------------------------------------------------------
| AREA ADMIN
|--------------------------------------------------------------------------
| Harus login dan memiliki role admin.
*/

Route::prefix('admin')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

        Volt::route('/dashboard', 'pages::admin.dashboard')
            ->name('admin.dashboard');

        Volt::route('/user', 'pages::admin.users-management')
            ->name('admin.user');

        Volt::route('/room', 'pages::admin.room-management')
            ->name('admin.room');

        Volt::route('/item', 'pages::admin.item-management')
            ->name('admin.item');

        Volt::route('/booking', 'pages::admin.booking-management')
            ->name('admin.booking');
    });