<?php

use Illuminate\Support\Facades\Route;

// The GEN-I backend has no public website; the root opens the admin dashboard
// (guests are redirected to the admin login page).
Route::redirect('/', '/'.config('admin.prefix', 'admin'))->name('home');
