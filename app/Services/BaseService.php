<?php

namespace App\Services;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ============================================================================
 * Base Service
 * ============================================================================
 *
 * Provides a reusable service layer for all business modules.
 *
 * Services should contain business rules while delegating persistence
 * to repositories.
 *
 * Example:
 *
 * class HouseholdService extends BaseService
 * {
 *     public function __construct(HouseholdRepository $repository)
 *     {
 *         parent::__construct($repository);
 *     }
 *
 *     public function inviteMember(...)
 *     {
 *         // business logic
 *     }
 * }
 *
 * ============================================================================
 */
abstract class BaseService
{
    /**
     * Repository instance.
     */
    protected RepositoryInterface $repository;

    /**
     * Create a new service instance.
     */
    public function __construct(RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Return all records.
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Paginate records.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Find a record.
     */
    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    /**
     * Find a record or fail.
     */
    public function findOrFail(int|string $id): Model
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Create a resource.
     */
    public function create(array $attributes): Model
    {
        return $this->repository->create($attributes);
    }

    /**
     * Update a resource.
     */
    public function update(int|string $id, array $attributes): Model
    {
        return $this->repository->update($id, $attributes);
    }

    /**
     * Delete a resource.
     */
    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}
