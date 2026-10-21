<?php

namespace App\Http\Resources;

use App\Models\Target;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Target
 */
class TargetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => ['value' => $this->type->value, 'label' => $this->type->label()],
            'region' => $this->region,
            'description' => $this->description,
            'website' => $this->website,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'votes_count' => $this->votes_count,
            'has_voted' => (bool) $this->resource->getAttribute('has_voted'),
            'creator' => $this->relationLoaded('creator') ? $this->creator?->name : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
