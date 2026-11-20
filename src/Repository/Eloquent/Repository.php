<?php

namespace BlueprintPhp\Laravel\Repository\Eloquent;

use BlueprintPhp\Laravel\Enums\QueryReturnType;
use BlueprintPhp\Laravel\Repository\Contracts\RepositoryInterface;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class Repository implements RepositoryInterface
{
    /** @var TModel */
    protected Model $model;

    /** @param TModel $model */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Generic query builder with optional named scopes and flexible return types.
     *
     * @param  array  $scopes  Named scopes to apply. Simple list or associative array with parameters.
     * @param  array  $columns  Columns to select.
     * @param  array  $relations  Eager-load relations.
     * @param  QueryReturnType  $returnType  How to return results (GET, FIRST, PAGINATE, QUERY).
     * @param  int  $perPage  Items per page (only used when $returnType is PAGINATE).
     * @param  string  $order  Order direction: ASC or DESC.
     * @param  string  $orderBy  Column to order by.
     *
     * @example
     * // Get all with named scopes
     * $repository->query(['active', 'verified']);
     *
     * // Scopes with parameters
     * $repository->query(['status' => 'active', 'role' => 'admin']);
     *
     * // Get the first result
     * $repository->query(['active'], returnType: QueryReturnType::FIRST);
     *
     * // Paginated results
     * $repository->query(
     *     scopes: ['active'],
     *     returnType: QueryReturnType::PAGINATE,
     *     perPage: 15,
     *     order: 'DESC',
     *     orderBy: 'created_at'
     * );
     *
     * // Return the query builder for further chaining
     * $query = $repository->query(['active'], returnType: QueryReturnType::QUERY);
     * $results = $query->where('created_at', '>', now()->subDays(7))->get();
     */
    public function query(
        array $scopes = [],
        array $columns = ['*'],
        array $relations = [],
        QueryReturnType $returnType = QueryReturnType::GET,
        int $perPage = 10,
        string $order = 'DESC',
        string $orderBy = 'id'
    ) {
        $query = $this->model::query()->select($columns)->with($relations);

        $query = $this->applyScopes($query, $scopes);

        $query = $query->orderBy($orderBy, $order);

        return match ($returnType) {
            QueryReturnType::GET => $query->get(),
            QueryReturnType::FIRST => $query->first(),
            QueryReturnType::FIRST_OR_FAIL => $query->firstOrFail(),
            // Ties on a non-unique sort column make LIMIT/OFFSET unstable: a row repeats on
            // one page and is missing from another. Append the key as a tiebreaker.
            QueryReturnType::PAGINATE => $query
                ->when($orderBy !== $this->model->getKeyName(), fn (Builder $query) => $query->orderBy($this->model->getQualifiedKeyName(), $order))
                ->paginate(request('per_page', $perPage)),
            // Cursor pagination needs a deterministic order for the same reason.
            QueryReturnType::CURSOR_PAGINATE => $query
                ->when($orderBy !== $this->model->getKeyName(), fn (Builder $query) => $query->orderBy($this->model->getQualifiedKeyName(), $order))
                ->cursorPaginate(request('per_page', $perPage)),
            QueryReturnType::QUERY => $query,
            // For PLUCK, pass the value (and optional key) column(s) via $columns,
            // e.g. query(columns: ['name', 'id'], returnType: PLUCK). Defaults to the
            // model's primary key when $columns is left as ['*'].
            QueryReturnType::PLUCK => $query->pluck(
                $columns[0] === '*' ? $this->model->getKeyName() : $columns[0],
                $columns[1] ?? null,
            ),
            QueryReturnType::COUNT => $query->count(),
            QueryReturnType::EXISTS => $query->exists(),
        };
    }

    /**
     * Apply a scopes array to ANY builder of this repository's model — including a
     * relation's builder, which is why it takes the query instead of reading
     * `$this->model`: constraining an eager load (`with(['items' => …])`) or a
     * `whereHas` subquery has to run the same named scopes the listing does, or the
     * two disagree about what a filter means.
     *
     * - Scopes run in the model's declared order when it uses AcceptedScopes
     *   (`orderScopes()`), not in request order.
     * - An entry the model does not recognise (named scope, local or global
     *   builder macro) is skipped, never fatal.
     * - Only a LIST-shaped parameter is spread as varargs; any other value —
     *   including a string-keyed array — is passed as one argument, so
     *   `?scopes[byFrom][x]=1` cannot become a named argument and 500.
     *
     * Callers taking scopes from a request should still whitelist them against
     * the model's `acceptedScopes()`: builder macros such as SoftDeletes'
     * `onlyTrashed` are callable here by design.
     *
     * @param  Builder<TModel>  $query
     * @param  array<int|string, mixed>  $scopes
     * @return Builder<TModel>
     */
    public function applyScopes(Builder $query, array $scopes): Builder
    {
        $model = $query->getModel();

        if (method_exists($model, 'orderScopes')) {
            $scopes = $model->orderScopes($scopes);
        }

        foreach ($scopes as $scope => $parameters) {
            if (is_int($scope)) {
                // No parameters: ['active', 'verified']
                if (is_string($parameters) && $this->isApplicableScope($query, $parameters)) {
                    $query = $query->{$parameters}();
                }

                continue;
            }

            // With parameters: ['status' => 'active'] or ['byIds' => [1, 2]]
            if ($this->isApplicableScope($query, $scope)) {
                $params = is_array($parameters) && array_is_list($parameters) ? $parameters : [$parameters];
                $query = $query->{$scope}(...$params);
            }
        }

        return $query;
    }

    /**
     * Whether a scope name is callable on the query: a model named scope, a
     * local macro, or a GLOBAL Eloquent Builder macro (e.g. `byKeywords`,
     * registered via Builder::macro — hasMacro() alone misses these). Reads the
     * model off the query so it also works on a relation's builder.
     */
    private function isApplicableScope(Builder $query, string $name): bool
    {
        return $query->getModel()->hasNamedScope($name)
            || $query->hasMacro($name)
            || Builder::hasGlobalMacro($name);
    }

    /** @return Collection<int, TModel> */
    public function getAll(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    /** @return Collection<int, TModel> */
    public function getActive(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->where('is_active', true)->get($columns);
    }

    /** @return TModel|null */
    public function getById(
        $modelId,
        array $columns = ['*'],
        array $relations = [],
        array $appends = []
    ): ?Model {
        return $this->model->select($columns)->with($relations)->findOrFail($modelId)->append($appends);
    }

    /** @return Collection<int, TModel> */
    public function get(
        $byColumn,
        $value,
        array $columns = ['*'],
        array $relations = []
    ): array|Collection {
        return $this->model::query()->select($columns)->with($relations)->where($byColumn, $value)->get();
    }

    /** @return TModel|null */
    public function first(
        $byColumn,
        $value,
        array $columns = ['*'],
        array $relations = []
    ): Builder|Model|null {
        return $this->model::query()->select($columns)->with($relations)->where($byColumn, $value)->first();
    }

    /** @return TModel|null */
    public function getFirst(): ?Model
    {
        return $this->model->first();
    }

    /** @return TModel|null */
    public function create(array $payload): ?Model
    {
        $model = $this->model->create($payload);

        return $model->fresh();
    }

    /** @return TModel|null */
    public function updateOrCreate(array $attributes, array $values = [], bool $withTrashed = false): ?Model
    {
        if ($withTrashed) {
            $values['deleted_at'] = null;
        }

        $model = $this->model::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->updateOrCreate($attributes, $values);

        return $model->fresh();
    }

    public function insert(array $payload): bool
    {
        return $this->model::query()->insert($payload);
    }

    public function createMany(array $payload): bool
    {
        try {
            foreach ($payload as $record) {
                $this->model::query()->create($record);
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /** @return TModel|null */
    public function update($modelId, array $payload): ?Model
    {
        $model = $this->getById($modelId);

        return $model->update($payload)
            ? $model->refresh()
            : null;
    }

    /** @param TModel|int $modelId */
    public function delete(Model|int $modelId): bool
    {
        $model = $modelId instanceof Model ? $modelId : $this->getById($modelId);

        return $model->delete();
    }

    public function forceDelete($modelId): bool
    {
        $model = $this->getById($modelId);

        return $model->forceDelete();
    }

    public function restore($modelId): bool
    {
        $model = $this->query(returnType: QueryReturnType::QUERY)
            ->onlyTrashed()
            ->where('id', $modelId)
            ->first();

        return $model->restore();
    }

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginate(int $perPage = 10, array $relations = [], $orderBy = 'ASC', $columns = ['*'])
    {
        return $this->model::query()
            ->select($columns)
            ->with($relations)
            ->orderBy('id', $orderBy)
            ->paginate(request('per_page', $perPage));
    }

    /** @return LengthAwarePaginator<int, TModel> */
    public function paginateWithQuery(
        $query,
        int $perPage = 10,
        array $relations = [],
        $orderBy = 'ASC',
        $columns = ['*']
    ) {
        return $this->model::query()
            ->select($columns)
            ->where($query)
            ->with($relations)
            ->orderBy('id', $orderBy)
            ->paginate(request('per_page', $perPage));
    }

    /** @return Collection<int, TModel> */
    public function whereHasMorph($relation, $class)
    {
        return $this->model::query()->whereHasMorph($relation, $class)->get();
    }

    /** @return TModel|null */
    public function sync($modelId, string $relation, array $ids, bool $detaching = true): ?Model
    {
        $model = $this->getById($modelId);

        $model->{$relation}()->sync($ids, $detaching);

        return $model->fresh();
    }

    /** @return TModel|null */
    public function getByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model::query()
            ->select($columns)
            ->with($relations)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function touch($modelId, string $column): bool
    {
        return $this->model::query()->where('id', $modelId)->touch($column);
    }

    /** @return Collection<int, TModel> */
    public function getByIds(array $ids, array $columns = ['*'], array $relations = [])
    {
        return $this->model::query()
            ->select($columns)
            ->with($relations)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    /** @return TModel */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        return $this->model::query()->firstOrCreate($attributes, $values);
    }
}
