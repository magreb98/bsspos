<?php

declare(strict_types=1);

namespace App\Platform\Identity\Enums;

enum AppPermission: string
{
    // ── Product images ────────────────────────────────────────────────────────
    case ImageList  = 'image.list';
    case ImageWrite = 'image.write';

    // ── Commerce — achats et logistique (Lot 4) ──────────────────────────────
    case SupplierWrite      = 'supplier.write';
    case SupplierOrderWrite = 'supplier-order.write';
    case ReceptionWrite     = 'reception.write';
    case InventoryWrite     = 'inventory.write';
    case ExpenseWrite       = 'expense.write';
    case TransferWrite      = 'transfer.write';
    case PosWrite           = 'pos.write';
    case OrgUnitWrite       = 'org-unit.write';

    // ── Commerce — hors-ligne et pilotage (Lot 3 / Lot 6) ────────────────────
    case SyncPush      = 'sync.push';
    case DashboardView = 'dashboard.view';

    // ── Secteur électronique ──────────────────────────────────────────────────
    case DeviceSpecList       = 'device-spec.list';
    case DeviceSpecWrite      = 'device-spec.write';
    case SerialUnitList       = 'serial-unit.list';
    case SerialUnitWrite      = 'serial-unit.write';
    case InstallmentList      = 'installment.list';
    case InstallmentPay       = 'installment.pay';
    case PaymentScheduleList  = 'payment-schedule.list';
    case PaymentScheduleWrite = 'payment-schedule.write';
    case WarrantyList         = 'warranty.list';
    case WarrantyWrite        = 'warranty.write';
    case ServiceTicketList    = 'service-ticket.list';
    case ServiceTicketWrite   = 'service-ticket.write';

    // ── Commerce — lecture transactionnelle ──────────────────────────────────
    case SaleList          = 'sale.list';
    case SaleCancel        = 'sale.cancel';        // annuler une vente confirmée (gérant+)
    case CashSessionClose  = 'cash-session.close'; // clôturer la caisse (gérant+)
    case ReturnWrite       = 'return.write';
    case PromotionList     = 'promotion.list';
    case PromotionWrite    = 'promotion.write';
    case InvoiceSettings   = 'invoice-settings.manage';

    // ── Commerce — nouvelles fonctionnalités ─────────────────────────────────
    case PaymentMethodManage  = 'payment-method.manage';
    case CustomerCreditView   = 'customer-credit.view';
    case LoyaltyView          = 'loyalty.view';
    case VariantWrite         = 'variant.write';
    case BatchWrite           = 'batch.write';
    case ReportView           = 'report.view';
    case QuoteWrite           = 'quote.write';
    case QuoteList            = 'quote.list';
    case InventoryAlertView       = 'inventory-alert.view';
    case InventoryThresholdManage = 'inventory-threshold.manage';
    case InvoiceList              = 'invoice.list';
    case InvoiceWrite             = 'invoice.write';
    case CurrencyManage           = 'currency.manage';

    // ── Commerce ─────────────────────────────────────────────────────────────
    case FamilyList        = 'family.list';
    case FamilyWrite       = 'family.write';
    case ProductList       = 'product.list';
    case ProductWrite      = 'product.write';
    case CustomerList      = 'customer.list';
    case CustomerWrite     = 'customer.write';
    case CashSessionManage = 'cash-session.manage';
    case SaleWrite         = 'sale.write';
    case PaymentCollect    = 'payment.collect';
    case InventoryList     = 'inventory.list';

    /** @return list<string> */
    public static function allValues(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

    /**
     * Vendeur — opérations de vente, caisse et consultation stock.
     * Pas de gestion catalogue, fournisseurs, transferts ni administration.
     *
     * @return list<string>
     */
    public static function vendeurValues(): array
    {
        return [
            self::ImageList->value,
            self::DeviceSpecList->value,
            self::SerialUnitList->value,
            self::InstallmentList->value,
            self::InstallmentPay->value,
            self::PaymentScheduleList->value,
            self::WarrantyList->value,
            self::ServiceTicketList->value,
            self::FamilyList->value,
            self::ProductList->value,
            self::CustomerList->value,
            self::CustomerWrite->value,   // créer un client lors d'une vente
            self::CashSessionManage->value,
            self::SaleWrite->value,
            self::PaymentCollect->value,
            self::InventoryList->value,
            // expense.write retiré : les dépenses sont du ressort du gérant
            self::SyncPush->value,
            self::DashboardView->value,
            self::SaleList->value,
            self::ReturnWrite->value,
            self::PromotionList->value,
            self::CustomerCreditView->value,
            self::LoyaltyView->value,
            self::QuoteWrite->value,
            self::QuoteList->value,
            self::InventoryAlertView->value,
        ];
    }

    /**
     * Gérant — tout sauf l'administration système (users, POS, paramètres globaux).
     * Pas de : pos.write, org-unit.write, invoice-settings.manage,
     *          currency.manage, payment-method.manage.
     *
     * @return list<string>
     */
    public static function gerantValues(): array
    {
        $adminOnly = [
            self::PosWrite->value,
            self::OrgUnitWrite->value,
            self::InvoiceSettings->value,
            self::CurrencyManage->value,
            self::PaymentMethodManage->value,
        ];

        return array_values(array_diff(self::allValues(), $adminOnly));
    }
}
