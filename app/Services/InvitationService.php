<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InvitationService
{
    /**
     * Create and send an invitation.
     */
    public function createInvitation(string $email, User $admin): Invitation
    {
        // Generate unique token
        $token = $this->generateUniqueToken();
        
        // Calculate expiry date
        $expiryDays = (int) config('app.invitation_expiry_days', 7);
        $expiresAt = now()->addDays($expiryDays);

        // Create invitation
        $invitation = Invitation::create([
            'email' => $email,
            'token' => Hash::make($token), // Hash the token for security
            'invited_by' => $admin->id,
            'expires_at' => $expiresAt,
            'status' => InvitationStatus::PENDING,
        ]);

        // Send invitation email using Notification facade
        \Illuminate\Support\Facades\Notification::route('mail', $email)
            ->notify(new CompanyInvitation($token));

        return $invitation;
    }

    /**
     * Generate a unique random token.
     */
    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Invitation::where('token', Hash::make($token))->exists());

        return $token;
    }

    /**
     * Validate invitation token.
     */
    public function validateToken(string $token): ?Invitation
    {
        // Find invitation by checking hashed tokens
        $invitations = Invitation::where('status', InvitationStatus::PENDING)
            ->where('expires_at', '>', now())
            ->get();

        foreach ($invitations as $invitation) {
            if (Hash::check($token, $invitation->token)) {
                return $invitation;
            }
        }

        return null;
    }

    /**
     * Mark invitation as accepted.
     */
    public function acceptInvitation(Invitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::ACCEPTED,
            'used_at' => now(),
        ]);
    }

    /**
     * Cancel an invitation.
     */
    public function cancelInvitation(Invitation $invitation): void
    {
        $invitation->update([
            'status' => InvitationStatus::CANCELLED,
        ]);
    }

    /**
     * Mark expired invitations.
     */
    public function markExpiredInvitations(): int
    {
        return Invitation::where('status', InvitationStatus::PENDING)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => InvitationStatus::EXPIRED,
            ]);
    }
}

