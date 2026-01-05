<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'email',
        'token',
        'invited_by',
        'expires_at',
        'used_at',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'status' => InvitationStatus::class,
        ];
    }

    /**
     * Get the admin who sent this invitation.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Check if invitation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === InvitationStatus::EXPIRED;
    }

    /**
     * Check if invitation is valid (pending and not expired).
     */
    public function isValid(): bool
    {
        return $this->status === InvitationStatus::PENDING && !$this->isExpired();
    }

    /**
     * Scope to get only pending invitations.
     */
    public function scopePending($query)
    {
        return $query->where('status', InvitationStatus::PENDING);
    }

    /**
     * Scope to get only valid (pending and not expired) invitations.
     */
    public function scopeValid($query)
    {
        return $query->where('status', InvitationStatus::PENDING)
                     ->where('expires_at', '>', now());
    }
}
