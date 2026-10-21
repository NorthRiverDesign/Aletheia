<?php

namespace App\Http\Resources;

use App\Models\Verification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Verification
 */
class VerificationResource extends JsonResource
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
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'verdict' => $this->verdict === null ? null : ['value' => $this->verdict->value, 'label' => $this->verdict->label()],
            'summary' => $this->summary,
            'risk_score' => $this->risk_score,
            'confidence' => $this->confidence === null ? null : ['value' => $this->confidence->value, 'label' => $this->confidence->label()],
            'claims' => $this->claims ?? [],
            'issues' => $this->issues ?? [],
            'model' => $this->model,
            'error' => $this->error,
            'usage' => $this->usage,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
