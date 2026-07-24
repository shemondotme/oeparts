<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarModelApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->query('lang', app()->getLocale());

        return [
            'id' => $this->id,
            'name' => trans_field($this->name, $locale),
            'slug' => $this->slug,
            'year_from' => $this->year_from,
            'year_to' => $this->year_to,
            'manufacturer_id' => $this->manufacturer_id,
        ];
    }
}
