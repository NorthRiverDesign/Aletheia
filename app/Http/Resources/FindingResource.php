<?php

namespace App\Http\Resources;

use App\Models\Finding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Finding
 */
class FindingResource extends JsonResource
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
            'analysis_id' => $this->analysis_id,
            'title' => $this->title,
            'summary' => $this->summary,
            'rationale' => $this->rationale,
            'risk_score' => $this->risk_score,
            'confidence' => ['value' => $this->confidence->value, 'label' => $this->confidence->label()],
            'entities' => $this->entities,
            'evidence' => $this->evidence,
            'next_steps' => $this->next_steps,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'review_notes' => $this->review_notes,
            'reviewer' => $this->relationLoaded('reviewer') ? $this->reviewer?->name : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'vector' => $this->relationLoaded('vector') ? ['id' => $this->vector->id, 'title' => $this->vector->title] : null,
            'target' => $this->relationLoaded('target') ? ['id' => $this->target->id, 'name' => $this->target->name] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
