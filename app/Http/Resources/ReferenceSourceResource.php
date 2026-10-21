<?php

namespace App\Http\Resources;

use App\Models\ReferenceSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReferenceSource
 */
class ReferenceSourceResource extends JsonResource
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
            'url' => $this->url,
            'description' => $this->description,
            'origin' => ['value' => $this->origin->value, 'label' => $this->origin->label()],
            'verified' => $this->verified_at !== null,
            'analysis_id' => $this->analysis_id,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
