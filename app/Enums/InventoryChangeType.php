<?php

namespace App\Enums;

enum InventoryChangeType: string
{
    case CsvImport   = 'csv_import';
    case Manual      = 'manual';
    case BulkUpdate  = 'bulk_update';
    case System      = 'system';
}
