<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function () {
    $db = 'down';
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = 'up';
    } catch (\Throwable $e) {
        $db = 'down';
    }

    return response()->json([
        'status'  => $db === 'up' ? 'ok' : 'degraded',
        'db'      => $db,
        'app'     => config('app.name'),
        'laravel' => app()->version(),
        'time'    => now()->toIso8601String(),
    ], $db === 'up' ? 200 : 503);
});

Route::get('/health', function () {
    $db = 'down';
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $db = 'up';
    } catch (\Throwable $e) {
        $db = 'down';
    }

    return response()->json([
        'status'  => $db === 'up' ? 'ok' : 'degraded',
        'db'      => $db,
        'app'     => config('app.name'),
        'laravel' => app()->version(),
        'time'    => now()->toIso8601String(),
    ], $db === 'up' ? 200 : 503);
});
