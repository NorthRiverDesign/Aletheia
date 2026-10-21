<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as admins see them on the moderation page. Includes the email
 * address, so keep it off pages other members can open.
 *
 * @mixin User
 */
class MemberResource extends JsonResource
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
            'email' => $this->email,
            'is_admin' => $this->is_admin,
            'email_verified' => $this->email_verified_at !== null,
            'banned_at' => $this->banned_at?->toIso8601String(),
            'ban_reason' => $this->ban_reason,
            'vectors_count' => (int) $this->resource->getAttribute('vectors_count'),
            'targets_count' => (int) $this->resource->getAttribute('targets_count'),
            'votes_count' => (int) $this->resource->getAttribute('votes_count'),
            'created_at' => $this->created_at?->toIso8601String(),
            'can_ban' => $request->user()?->can('ban', $this->resource) ?? false,
        ];
    }
}
