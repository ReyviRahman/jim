<?php

use Illuminate\Support\Facades\Route;

// --- HALAMAN PUBLIK (Bisa diakses siapa saja) ---
Route::livewire('/', 'pages::index')
    ->name('home');

// --- HALAMAN GUEST (Hanya bisa diakses jika BELUM login) ---
Route::middleware('guest')->group(function () {
    
    Route::livewire('/login', 'pages::login')
        ->name('login');
        
    // Pendaftaran biasanya untuk orang yang belum punya akun
    Route::livewire('/pendaftaran/member', 'pages::registration.registration')
        ->name('member.register');
});


// --- HALAMAN YANG BUTUH LOGIN (AUTH) ---
Route::middleware('auth')->group(function () {

    // GROUP 1: KHUSUS MEMBER
    // Middleware: harus login DAN role = member
    Route::middleware('role:member')->prefix('dashboard/member')->group(function () {
        
        Route::livewire('/home', 'pages::dashboard.member.home')
            ->name('member.dashboard');

        Route::livewire('/membership', 'pages::dashboard.member.membership')
        ->name('member.membership.index');

        Route::livewire('/paket', 'pages::dashboard.member.package')
            ->name('member.paket.index');

        Route::livewire('/paket/{package}/checkout', 'pages::dashboard.member.package.checkout')
            ->name('member.paket.checkout');
    });


    // GROUP 2: KHUSUS ADMIN
    // Middleware: harus login DAN role = admin
    Route::middleware('role:admin')->prefix('dashboard/admin')->group(function () {
        
        // --- Membership Management ---
        Route::livewire('/membership/create', 'pages::dashboard.admin.membership.create')
            ->name('admin.membership.create');

        Route::livewire('/membership', 'pages::dashboard.admin.membership')
            ->name('admin.membership.index');

        Route::livewire('/absensi', 'pages::dashboard.admin.absensi')
            ->name('admin.absensi.index');

        // --- Package Management ---
        // Saya kelompokkan lagi dengan prefix 'package' biar URL rapi
        Route::prefix('package')->group(function () {
            
            Route::livewire('/', 'pages::dashboard.admin.package')
                ->name('admin.packages.index'); // List Data

            Route::livewire('/create', 'pages::dashboard.admin.package.create')
                ->name('admin.packages.create'); // Form Input

            Route::livewire('/{package}/edit', 'pages::dashboard.admin.package.edit')
                ->name('admin.packages.edit'); // Edit
        });
    });

});