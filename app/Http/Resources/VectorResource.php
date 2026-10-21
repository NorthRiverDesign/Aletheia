<?php

namespace App\Http\Resources;

use App\Models\Vector;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vector
 */
class VectorResource extends JsonResource
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
            'title' => $this->title,
            'category' => ['value' => $this->category->value, 'label' => $this->category->label()],
            'summary' => $this->summary,
            'description' => $this->description,
            'indicators' => $this->indicators,
            'data_sources' => $this->data_sources,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'votes_count' => $this->votes_count,
            'has_voted' => (bool) $this->resource->getAttribute('has_voted'),
            'creator' => $this->relationLoaded('creator') ? $this->creator?->name : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
