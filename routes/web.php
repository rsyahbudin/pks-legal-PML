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

    // Tickets Routes (main feature)
    Volt::route('tickets', 'contracts.index')
        ->middleware('permission:tickets.view')
        ->name('tickets.index');
    Volt::route('tickets/create', 'contracts.create')
        ->middleware('permission:tickets.create')
        ->name('tickets.create');
    Volt::route('tickets/{contract}', 'contracts.show')
        ->middleware('permission:tickets.view')
        ->name('tickets.show');
    Volt::route('tickets/{contract}/edit', 'contracts.edit')
        ->middleware('permission:tickets.edit')
        ->name('tickets.edit');

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
    Volt::route('email-templates', 'admin.email-templates')
        ->middleware('permission:email_templates.edit')
        ->name('admin.email-templates');
});

