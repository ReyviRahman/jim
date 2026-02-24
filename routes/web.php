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
            
        Route::livewire('/riwayat-kehadiran', 'pages::dashboard.member.kehadiran')
            ->name('member.kehadiran.index');

        Route::livewire('/membership', 'pages::dashboard.member.membership')
        ->name('member.membership.index');

        // Route::livewire('/paket', 'pages::dashboard.member.package')
        //     ->name('member.paket.index');

        Route::livewire('/paket/{package}/checkout', 'pages::dashboard.member.package.checkout')
            ->name('member.paket.checkout');
    });

    Route::middleware('role:pt')->prefix('dashboard/pt')->group(function () {
        Route::livewire('/absensi', 'pages::dashboard.pt.absensi')
            ->name('pt.absensi');

        Route::livewire('/riwayat-kehadiran', 'pages::dashboard.pt.kehadiran')
            ->name('pt.kehadiran.index');
    });


    // GROUP 2: KHUSUS ADMIN
    // Middleware: harus login DAN role = admin
    Route::middleware('role:admin')->prefix('dashboard/admin')->group(function () {
        
        // --- Membership Management ---
        Route::livewire('/membership', 'pages::dashboard.admin.membership')
            ->name('admin.membership.index');

        Route::livewire('/membership/gabung', 'pages::dashboard.admin.membership.gabung')
            ->name('admin.membership.gabung');

        Route::livewire('/membership/paket/{user}', 'pages::dashboard.admin.membership.paket')
            ->name('admin.membership.paket');

        Route::livewire('/absensi', 'pages::dashboard.admin.absensi')
            ->name('admin.absensi.index');
        
        Route::livewire('/akun', 'pages::dashboard.admin.akun')
            ->name('admin.akun.index');

        Route::livewire('/akun/{user}', 'pages::dashboard.admin.akun.detail')
            ->name('admin.akun.detail');

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