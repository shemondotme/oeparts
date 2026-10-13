<?php

namespace App\Services\Migration\Groups;

/**
 * The fifteen data groups a person chooses between when moving a site. A group is a set of
 * database tables (and sometimes folders of files) that belong together; nobody is asked to
 * think in tables.
 */
enum GroupKey: string
{
    case Core = 'core';
    case Catalog = 'catalog';
    case CatalogFiles = 'catalog_files';
    case Media = 'media';
    case Content = 'content';
    case Customers = 'customers';
    case Sales = 'sales';
    case SalesFiles = 'sales_files';
    case Invoicing = 'invoicing';
    case ShippingTax = 'shipping_tax';
    case Marketing = 'marketing';
    case AdminPrefs = 'admin_prefs';
    case Carts = 'carts';
    case Logs = 'logs';
    case System = 'system';

    public function labelKey(): string
    {
        return 'migration.group.'.$this->value.'.label';
    }

    public function descriptionKey(): string
    {
        return 'migration.group.'.$this->value.'.description';
    }
}
