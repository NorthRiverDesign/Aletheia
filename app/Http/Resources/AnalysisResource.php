<?php

namespace App\Http\Resources;

use App\Models\Analysis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Analysis
 */
class AnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $maxScore = $this->resource->getAttribute('findings_max_risk_score');

        return [
            'id' => $this->id,
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'model' => $this->model,
            'summary' => $this->summary,
            'error' => $this->error,
            'usage' => $this->usage,
            'vector' => $this->relationLoaded('vector') ? ['id' => $this->vector->id, 'title' => $this->vector->title] : null,
            'target' => $this->relationLoaded('target') ? ['id' => $this->target->id, 'name' => $this->target->name] : null,
            'requester' => $this->relationLoaded('requester') ? $this->requester?->name : null,
            'findings_count' => $this->resource->getAttribute('findings_count'),
            'max_risk_score' => $maxScore === null ? null : (int) $maxScore,
            'created_at' => $this->created_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
