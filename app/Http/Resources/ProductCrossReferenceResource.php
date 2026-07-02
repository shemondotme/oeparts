<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCrossReferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cross_reference_number' => $this->cross_oem_number,
            'normalized_cross_oem' => $this->normalized_cross_oem,
        ];
    }
}
