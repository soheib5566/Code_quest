<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\Role;
use App\Models\Course;
use App\Models\LedgerEntry;
use App\Models\Payout;
use App\Models\Subscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'iban_account'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'student_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'instructor_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'instructor_id');
    }

    public function getOwedBalanceInCents(): int
    {
        return $this->ledgerEntries()
            ->where('status', 'payable')
            ->where('direction', 'credit')
            ->sum('amount_in_cents');
    }

    public function getTotalPaidInCents(): int
    {
        return $this->payouts()
            ->where('status', 'paid')
            ->sum('amount_in_cents');
    }

    public function getTotalEarnedInCents(): int
    {
        return $this->ledgerEntries()
            ->where('type', 'earning')
            ->where('direction', 'credit')
            ->sum('amount_in_cents');
    }
}
