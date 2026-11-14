<?php

namespace BlueprintPhp\Laravel\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PaginationCollection extends ResourceCollection
{
    private ?string $resourceClass;

    /**
     * Create a new resource collection instance.
     *
     * @param  mixed  $resource  The paginated resource
     * @param  string|null  $resourceClass  Optional resource class to transform items
     */
    public function __construct($resource, ?string $resourceClass = null)
    {
        parent::__construct($resource);
        $this->resourceClass = $resourceClass;
    }

    /**
     * Transform the resource collection into an array.
     * Returns ONLY the data and pagination — no outer wrapping.
     */
    public function toArray($request): array
    {
        return [
            'items' => $this->resourceClass
                ? $this->resourceClass::collection($this->collection)
                : $this->collection,
            'pagination' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with($request): array
    {
        return [];
    }
}
