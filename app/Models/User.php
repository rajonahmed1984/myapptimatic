<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Concerns\HasActivityTracking;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasActivityTracking;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'customer_id',
        'project_id',
        'currency',
        'avatar_path',
        'nid_path',
        'cv_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'password' => 'hashed',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function clientRequests(): HasMany
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, Role::adminRoles(), true);
    }

    public function isMasterAdmin(): bool
    {
        return $this->role === Role::MASTER_ADMIN;
    }

    public function isSubAdmin(): bool
    {
        return $this->role === Role::SUB_ADMIN;
    }

    public function isSales(): bool
    {
        return $this->role === Role::SALES;
    }

    public function isSupport(): bool
    {
        return $this->role === Role::SUPPORT;
    }

    public function isClient(): bool
    {
        return $this->role === Role::CLIENT;
    }

    public function isClientProject(): bool
    {
        return $this->role === Role::CLIENT_PROJECT;
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isEmployee(): bool
    {
        return $this->employee()->exists();
    }
}
