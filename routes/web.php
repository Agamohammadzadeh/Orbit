<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'pages::dashboard')->name('dashboard');

    Route::livewire('/settings/profile', 'pages::settings.profile')->name('settings.profile');

    Route::middleware('can:users.manage')->prefix('team')->group(function () {
        Route::livewire('/', 'pages::team.index')->name('team');
        Route::livewire('/roles', 'pages::team.roles')->name('team.roles');
    });

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
