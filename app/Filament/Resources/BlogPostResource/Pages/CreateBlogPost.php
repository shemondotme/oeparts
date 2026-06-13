<?php

namespace App\Filament\Resources\BlogPostResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;

use App\Filament\Resources\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = BlogPostResource::class;
}
