<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'address' => $this->address,
            'email_verified_at' => $this->email_verified_at,
            // See RoleResource::toArray() for why this must be eagerly
            // resolved here via the `whenLoaded()` callback form, rather
            // than left as a bare `RoleResource::collection(...)`.
            'roles' => $this->whenLoaded(
                'roles',
                fn ($roles) => RoleResource::collection($roles)->resolve()
            ),
            'created_at' => $this->created_at,
        ];
    }
}
