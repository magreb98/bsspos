<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMemberRequest;
use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\AuthController;
use Modules\Commerce\Http\Controllers\CashClosingController;
use Modules\Commerce\Http\Controllers\CurrencyController;
use Modules\Commerce\Http\Controllers\InvoiceController;
use Modules\Commerce\Http\Controllers\CashRegisterController;
use Modules\Commerce\Http\Controllers\CashSessionController;
use Modules\Commerce\Http\Controllers\CustomerController;
use Modules\Commerce\Http\Controllers\CustomerCreditController;
use Modules\Commerce\Http\Controllers\DashboardController;
use Modules\Commerce\Http\Controllers\ExpenseController;
use Modules\Commerce\Http\Controllers\FamilyController;
use Modules\Commerce\Http\Controllers\InventoryCountController;
use Modules\Commerce\Http\Controllers\OrganizationalUnitController;
use Modules\Commerce\Http\Controllers\InvoiceSettingController;
use Modules\Commerce\Http\Controllers\PaymentController;
use Modules\Commerce\Http\Controllers\PaymentMethodController;
use Modules\Commerce\Http\Controllers\PointOfSaleController;
use Modules\Commerce\Http\Controllers\ProductBatchController;
use Modules\Commerce\Http\Controllers\ProductController;
use Modules\Commerce\Http\Controllers\ProductImageController;
use Modules\Commerce\Http\Controllers\ProductVariantController;
use Modules\Commerce\Http\Controllers\PromotionController;
use Modules\Commerce\Http\Controllers\QuoteController;
use Modules\Commerce\Http\Controllers\ReceptionController;
use Modules\Commerce\Http\Controllers\ReportController;
use Modules\Commerce\Http\Controllers\SaleController;
use Modules\Commerce\Http\Controllers\SaleReturnController;
use Modules\Commerce\Http\Controllers\StockAlertController;
use Modules\Commerce\Http\Controllers\StockController;
use Modules\Commerce\Http\Controllers\StockMovementController;
use Modules\Commerce\Http\Controllers\SupplierController;
use Modules\Commerce\Http\Controllers\SupplierOrderController;
use Modules\Commerce\Http\Controllers\SyncController;
use Modules\Commerce\Http\Controllers\TransferController;
use Modules\Commerce\Http\Controllers\UserController;

// ── Authentification (pas besoin d'être authentifié) ─────────────────────────
Route::prefix('commerce/auth')->name('commerce.auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:member-login')
        ->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])
        ->name('me')
        ->middleware(AuthenticateMemberRequest::class);
    // Reachable even while must_change_password is still true — see
    // AuthenticateMemberRequest, which otherwise blocks every other route.
    Route::post('change-password', [AuthController::class, 'changePassword'])
        ->name('change-password')
        ->middleware(AuthenticateMemberRequest::class);
});

