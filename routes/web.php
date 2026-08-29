<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Models\User;
use Illuminate\Support\Facades\Auth;



/*
|--------------------------------------------------------------------------
| AREA PUBLIK NON MIDLEWARE
|--------------------------------------------------------------------------
*/
// public /
Volt::route('/', 'pages::user.home')->name('home');
Volt::route('/login', 'pages::auth.login')->name('login');
Volt::route('/home', 'pages::user.home')->name('home');
Volt::route('booking', 'pages::user.booking')->name('booking');
Volt::route('history', 'pages::user.history')->name('history');
Volt::route('account/settings', 'pages::user.settings')->name('account.settings');


Route::get('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect('/login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| AREA ADMIN
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth'])->group(function () {
    // =================================================================
    // 1. AKSES UNTUK SEMUA ROLE (Owner, Admin, Kasir)
    // =================================================================
    Route::middleware(['role:admin'])->group(function () {
        Volt::route('/dashboard', 'pages::admin.dashboard')->name('admin.dashboard');
        Volt::route('/user', 'pages::admin.users-management')->name('admin.user');
        Volt::route('/room', 'pages::admin.room-management')->name('admin.room');
        Volt::route('/item', 'pages::admin.item-management')->name('admin.item');
        Volt::route('/booking', 'pages::admin.booking-management')->name('admin.booking');
    });
});