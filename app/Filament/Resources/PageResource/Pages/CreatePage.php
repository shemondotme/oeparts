<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // pages.created_by is a NOT NULL foreign key (migration
        // 2026_03_26_100033) with no form field for it anywhere — every
        // single CMS page creation crashed with a raw SQLSTATE NOT NULL
        // constraint failure instead of saving, confirmed live. Same bug
        // class as CreateCoupon, same auth('admin')->id() fix.
        $data['created_by'] = auth('admin')->id();

        return $data;
    }
}
