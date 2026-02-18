<?php

use Illuminate\Support\Facades\Route;

// Halaman Depan / Landing
Route::livewire('/', 'pages::index')
    ->name('home');

// Login
Route::livewire('/login', 'pages::login')
    ->name('login');

// User Management (Umum)
Route::livewire('/users/create', 'pages::users.create')
    ->name('users.create');

// Pendaftaran (Khusus Member)
Route::livewire('/pendaftaran/member', 'pages::registration.registration')
    ->name('member.register');

// --- DASHBOARD MEMBER ---
Route::livewire('/dashboard/member/home', 'pages::dashboard.member.home')
    ->name('member.dashboard');

// --- DASHBOARD ADMIN ---
// 1. Membership (Transaksi Member)
Route::livewire('/dashboard/admin/membership/create', 'pages::dashboard.admin.membership.create')
    ->name('admin.membership.create');

// 2. Package (Master Data Paket)
Route::livewire('/dashboard/admin/package/create', 'pages::dashboard.admin.package.create')
    ->name('admin.packages.create'); // Create (Form Input)

Route::livewire('/dashboard/admin/package', 'pages::dashboard.admin.package')
    ->name('admin.packages.index'); // Index (List Data)

Route::livewire('/dashboard/admin/package/{package}/edit', 'pages::dashboard.admin.package.edit')->name('admin.packages.edit');


