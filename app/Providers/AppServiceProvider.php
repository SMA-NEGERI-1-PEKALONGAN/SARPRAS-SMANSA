<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Response Macros
        Response::macro('success', function ($data, $message = 'Berhasil', $statusCode = 200) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data'    => $data,
            ], $statusCode);
        });

        Response::macro('error', function ($message = 'Terjadi Kesalahan', $statusCode = 400, $errors = null) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors'  => $errors,
            ], $statusCode);
        });
    }
}