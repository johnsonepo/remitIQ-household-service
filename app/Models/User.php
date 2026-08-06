<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'locale',
        'timezone',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Households this user owns.
     */
    public function ownedHouseholds(): HasMany
    {
        return $this->hasMany(Household::class, 'owner_id');
    }

    /**
     * All households this user belongs to (owner or member), via the
     * household_members pivot. Role is available on the pivot, not
     * on this relation directly — see memberships() for the pivot
     * records themselves, e.g. to check a specific role.
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members')
            ->withPivot(['id', 'role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * This user's household membership records directly (useful when
     * you need the pivot row itself, e.g. to check ->role or ->id).
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMember::class);
    }

    /**
     * Budgets this user tracks (per the confirmed design: budgets are
     * owned by the tracking user, not the household collectively).
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * Custom budget categories this user created (default/system
     * categories have a null user_id and aren't included here).
     */
    public function budgetCategories(): HasMany
    {
        return $this->hasMany(BudgetCategory::class);
    }

    /**
     * Remittances this user has sent.
     */
    public function remittances(): HasMany
    {
        return $this->hasMany(Remittance::class);
    }

    /**
     * Household invitations this user has sent to others.
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class, 'invited_by');
    }
}
