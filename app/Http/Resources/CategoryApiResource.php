<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryApiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->query('lang', app()->getLocale());

        return [
            'id' => $this->id,
            'name' => trans_field($this->name, $locale),
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'children' => CategoryApiResource::collection($this->whenLoaded('children')),
        ];
    }
}
