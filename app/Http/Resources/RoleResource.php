<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role_name' => $this->role_name,
            'users_count' => $this->whenCounted('users'),
            // `->resolve()` must happen here, eagerly, rather than handing
            // the caller a bare `PermissionResource::collection(...)`:
            // `JsonResource`/`ResourceCollection` implement `Responsable`,
            // and Inertia's own prop resolver calls `toResponse()` on any
            // `Responsable` value it finds at *any* nesting depth, which
            // wraps it in a `data` key - fine for a real HTTP response,
            // wrong for a value nested inside another already-resolved
            // prop. The `whenLoaded()` callback form is required instead
            // of unconditionally calling `->resolve()`, because resolving
            // a `Resource::collection()` built from a `MissingValue` (the
            // placeholder `whenLoaded()` returns when the relation isn't
            // loaded, e.g. on the roles index page) fatals - the callback
            // only runs once the relation is confirmed loaded.
            'permissions' => $this->whenLoaded(
                'permissions',
                fn ($permissions) => PermissionResource::collection($permissions)->resolve()
            ),
            'created_at' => $this->created_at,
        ];
    }
}
