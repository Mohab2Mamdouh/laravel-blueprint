<?php

namespace BlueprintPhp\Laravel\Tests;

use BlueprintPhp\Laravel\Enums\QueryReturnType;
use BlueprintPhp\Laravel\Repository\Eloquent\Repository;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ApplyScopesWidget extends Model
{
    protected $table = 'widgets';

    public $timestamps = false;

    protected $guarded = [];

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', 'active');
    }

    #[Scope]
    protected function byStatus(Builder $query, string ...$statuses): void
    {
        $query->whereIn('status', $statuses);
    }

    #[Scope]
    protected function byRange(Builder $query, array $range): void
    {
        $query->whereBetween('rank', [$range['from'], $range['to']]);
    }
}

class ApplyScopesWidgetRepository extends Repository
{
    public function __construct(ApplyScopesWidget $model)
    {
        parent::__construct($model);
    }
}

class RepositoryApplyScopesTest extends TestCase
{
    private ApplyScopesWidgetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->integer('rank');
        });

        foreach ([['active', 1], ['active', 5], ['inactive', 3], ['draft', 9]] as [$status, $rank]) {
            ApplyScopesWidget::query()->create(['status' => $status, 'rank' => $rank]);
        }

        $this->repository = new ApplyScopesWidgetRepository(new ApplyScopesWidget);
    }

    public function test_list_parameters_are_spread_as_varargs(): void
    {
        $count = $this->repository->query(
            scopes: ['byStatus' => ['active', 'draft']],
            returnType: QueryReturnType::COUNT,
        );

        $this->assertSame(3, $count);
    }

    public function test_a_string_keyed_array_is_passed_as_one_argument(): void
    {
        // Spreading it would turn `from`/`to` into named arguments and fatal.
        $count = $this->repository->query(
            scopes: ['byRange' => ['from' => 2, 'to' => 9]],
            returnType: QueryReturnType::COUNT,
        );

        $this->assertSame(3, $count);
    }

    public function test_unknown_scopes_are_skipped(): void
    {
        $count = $this->repository->query(
            scopes: ['active', 'notAScope', 'alsoMissing' => 'x', 7 => ['not-a-name']],
            returnType: QueryReturnType::COUNT,
        );

        $this->assertSame(2, $count);
    }

    public function test_apply_scopes_works_on_any_builder_of_the_model(): void
    {
        $query = $this->repository->applyScopes(
            ApplyScopesWidget::query()->where('rank', '>', 1),
            ['active'],
        );

        $this->assertSame(1, $query->count());
    }

    public function test_paginate_is_stable_on_a_non_unique_sort_column(): void
    {
        $page = $this->repository->query(
            returnType: QueryReturnType::PAGINATE,
            perPage: 10,
            order: 'ASC',
            orderBy: 'status',
        );

        // status ties (two `active`) are broken by the primary key.
        $this->assertSame([1, 2, 4, 3], collect($page->items())->pluck('id')->all());
    }
}
