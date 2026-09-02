<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ShareAuthPermissions;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    ShareAuthPermissions::class,
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('can:USER:READ');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show')->middleware('can:USER:READ');
    Route::post('/users/{user}/roles/{role}', [UserController::class, 'assignRole'])->name('users.roles.assign')->middleware('can:USER:WRITE');
    Route::delete('/users/{user}/roles/{role}', [UserController::class, 'removeRole'])->name('users.roles.remove')->middleware('can:USER:WRITE');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('can:ROLE:READ');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('can:ROLE:WRITE');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('can:ROLE:WRITE');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('can:ROLE:WRITE');
    Route::get('/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions')->middleware('can:ROLE:WRITE');
    Route::post('/roles/{role}/permissions/{permission}', [RoleController::class, 'assignPermission'])->name('roles.permissions.assign')->middleware('can:ROLE:WRITE');
    Route::delete('/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermission'])->name('roles.permissions.remove')->middleware('can:ROLE:WRITE');

    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('can:PERMISSION:READ');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store')->middleware('can:PERMISSION:WRITE');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update')->middleware('can:PERMISSION:WRITE');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy')->middleware('can:PERMISSION:WRITE');
});

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
