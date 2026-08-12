<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Support\AdminUi;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * No existing multi-image/gallery upload pattern exists anywhere in this
 * codebase (every other FileUpload::make() usage is a single logo/OG-image
 * field) — this is genuinely new UI, structurally modeled on
 * CrossReferencesRelationManager (same Relation Manager shape) since no
 * closer precedent exists to copy.
 *
 * "Exactly one featured image per product" is enforced in
 * ProductImageObserver, not here — the Toggle/ToggleColumn below just
 * reflect whatever state results, they don't need their own un-set-
 * siblings logic.
 */
class ProductImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $recordTitleAttribute = 'path';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->maxSize(4096)
                    ->required()
                    ->helperText('Max 4MB. A thumbnail and medium-size version are generated automatically.'),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured (main product image)')
                    ->helperText('Only one image per product can be featured — setting this un-sets any other featured image.'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),
                AdminUi::translatableTabs('Alt Text', [
                    'alt_text' => [
                        'label' => 'Alt Text',
                        'required' => false,
                        'helperText' => 'Describes the image for accessibility and image search — e.g. "Bosch brake pad, front, new".',
                    ],
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return AdminUi::configureTable($table)->recordTitleAttribute('path')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('thumbnail_url')
                    ->label('Preview')
                    ->disk('public'),
                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y'),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }
}
