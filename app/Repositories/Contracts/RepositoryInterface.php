<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * @return TModel|null
     */
    public function find(int|string $id): ?Model;

    /**
     * @return TModel
     */
    public function findOrFail(int|string $id): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(int|string $id, array $attributes): Model;

    public function delete(int|string $id): bool;
}
