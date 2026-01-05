<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Invitations",
 *     description="API endpoints for managing invitations (Admin only)"
 * )
 */
class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/invitations",
     *     summary="List all invitations",
     *     tags={"Admin - Invitations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         @OA\Schema(type="string", enum={"pending", "accepted", "expired", "cancelled"})
     *     ),
     *     @OA\Response(response=200, description="List of invitations")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invitation::with('invitedBy')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $invitations = $query->paginate(15);

        return response()->json($invitations);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/invitations",
     *     summary="Send invitation to a company",
     *     tags={"Admin - Invitations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="company@example.com")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Invitation sent successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(SendInvitationRequest $request): JsonResponse
    {
        // Check if user already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => __('messages.user_exists'),
            ], 422);
        }

        // Check if there's already a pending invitation
        $existingInvitation = Invitation::where('email', $request->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'message' => __('messages.invitation_exists'),
            ], 422);
        }

        $invitation = $this->invitationService->createInvitation(
            $request->email,
            $request->user()
        );

        return response()->json([
            'message' => __('messages.invitation_sent'),
            'invitation' => $invitation,
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/invitations/{id}",
     *     summary="Cancel an invitation",
     *     tags={"Admin - Invitations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Invitation cancelled successfully")
     * )
     */
    public function destroy(Invitation $invitation): JsonResponse
    {
        $this->invitationService->cancelInvitation($invitation);

        return response()->json([
            'message' => __('messages.invitation_cancelled'),
        ]);
    }
}
