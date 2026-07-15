<?php

namespace App\Filament\Resources\SeoMetaResource\Pages;

use App\Filament\Resources\SeoMetaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Support\HasDrilldownFilters;

class ListSeoMetas extends ListRecords
{
    use HasDrilldownFilters;

    protected static string $resource = SeoMetaResource::class;

    protected function getHeaderActions(): array
    {
        // seo_meta.metable_type/metable_id are NOT NULL polymorphic-target
        // columns, but the create form only ever shows them as read-only
        // fields (they exist to give context when editing an EXISTING row —
        // meaningless on a blank create) with no way to actually set them.
        // Clicking "New" here and saving always crashed with a raw SQLSTATE
        // NOT NULL constraint failure, confirmed live. There's also no
        // programmatic creation path anywhere in the app yet (only
        // Product::seoMeta() morphOne is defined, nothing ever calls
        // SeoMeta::create()) — this resource is currently edit-only for
        // whatever rows exist. Building a real "create SEO meta for a
        // record" flow (a relation-manager/attach-picker keyed off an
        // actual Product/Page) is a feature decision for a future chunk,
        // not a quick fix — removing the guaranteed-broken standalone
        // Create button in the meantime.
        return [];
    }
}
