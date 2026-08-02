<?php

use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ThermalReceiptController;
use App\Livewire\AuditLogsManager;
use App\Livewire\AccessControlManager;
use App\Livewire\BackupManager;
use App\Livewire\Dashboard;
use App\Livewire\DeliveryManagement;
use App\Livewire\GarmentTaggingManager;
use App\Livewire\CustomersManager;
use App\Livewire\OrganizationSettings;
use App\Livewire\NotificationsManager;
use App\Livewire\OrdersManager;
use App\Livewire\OperationsCalendar;
use App\Livewire\PaymentsManager;
use App\Livewire\PickupManagement;
use App\Livewire\ProductsManager;
use App\Livewire\RateChartManager;
use App\Livewire\ReportsManager;
use App\Livewire\ServicesManager;
use App\Livewire\SubscriptionPackagesManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->where('is_active', true)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid local account credentials.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->name('login.store');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'permission:dashboard.view'])->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    Route::get('/calendar', OperationsCalendar::class)->middleware('permission:deliveries.manage|orders.manage|subscriptions.manage|staff.manage')->name('calendar.index');
    Route::get('/notifications', NotificationsManager::class)->middleware('permission:dashboard.view')->name('notifications.index');
    Route::get('/audit-logs', AuditLogsManager::class)->middleware('permission:settings.manage|reports.view')->name('audit-logs.index');
    Route::get('/access-control', AccessControlManager::class)->middleware('permission:settings.manage')->name('access-control.index');
    Route::get('/backups', BackupManager::class)->middleware('permission:settings.manage')->name('backups.index');
    Route::get('/receipts/orders/{order}', [ThermalReceiptController::class, 'show'])
        ->middleware('permission:payments.manage|orders.manage')
        ->name('receipts.orders.show');
    Route::get('/orders', OrdersManager::class)->middleware('permission:orders.manage|orders.assigned.view')->name('orders.index');
    Route::get('/garment-tags', GarmentTaggingManager::class)->middleware('permission:garments.scan')->name('garment-tags.index');
    Route::get('/pickups', PickupManagement::class)->middleware('permission:deliveries.manage|deliveries.assigned.view')->name('pickups.index');
    Route::get('/customers', CustomersManager::class)->middleware('permission:customers.manage')->name('customers.index');
    Route::get('/payments', PaymentsManager::class)->middleware('permission:payments.manage')->name('payments.index');
    Route::get('/services', ServicesManager::class)->middleware('permission:services.manage')->name('services.index');
    Route::get('/products', ProductsManager::class)->middleware('permission:products.manage')->name('products.index');
    Route::get('/rate-chart', RateChartManager::class)->middleware('permission:rate-chart.manage')->name('rate-chart.index');
    Route::get('/reports', ReportsManager::class)->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/export/{format}', [ReportExportController::class, 'export'])->middleware('permission:reports.view')->name('reports.export');
    Route::get('/subscriptions', SubscriptionPackagesManager::class)->middleware('permission:subscriptions.manage')->name('subscriptions.index');
    Route::get('/plan', SubscriptionPackagesManager::class)->middleware('permission:subscriptions.manage')->name('plan');
    Route::get('/deliveries', DeliveryManagement::class)->middleware('permission:deliveries.manage|deliveries.assigned.view')->name('deliveries.index');
    Route::get('/settings', OrganizationSettings::class)->middleware('permission:settings.manage')->name('settings.index');
});
