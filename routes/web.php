<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| AREA PUBLIK (Bisa Diakses Semua Orang: Tanpa Log In / Sudah Log In)
|--------------------------------------------------------------------------
*/
Volt::route('/', 'pages::user.home')->name('welcome');


/*
|--------------------------------------------------------------------------
| AREA GUEST (Hanya Untuk yang BELUM Log In)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Volt::route('/login', 'pages::auth.login')->name('login');
});


/*
|--------------------------------------------------------------------------
| PROSES LOGOUT (Hanya Untuk yang SUDAH Log In)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->get('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect('/login');
})->name('logout');


/*
|--------------------------------------------------------------------------
| AREA USER & ROLE UMUM (Harus Log In)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:siswa,guru/staff,admin'])->group(function () {
    Volt::route('/home', 'pages::user.home')->name('home');
    Volt::route('/booking', 'pages::user.booking')->name('booking'); // Menambahkan '/' di awal rute
    Volt::route('/history', 'pages::user.history')->name('history');
    Volt::route('/account/settings', 'pages::user.settings')->name('account.settings');
});


/*
|--------------------------------------------------------------------------
| AREA ADMIN (Harus Log In & Punya Role Admin)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth', 'role:admin'])->group(function () {
    Volt::route('/dashboard', 'pages::admin.dashboard')->name('admin.dashboard');
    Volt::route('/user', 'pages::admin.users-management')->name('admin.user');
    Volt::route('/room', 'pages::admin.room-management')->name('admin.room');
    Volt::route('/item', 'pages::admin.item-management')->name('admin.item');
    Volt::route('/booking', 'pages::admin.booking-management')->name('admin.booking');
});