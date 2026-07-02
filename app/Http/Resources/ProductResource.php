<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->query('lang', app()->getLocale());

        return [
            'id' => $this->id,
            'oem_number' => $this->oem_number,
            'normalized_oem' => $this->normalized_oem,
            'name' => trans_field($this->name, $locale),
            'description' => trans_field($this->description, $locale),
            'price' => $this->price,
            'delivery_time' => $this->delivery_time,
            'moq' => $this->moq,
            'is_in_stock' => $this->is_in_stock,
            'is_active' => $this->is_active,
            'condition' => $this->whenLoaded('condition', fn () => [
                'id' => $this->condition->id,
                'name' => trans_field($this->condition->name, $locale),
            ]),
            'manufacturer' => $this->whenLoaded('manufacturer', fn () => new ManufacturerResource($this->manufacturer)),
            'cross_references' => ProductCrossReferenceResource::collection($this->whenLoaded('crossReferences')),
            'car_models' => CarModelResource::collection($this->whenLoaded('carModels')),
        ];
    }
}
