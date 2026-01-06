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
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Invitations", description: "Public invitation endpoints for company registration")]
class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService)
    {
    }

    #[OA\Get(
        path: "/api/invitations/validate/{token}",
        summary: "Validate invitation token",
        tags: ["Invitations"],
        parameters: [
            new OA\Parameter(
                name: "token",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        responses: [
            new OA\Response(response: 200, description: "Token is valid"),
            new OA\Response(response: 400, description: "Invalid or expired token")
        ]
    )]
    public function validate(string $token): JsonResponse
    {
        $invitation = $this->invitationService->validateToken($token);

        if (!$invitation) {
            return response()->json([
                'message' => __('messages.invitation_invalid'),
            ], 400);
        }

        return response()->json([
            'message' => __('messages.invitation_valid'),
            'email' => $invitation->email,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    #[OA\Post(
        path: "/api/invitations/accept/{token}",
        summary: "Accept invitation and register company",
        tags: ["Invitations"],
        parameters: [
            new OA\Parameter(
                name: "token",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "company_name", "phone", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "email", type: "string", format: "email", example: "company@example.com"),
                    new OA\Property(property: "company_name", type: "string", example: "Acme Corp"),
                    new OA\Property(property: "phone", type: "string", example: "+1234567890"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "SecurePass123!"),
                    new OA\Property(property: "password_confirmation", type: "string", format: "password", example: "SecurePass123!")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Registration successful"),
            new OA\Response(response: 400, description: "Invalid token or validation error")
        ]
    )]
    public function accept(string $token, RegisterRequest $request): JsonResponse
    {
        $invitation = $this->invitationService->validateToken($token);

        if (!$invitation) {
            return response()->json([
                'message' => __('messages.invitation_invalid'),
            ], 400);
        }

        // Check if email matches
        if ($invitation->email !== $request->email) {
            return response()->json([
                'message' => __('messages.email_mismatch'),
            ], 400);
        }

        // Check if user already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'message' => __('messages.user_exists'),
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
                'message' => __('messages.registration_success'),
                'user' => $user->load('company'),
                'token' => $token,
                'token_type' => 'Bearer',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => __('messages.registration_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
