<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMemberRequest;
use Illuminate\Support\Facades\Route;
use Modules\SecteurElectronique\Http\Controllers\DeviceSpecController;
use Modules\SecteurElectronique\Http\Controllers\InstallmentController;
use Modules\SecteurElectronique\Http\Controllers\PaymentScheduleController;
use Modules\SecteurElectronique\Http\Controllers\SerialUnitController;
use Modules\SecteurElectronique\Http\Controllers\ServiceTicketController;
use Modules\SecteurElectronique\Http\Controllers\WarrantyController;

Route::prefix('electronics')->name('electronics.')->middleware([AuthenticateMemberRequest::class, 'throttle:api'])->group(function (): void {

    // ── DeviceSpec ───────────────────────────────────────────────────────────
    Route::middleware('permission:device-spec.list')->group(function (): void {
        Route::get('device-specs', [DeviceSpecController::class, 'index'])->name('device-specs.index');
        Route::get('device-specs/{device_spec}', [DeviceSpecController::class, 'show'])->name('device-specs.show');
    });
    Route::middleware('permission:device-spec.write')->group(function (): void {
        Route::post('device-specs', [DeviceSpecController::class, 'store'])->name('device-specs.store');
        Route::match(['put', 'patch'], 'device-specs/{device_spec}', [DeviceSpecController::class, 'update'])->name('device-specs.update');
        Route::delete('device-specs/{device_spec}', [DeviceSpecController::class, 'destroy'])->name('device-specs.destroy');
    });

    // ── SerialUnit ───────────────────────────────────────────────────────────
    Route::middleware('permission:serial-unit.list')->group(function (): void {
        Route::get('serial-units', [SerialUnitController::class, 'index'])->name('serial-units.index');
        Route::get('serial-units/{serial_unit}', [SerialUnitController::class, 'show'])->name('serial-units.show');
    });
    Route::middleware('permission:serial-unit.write')->group(function (): void {
        Route::post('serial-units', [SerialUnitController::class, 'store'])->name('serial-units.store');
        Route::delete('serial-units/{serial_unit}', [SerialUnitController::class, 'destroy'])->name('serial-units.destroy');
    });

    // ── Warranty ─────────────────────────────────────────────────────────────
    Route::middleware('permission:warranty.list')->group(function (): void {
        Route::get('warranties', [WarrantyController::class, 'index'])->name('warranties.index');
        Route::get('warranties/{warranty}', [WarrantyController::class, 'show'])->name('warranties.show');
    });
    Route::post('warranties', [WarrantyController::class, 'store'])
        ->name('warranties.store')
        ->middleware('permission:warranty.write');

    // ── ServiceTicket ─────────────────────────────────────────────────────────
    Route::middleware('permission:service-ticket.list')->group(function (): void {
        Route::get('service-tickets', [ServiceTicketController::class, 'index'])->name('service-tickets.index');
        Route::get('service-tickets/{service_ticket}', [ServiceTicketController::class, 'show'])->name('service-tickets.show');
    });
    Route::middleware('permission:service-ticket.write')->group(function (): void {
        Route::post('service-tickets', [ServiceTicketController::class, 'store'])->name('service-tickets.store');
        Route::match(['put', 'patch'], 'service-tickets/{service_ticket}', [ServiceTicketController::class, 'update'])->name('service-tickets.update');
    });

    // ── PaymentSchedule ──────────────────────────────────────────────────────
    Route::middleware('permission:payment-schedule.list')->group(function (): void {
        Route::get('payment-schedules', [PaymentScheduleController::class, 'index'])->name('payment-schedules.index');
        Route::get('payment-schedules/{payment_schedule}', [PaymentScheduleController::class, 'show'])->name('payment-schedules.show');
        Route::get('payment-schedules/{payment_schedule}/schedule.pdf', [PaymentScheduleController::class, 'pdf'])->name('payment-schedules.pdf');
    });
    Route::middleware('permission:payment-schedule.write')->group(function (): void {
        Route::post('payment-schedules', [PaymentScheduleController::class, 'store'])->name('payment-schedules.store');
    });

    // ── Installment ──────────────────────────────────────────────────────────
    Route::middleware('permission:installment.list')->group(function (): void {
        Route::get('installments', [InstallmentController::class, 'index'])->name('installments.index');
        Route::get('installments/{installment}', [InstallmentController::class, 'show'])->name('installments.show');
    });
    Route::middleware('permission:installment.pay')->group(function (): void {
        Route::patch('installments/{installment}/pay', [InstallmentController::class, 'pay'])->name('installments.pay');
    });
});
