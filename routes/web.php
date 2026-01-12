<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Contracts
    Volt::route('contracts', 'contracts.index')
        ->middleware('permission:contracts.view')
        ->name('contracts.index');
    Volt::route('contracts/create', 'contracts.create')
        ->middleware('permission:contracts.create')
        ->name('contracts.create');
    Volt::route('contracts/{contract}', 'contracts.show')
        ->middleware('permission:contracts.view')
        ->name('contracts.show');
    Volt::route('contracts/{contract}/edit', 'contracts.edit')
        ->middleware('permission:contracts.edit')
        ->name('contracts.edit');

    // Contracts Export
    Route::get('contracts-export', function (\Illuminate\Http\Request $request) {
        if (! auth()->user()->hasPermission('reports.export')) {
            abort(403);
        }
        $export = new \App\Exports\ContractsExport(
            $request->get('status'),
            $request->get('color'),
            $request->get('division') ? (int) $request->get('division') : null
        );
        $filename = 'contracts_'.now()->format('Y-m-d_His').'.csv';

        return response($export->toCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    })->middleware('permission:reports.export')->name('contracts.export');

    // Divisions
    Volt::route('divisions', 'divisions.index')
        ->middleware('permission:divisions.view')
        ->name('divisions.index');
});

// Admin routes
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Volt::route('users', 'admin.users.index')
        ->middleware('permission:users.view')
        ->name('admin.users.index');
    Volt::route('roles', 'admin.roles.index')
        ->middleware('permission:roles.view')
        ->name('admin.roles.index');
    Volt::route('settings', 'admin.settings.index')
        ->middleware('permission:settings.view')
        ->name('admin.settings.index');
});