Route::prefix('commerce')->name('commerce.')->middleware([AuthenticateMemberRequest::class, 'throttle:api'])->group(function (): void {

    // ── Paramètres entreprise ─────────────────────────────────────────────────
    Route::middleware('permission:invoice-settings.manage')->group(function (): void {
        Route::get('invoice-settings', [InvoiceSettingController::class, 'show'])->name('invoice-settings.show');
        Route::match(['put', 'patch'], 'invoice-settings', [InvoiceSettingController::class, 'update'])->name('invoice-settings.update');
    });

    // ── Familles ─────────────────────────────────────────────────────────────
    Route::middleware('permission:family.list')->group(function (): void {
        Route::get('families', [FamilyController::class, 'index'])->name('families.index');
        Route::get('families/{family}', [FamilyController::class, 'show'])->name('families.show');
    });
    Route::middleware('permission:family.write')->group(function (): void {
        Route::post('families', [FamilyController::class, 'store'])->name('families.store');
        Route::match(['put', 'patch'], 'families/{family}', [FamilyController::class, 'update'])->name('families.update');
        Route::delete('families/{family}', [FamilyController::class, 'destroy'])->name('families.destroy');
    });

    // ── Produits ─────────────────────────────────────────────────────────────
    Route::middleware('permission:product.list')->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    });
    Route::middleware('permission:product.write')->group(function (): void {
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->name('products.update');
    });

    // ── Images produit ────────────────────────────────────────────────────────
    Route::prefix('products/{product}/images')
        ->name('products.images.')
        ->group(function (): void {
            Route::get('/', [ProductImageController::class, 'index'])
                ->name('index')
                ->middleware('permission:image.list');

            Route::middleware('permission:image.write')->group(function (): void {
                Route::post('/', [ProductImageController::class, 'store'])->name('store');
                Route::post('/upload', [ProductImageController::class, 'upload'])->name('upload');
                Route::post('/reorder', [ProductImageController::class, 'reorder'])->name('reorder');
                Route::delete('/{image}', [ProductImageController::class, 'destroy'])->name('destroy');
            });
        });

    // ── Clients ───────────────────────────────────────────────────────────────
    Route::middleware('permission:customer.list')->group(function (): void {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('customers/{customer}/sales', [CustomerController::class, 'sales'])->name('customers.sales');
    });
    Route::middleware('permission:customer.write')->group(function (): void {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::match(['put', 'patch'], 'customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    // ── Sessions de caisse ────────────────────────────────────────────────────
    Route::middleware('permission:cash-session.manage')->group(function (): void {
        Route::get('cash-sessions', [CashSessionController::class, 'index'])->name('cash-sessions.index');
        Route::post('cash-sessions', [CashSessionController::class, 'store'])->name('cash-sessions.store');
        Route::get('cash-sessions/active', [CashSessionController::class, 'showActive'])->name('cash-sessions.active');
        Route::get('cash-sessions/{cash_session}/closing', [CashClosingController::class, 'show'])->name('cash-sessions.closing.show');
        Route::get('cash-sessions/{cash_session}/expected-cash', [CashClosingController::class, 'expected'])->name('cash-sessions.expected-cash');
    });
    // Clôture de caisse — gérant et propriétaire uniquement
    Route::middleware('permission:cash-session.close')->group(function (): void {
        Route::patch('cash-sessions/{cash_session}/close', [CashSessionController::class, 'close'])->name('cash-sessions.close');
        Route::post('cash-sessions/{cash_session}/closing', [CashClosingController::class, 'store'])->name('cash-sessions.closing.store');
    });

    // ── Dépenses ──────────────────────────────────────────────────────────────
    Route::middleware('permission:expense.write')->group(function (): void {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });

    // ── Stock ─────────────────────────────────────────────────────────────────
    Route::get('stock', [StockController::class, 'index'])
        ->name('stock.index')
        ->middleware('permission:inventory.list');

    Route::get('stock/movements', [StockMovementController::class, 'index'])
        ->name('stock.movements')
        ->middleware('permission:inventory.list');

    Route::post('stock/movements', [StockMovementController::class, 'store'])
        ->name('stock.movements.store')
        ->middleware('permission:inventory.write');

    // ── Inventaires ───────────────────────────────────────────────────────────
    Route::middleware('permission:inventory.write')->group(function (): void {
        Route::post('inventory-counts', [InventoryCountController::class, 'store'])->name('inventory-counts.store');
        Route::get('inventory-counts/{inventory_count}', [InventoryCountController::class, 'show'])->name('inventory-counts.show');
    });

    // ── Ventes ────────────────────────────────────────────────────────────────
    Route::middleware('permission:sale.list')->group(function (): void {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    });
    Route::middleware('permission:sale.write')->group(function (): void {
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::patch('sales/{sale}/confirm', [SaleController::class, 'confirm'])->name('sales.confirm');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('sales/{sale}/receipt.pdf', [SaleController::class, 'receiptPdf'])->name('sales.receipt.pdf');
        Route::post('sales/{sale}/lines', [SaleController::class, 'addLine'])->name('sales.lines.store');
        Route::delete('sales/{sale}/lines/{line}', [SaleController::class, 'removeLine'])->name('sales.lines.destroy');
        Route::post('sales/{sale}/invoice', [InvoiceController::class, 'fromSale'])->name('sales.invoice.store');
    });
    // Annulation d'une vente confirmée — gérant et propriétaire uniquement
    Route::patch('sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->name('sales.cancel')
        ->middleware('permission:sale.cancel');

    // ── Retours ───────────────────────────────────────────────────────────────
    Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])
        ->name('sales.returns.store')
        ->middleware('permission:return.write');

    // ── Paiements ─────────────────────────────────────────────────────────────
    Route::middleware('permission:payment.collect')->group(function (): void {
        Route::get('sales/{sale}/payments', [PaymentController::class, 'index'])->name('sales.payments.index');
        Route::post('sales/{sale}/payments', [PaymentController::class, 'store'])->name('sales.payments.store');
    });

    // ── Promotions & coupons ──────────────────────────────────────────────────
    Route::middleware('permission:promotion.list')->group(function (): void {
        Route::get('promotions', [PromotionController::class, 'index'])->name('promotions.index');
        Route::get('promotions/validate-coupon', [PromotionController::class, 'validateCoupon'])->name('promotions.validate-coupon');
    });
    Route::middleware('permission:promotion.write')->group(function (): void {
        Route::post('promotions', [PromotionController::class, 'store'])->name('promotions.store');
        Route::match(['put', 'patch'], 'promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
        Route::post('promotions/{promotion}/coupons', [PromotionController::class, 'storeCoupon'])->name('promotions.coupons.store');
    });

    // ── Fournisseurs (Lot 4) ──────────────────────────────────────────────────
    Route::middleware('permission:supplier.write')->group(function (): void {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::match(['put', 'patch'], 'suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    // ── Commandes fournisseur (Lot 4) ─────────────────────────────────────────
    Route::middleware('permission:supplier-order.write')->group(function (): void {
        Route::get('supplier-orders', [SupplierOrderController::class, 'index'])->name('supplier-orders.index');
        Route::post('supplier-orders', [SupplierOrderController::class, 'store'])->name('supplier-orders.store');
        Route::get('supplier-orders/{supplier_order}', [SupplierOrderController::class, 'show'])->name('supplier-orders.show');
        Route::post('supplier-orders/{supplier_order}/receptions', [ReceptionController::class, 'store'])->name('supplier-orders.receptions.store');
        Route::get('supplier-orders/{supplier_order}/receptions', [ReceptionController::class, 'show'])->name('supplier-orders.receptions.index');
    });

    // ── Transferts inter-boutiques (Lot 5) ────────────────────────────────────
    Route::middleware('permission:transfer.write')->group(function (): void {
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::patch('transfers/{transfer}/dispatch', [TransferController::class, 'dispatch'])->name('transfers.dispatch');
        Route::patch('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    });

    // ── Unités organisationnelles ─────────────────────────────────────────────
    Route::get('organizational-units', [OrganizationalUnitController::class, 'index'])
        ->name('org-units.index')
        ->middleware('permission:pos.write');
    Route::middleware('permission:org-unit.write')->group(function (): void {
        Route::post('organizational-units', [OrganizationalUnitController::class, 'store'])->name('org-units.store');
        Route::get('organizational-units/{organizationalUnit}', [OrganizationalUnitController::class, 'show'])->name('org-units.show');
        Route::match(['put', 'patch'], 'organizational-units/{organizationalUnit}', [OrganizationalUnitController::class, 'update'])->name('org-units.update');
        Route::delete('organizational-units/{organizationalUnit}', [OrganizationalUnitController::class, 'destroy'])->name('org-units.destroy');
    });

    // ── Points de vente & caisses (Lot 5) ────────────────────────────────────
    // Lecture accessible à tous les membres authentifiés
    Route::get('points-of-sale', [PointOfSaleController::class, 'index'])->name('points-of-sale.index');
    Route::get('points-of-sale/{point_of_sale}', [PointOfSaleController::class, 'show'])->name('points-of-sale.show');
    Route::get('cash-registers', [CashRegisterController::class, 'index'])->name('cash-registers.index');
    // Écriture réservée aux membres avec permission pos.write
    Route::middleware('permission:pos.write')->group(function (): void {
        Route::post('points-of-sale', [PointOfSaleController::class, 'store'])->name('points-of-sale.store');
        Route::match(['put', 'patch'], 'points-of-sale/{point_of_sale}', [PointOfSaleController::class, 'update'])->name('points-of-sale.update');
        Route::post('points-of-sale/{point_of_sale}/cash-registers', [CashRegisterController::class, 'store'])->name('cash-registers.store');
        Route::match(['put', 'patch'], 'cash-registers/{cash_register}', [CashRegisterController::class, 'update'])->name('cash-registers.update');
    });

    // ── Synchronisation hors-ligne (Lot 3) ────────────────────────────────────
    Route::post('sync', [SyncController::class, 'store'])
        ->name('sync.store')
        ->middleware('permission:sync.push');

    // ── Tableau de bord consolidé (Lot 6) ────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard.index')
        ->middleware('permission:dashboard.view');

    // ── Méthodes de paiement configurables ───────────────────────────────────
    Route::get('payment-methods', [PaymentMethodController::class, 'index'])
        ->name('payment-methods.index')
        ->middleware('permission:payment.collect');
    Route::middleware('permission:payment-method.manage')->group(function (): void {
        Route::post('payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::match(['put', 'patch'], 'payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    });

    // ── Crédits clients ───────────────────────────────────────────────────────
    Route::get('customers/{customer}/credits', [CustomerCreditController::class, 'index'])
        ->name('customers.credits.index')
        ->middleware('permission:customer-credit.view');

    // ── Variantes produits ────────────────────────────────────────────────────
    Route::get('products/{product}/variants', [ProductVariantController::class, 'index'])
        ->name('products.variants.index')
        ->middleware('permission:product.list');
    Route::middleware('permission:variant.write')->group(function (): void {
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->name('products.variants.store');
        Route::match(['put', 'patch'], 'product-variants/{productVariant}', [ProductVariantController::class, 'update'])->name('product-variants.update');
    });

    // ── Lots (batches) produits ───────────────────────────────────────────────
    Route::get('products/{product}/batches', [ProductBatchController::class, 'index'])
        ->name('products.batches.index')
        ->middleware('permission:product.list');
    Route::post('products/{product}/batches', [ProductBatchController::class, 'store'])
        ->name('products.batches.store')
        ->middleware('permission:batch.write');

    // ── Rapports avancés ──────────────────────────────────────────────────────
    Route::middleware('permission:report.view')->prefix('reports')->name('reports.')->group(function (): void {
        Route::get('top-products', [ReportController::class, 'topProducts'])->name('top-products');
        Route::get('margin', [ReportController::class, 'margin'])->name('margin');
        Route::get('customer-ranking', [ReportController::class, 'customerRanking'])->name('customer-ranking');
        Route::get('stock-rotation', [ReportController::class, 'stockRotation'])->name('stock-rotation');
    });

    // ── Devis ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:quote.list')->group(function (): void {
        Route::get('quotes', [QuoteController::class, 'index'])->name('quotes.index');
    });
    Route::middleware('permission:quote.write')->group(function (): void {
        Route::post('quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::patch('quotes/{sale}/convert', [QuoteController::class, 'convert'])->name('quotes.convert');
    });

    // ── Alertes stock & seuils ────────────────────────────────────────────────
    Route::get('stock/alerts', [StockAlertController::class, 'index'])
        ->name('stock.alerts')
        ->middleware('permission:inventory-alert.view');
    Route::post('stock/thresholds', [StockAlertController::class, 'setThreshold'])
        ->name('stock.thresholds.set')
        ->middleware('permission:inventory-threshold.manage');

    // ── Factures B2B ──────────────────────────────────────────────────────────
    Route::middleware('permission:invoice.list')->group(function (): void {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    });
    Route::middleware('permission:invoice.write')->group(function (): void {
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::patch('invoices/{invoice}/mark-sent', [InvoiceController::class, 'markSent'])->name('invoices.mark-sent');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
        Route::get('invoices/{invoice}/invoice.pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    });

    // ── Gestion des utilisateurs (membres) ───────────────────────────────────
    Route::middleware('permission:pos.write')->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::match(['put', 'patch'], 'users/{id}', [UserController::class, 'update'])->name('users.update');
        // Affectation boutique ↔ utilisateur
        Route::get('users/{id}/points-of-sale', [UserController::class, 'listPos'])->name('users.pos.index');
        Route::post('users/{id}/points-of-sale', [UserController::class, 'assignPos'])->name('users.pos.attach');
        Route::delete('users/{id}/points-of-sale/{posId}', [UserController::class, 'unassignPos'])->name('users.pos.detach');
    });

    // ── Devises / multi-devise ────────────────────────────────────────────────
    Route::get('currencies', [CurrencyController::class, 'index'])
        ->name('currencies.index')
        ->middleware('permission:payment.collect');
    Route::middleware('permission:currency.manage')->group(function (): void {
        Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
        Route::match(['put', 'patch'], 'currencies/{currency}', [CurrencyController::class, 'update'])->name('currencies.update');
    });
});
