<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Authentication", description: "API endpoints for authentication")]
class AuthController extends Controller
{
    #[OA\Post(
        path: "/api/auth/login",
        summary: "Login user",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string", format: "email", example: "admin@academicfinder.com"),
                    new OA\Property(property: "password", type: "string", format: "password", example: "Admin@123")
                ]
            )
        ),
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful login",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object"),
                        new OA\Property(property: "token", type: "string"),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer")
                    ]
                )
            ),
            new OA\Response(response: 401, description: "Invalid credentials")
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('messages.invalid_credentials')],
            ]);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => __('messages.account_deactivated'),
            ], 403);
        }

        // Check if company is enabled (for company users)
        if ($user->isCompany() && $user->company && !$user->company->is_enabled) {
            return response()->json([
                'message' => __('messages.company_disabled'),
            ], 403);
        }

        // Create token with abilities based on user type
        $abilities = $user->isAdmin() ? ['admin'] : ['company'];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return response()->json([
            'user' => $user->load('company'),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    #[OA\Post(
        path: "/api/auth/logout",
        summary: "Logout user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Successfully logged out")
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => __('messages.logout_success'),
        ]);
    }

    #[OA\Get(
        path: "/api/auth/me",
        summary: "Get authenticated user",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "User data",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "user", type: "object")
                    ]
                )
            )
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('company'),
        ]);
    }

    #[OA\Post(
        path: "/api/auth/refresh",
        summary: "Refresh authentication token",
        security: [["sanctum" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(
                response: 200,
                description: "New token generated",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string"),
                        new OA\Property(property: "token_type", type: "string", example: "Bearer")
                    ]
                )
            )
        ]
    )]
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Delete current token
        $request->user()->currentAccessToken()->delete();
        
        // Create new token with same abilities
        $abilities = $user->isAdmin() ? ['admin'] : ['company'];
        $token = $user->createToken('auth-token', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
