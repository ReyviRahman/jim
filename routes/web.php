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
    Route::middleware('role:admin,kasir_gym')->prefix('dashboard/admin')->group(function () {
        Route::livewire('/absensi', 'pages::dashboard.admin.absensi')
            ->name('admin.absensi.index');

        Route::livewire('/penjualan', 'pages::dashboard.admin.penjualan.index')->name('admin.penjualan.index');
        Route::livewire('/pengeluaran', 'pages::dashboard.admin.pengeluaran.index')->name('admin.pengeluaran.index');
        Route::livewire('/pengeluaran/create', 'pages::dashboard.admin.pengeluaran.create')->name('admin.pengeluaran.create');

        // --- Membership Management ---
        Route::livewire('/membership', 'pages::dashboard.admin.membership')
            ->name('admin.membership.index');

        Route::livewire('/membership/gabung', 'pages::dashboard.admin.membership.gabung')
            ->name('admin.membership.gabung');

        Route::livewire('/membership/gabung/daftar-member', 'pages::dashboard.admin.membership.daftar-member')
            ->name('admin.membership.gabung.daftar-member');

        Route::livewire('/membership/paket', 'pages::dashboard.admin.membership.paket')
            ->name('admin.membership.paket');

        Route::livewire('/akun/member', 'pages::dashboard.admin.akun.member.index')
            ->name('admin.akun.member.index');

        Route::livewire('/akun/member/create', 'pages::dashboard.admin.akun.member.create')
            ->name('admin.akun.member.create');

        Route::livewire('/akun/member/{user}/edit', 'pages::dashboard.admin.akun.member.edit')
            ->name('admin.akun.member.edit');

        // Route::livewire('/akun/{user}', 'pages::dashboard.admin.akun.detail')
        //     ->name('admin.akun.detail');

        Route::livewire('/membership/cicilan', 'pages::dashboard.admin.cicilan.index')->name('admin.cicilan.index');
        Route::livewire('/membership/cicilan/{membership}/pay', 'pages::dashboard.admin.cicilan.pay')->name('admin.cicilan.pay');
        
        // Route::livewire('/renew', 'pages::dashboard.admin.renew.index')->name('admin.renew.index');
        // Route::livewire('/renew/{id}/create', 'pages::dashboard.admin.renew.create')->name('admin.renew.create');
    });

    Route::middleware('role:admin')->prefix('dashboard/admin')->group(function () {
        Route::livewire('/pengeluaran/{expense}/edit', 'pages::dashboard.admin.pengeluaran.edit')->name('admin.pengeluaran.edit');

        Route::livewire('/akun/admin', 'pages::dashboard.admin.akun.admin.index')
            ->name('admin.akun.admin.index');

        Route::livewire('/akun/admin/create', 'pages::dashboard.admin.akun.admin.create')
            ->name('admin.akun.admin.create');

        Route::livewire('/akun/admin/{user}/edit', 'pages::dashboard.admin.akun.admin.edit')
            ->name('admin.akun.admin.edit');

        Route::livewire('/akun/trainer', 'pages::dashboard.admin.akun.trainer.index')
            ->name('admin.akun.trainer.index');

        Route::livewire('/akun/trainer/create', 'pages::dashboard.admin.akun.trainer.create')
            ->name('admin.akun.trainer.create');

        Route::livewire('/akun/trainer/{user}/edit', 'pages::dashboard.admin.akun.trainer.edit')
            ->name('admin.akun.trainer.edit');

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