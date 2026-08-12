<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ============================================================================
 * Base Repository
 * ============================================================================
 *
 * Provides common database operations for all repositories.
 *
 * Feature repositories should extend this class instead of duplicating
 * CRUD logic.
 *
 * Example:
 *
 * class HouseholdRepository extends BaseRepository
 * {
 *     public function __construct(Household $model)
 *     {
 *         parent::__construct($model);
 *     }
 * }
 *
 * ============================================================================
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Repository model.
     */
    protected Model $model;

    /**
     * Create a new repository instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Return all records.
     */
    public function all(): Collection
    {
        return $this->model->all();
    }

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->paginate($perPage);
    }

    /**
     * Find a record by its primary key.
     */
    public function find(int|string $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Find a record or throw an API exception.
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    /**
     * Create a new record.
     */
    public function create(array $attributes): Model
    {
        return $this->model->create($attributes);
    }

    /**
     * Update an existing record.
     */
    public function update(int|string $id, array $attributes): Model
    {
        $record = $this->findOrFail($id);

        $record->update($attributes);

        return $record->refresh();
    }

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool
    {
        return (bool) $this->findOrFail($id)->delete();
    }
}
