<?php

namespace App\Filament\Resources\ConditionResource\Pages;

use App\Filament\Concerns\DisablesCreateAnother;
use App\Filament\Resources\ConditionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCondition extends CreateRecord
{
    use DisablesCreateAnother;

    protected static string $resource = ConditionResource::class;

    public function getHeading(): string
    {
        return 'Create Condition';
    }

    public function getSubheading(): string
    {
        return 'Add a part condition like New, Used, or Remanufactured.';
    }
}
