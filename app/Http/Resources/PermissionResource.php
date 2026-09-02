<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `$this->resource` is JsonResource's own property holding the
        // wrapped model, so the `resource` database column (this
        // permission's resource name, e.g. "ROLE") must be read as
        // `$this->resource->resource` to avoid the name collision.
        $resource = $this->resource->resource;
        $action = $this->resource->action;

        return [
            'id' => $this->id,
            'resource' => $resource,
            'action' => $action,
            'name' => "{$resource}:{$action}",
            'roles_count' => $this->whenCounted('roles'),
            'created_at' => $this->created_at,
        ];
    }
}
