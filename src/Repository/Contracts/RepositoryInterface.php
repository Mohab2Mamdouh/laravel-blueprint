<?php

namespace BlueprintPhp\Laravel\Repository\Contracts;

use BlueprintPhp\Laravel\Enums\QueryReturnType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    public function query(
        array $scopes = [],
        array $columns = ['*'],
        array $relations = [],
        QueryReturnType $returnType = QueryReturnType::GET,
        int $perPage = 10,
        string $order = 'DESC',
        string $orderBy = 'id'
    );

    /** @return Collection<int, TModel> */
    public function getAll(array $columns = ['*'], array $relations = []): Collection;

    /** @return Collection<int, TModel> */
    public function getActive(array $columns = ['*'], array $relations = []): Collection;

    /** @return TModel|null */
    public function getById(
        $modelId,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model;

    /** @return Collection<int, TModel> */
    public function get(
        $byColumn,
        $value,
        array $columns = ['*'],
        array $relations = []
    ): array|Collection;

    /** @return TModel|null */
    public function first(
        $byColumn,
        $value,
        array $columns = ['*'],
        array $relations = []
    ): Builder|Model|null;

    /** @return TModel|null */
    public function create(array $payload): ?Model;

    /** @return TModel|null */
    public function updateOrCreate(array $attributes, array $values = [], bool $withTrashed = false): ?Model;

    public function insert(array $payload): bool;

    public function createMany(array $payload): bool;

    /** @return TModel|null */
    public function getFirst(): ?Model;

    /** @return TModel|null */
    public function update($modelId, array $payload): ?Model;

    /** @param TModel|int $modelId */
    public function delete(Model|int $modelId): bool;

    public function forceDelete($modelId): bool;

    public function restore($modelId): bool;

    /** @return TModel|null */
    public function getByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model;

    /** @return Collection<int, TModel> */
    public function getByIds(array $ids, array $columns = ['*'], array $relations = []);

    public function touch($modelId, string $column): bool;

    /** @return TModel */
    public function firstOrCreate(array $attributes = [], array $values = []);

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginate(int $perPage = 10, array $relations = [], $orderBy = 'ASC', $columns = ['*']);

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginateWithQuery(
        $query,
        int $perPage = 10,
        array $relations = [],
        $orderBy = 'ASC',
        $columns = ['*']
    );

    /** @return Collection<int, TModel> */
    public function whereHasMorph($relation, $class);

    /** @return TModel|null */
    public function sync($modelId, string $relation, array $ids, bool $detaching = true): ?Model;
}
