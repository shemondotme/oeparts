<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManufacturerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->query('lang', app()->getLocale());

        return [
            'id' => $this->id,
            'name' => trans_field($this->name, $locale),
            'slug' => $this->slug,
            'country_code' => $this->country_code,
            'is_active' => $this->is_active,
            'is_verified_oem' => $this->is_verified_oem,
            'sort_order' => $this->sort_order,
        ];
    }
}
