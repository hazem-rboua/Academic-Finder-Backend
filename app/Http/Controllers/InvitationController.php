<?php

namespace App\Http\Controllers;

use App\Enums\UserType;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Invitations",
 *     description="Public invitation endpoints for company registration"
 * )
 */
class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/invitations/validate/{token}",
     *     summary="Validate invitation token",
     *     tags={"Invitations"},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="Token is valid"),
     *     @OA\Response(response=400, description="Invalid or expired token")
     * )
     */
    public function validate(string $token): JsonResponse
    {
        $invitation = $this->invitationService->validateToken($token);

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid or expired invitation token.',
            ], 400);
        }

        return response()->json([
            'message' => 'Token is valid',
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/invitations/accept/{token}",
     *     summary="Accept invitation and register company",
     *     tags={"Invitations"},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","company_name","phone","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="company_name", type="string", example="Acme Corp"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="password", type="string", format="password", example="SecurePass123!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="SecurePass123!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration successful"),
     *     @OA\Response(response=400, description="Invalid token or validation error")
     * )
     */
    public function accept(string $token, RegisterRequest $request): JsonResponse
    {
        $invitation = $this->invitationService->validateToken($token);

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid or expired invitation token.',
            ], 400);
        }

        // Check if email matches
        if ($invitation->email !== $request->email) {
            return response()->json([
                'message' => 'Email does not match the invitation.',
            ], 400);
        }

        // Check if user already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => 'A user with this email already exists.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create company
            $company = Company::create([
                'name' => $request->company_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'is_enabled' => true,
            ]);

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'user_type' => UserType::COMPANY,
                'is_active' => true,
                'company_id' => $company->id,
            ]);

            // Mark invitation as accepted
            $this->invitationService->acceptInvitation($invitation);

            DB::commit();

            // Create token
            $token = $user->createToken('auth-token', ['company'])->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'user' => $user->load('company'),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
