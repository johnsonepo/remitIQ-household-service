<?php

namespace App\Repositories;

use App\Models\Remittance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class RemittanceRepository extends BaseRepository
{
    public function __construct(Remittance $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all remittances belonging to a user.
     *
     * @return Collection<int, Remittance>
     */
    public function forUser(int $userId): Collection
    {
        /** @var Collection<int, Remittance> $remittances */
        $remittances = $this->model
            ->where('user_id', $userId)
            ->with([
                'household',
                'provider',
                'attachments',
            ])
            ->latest('sent_at')
            ->get();

        return $remittances;
    }

    /**
     * Get remittances for a specific user and household.
     *
     * @return Collection<int, Remittance>
     */
    public function forUserHousehold(int $userId, string $householdId): Collection
    {
        /** @var Collection<int, Remittance> $remittances */
        $remittances = $this->model
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->with([
                'household',
                'provider',
                'attachments',
            ])
            ->latest('sent_at')
            ->get();

        return $remittances;
    }

    /**
     * Find a remittance belonging to a specific user.
     */
    public function findForUser(int $userId, string $remittanceId): ?Remittance
    {
        /** @var Remittance|null $remittance */
        $remittance = $this->model
            ->whereKey($remittanceId)
            ->where('user_id', $userId)
            ->with([
                'household',
                'provider',
                'attachments',
            ])
            ->first();

        return $remittance;
    }

    /**
     * Get filtered remittance history for a user.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Remittance>
     */
    public function historyForUser(int $userId, array $filters = []): Collection
    {
        $query = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->with([
                'household',
                'provider',
                'attachments',
            ]);

        if (isset($filters['household_id'])) {
            $query->where('household_id', (string) $filters['household_id']);
        }

        if (isset($filters['transfer_provider_id'])) {
            $query->where('transfer_provider_id', (string) $filters['transfer_provider_id']);
        }

        if (isset($filters['from'])) {
            $query->whereDate('sent_at', '>=', (string) $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('sent_at', '<=', (string) $filters['to']);
        }

        /** @var Collection<int, Remittance> $remittances */
        $remittances = $query
            ->latest('sent_at')
            ->get();

        return $remittances;
    }

    /**
     * Get remittance analytics for a user.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function analyticsForUser(int $userId, array $filters = []): array
    {
        $query = $this->model
            ->newQuery()
            ->where('user_id', $userId);

        if (isset($filters['household_id'])) {
            $query->where('household_id', (string) $filters['household_id']);
        }

        if (isset($filters['transfer_provider_id'])) {
            $query->where('transfer_provider_id', (string) $filters['transfer_provider_id']);
        }

        if (isset($filters['from'])) {
            $query->whereDate('sent_at', '>=', (string) $filters['from']);
        }

        if (isset($filters['to'])) {
            $query->whereDate('sent_at', '<=', (string) $filters['to']);
        }

        $remittances = $query
            ->with(['provider'])
            ->orderBy('sent_at')
            ->get();

        $totalSent = (float) $remittances->sum('amount_sent');
        $totalReceived = (float) $remittances->sum('amount_received');
        $count = $remittances->count();

        $monthly = $remittances
            ->groupBy(function (Model $remittance): string {
                return Carbon::parse($remittance->getAttribute('sent_at'))->format('Y-m');
            })
            ->map(function (Collection $items, string $month): array {
                return [
                    'month' => $month,
                    'count' => $items->count(),
                    'total_sent' => (float) $items->sum('amount_sent'),
                    'total_received' => (float) $items->sum('amount_received'),
                    'average_exchange_rate' => round((float) $items->avg('exchange_rate'), 10),
                ];
            })
            ->values()
            ->all();

        $providers = $remittances
            ->groupBy('transfer_provider_id')
            ->map(function (Collection $items): array {
                /** @var Remittance|null $first */
                $first = $items->first();

                return [
                    'provider_id' => $first?->transfer_provider_id,
                    'provider' => $first?->provider,
                    'count' => $items->count(),
                    'total_sent' => (float) $items->sum('amount_sent'),
                    'total_received' => (float) $items->sum('amount_received'),
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'count' => $count,
                'total_sent' => $totalSent,
                'total_received' => $totalReceived,
                'average_sent' => $count > 0
                    ? round($totalSent / $count, 2)
                    : 0.0,
                'average_received' => $count > 0
                    ? round($totalReceived / $count, 2)
                    : 0.0,
                'average_exchange_rate' => $count > 0
                    ? round((float) $remittances->avg('exchange_rate'), 10)
                    : 0.0,
            ],
            'monthly_trend' => $monthly,
            'providers' => $providers,
        ];
    }
}
