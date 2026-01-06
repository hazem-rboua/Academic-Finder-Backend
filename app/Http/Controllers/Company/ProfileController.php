<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Company - Profile", description: "API endpoints for company profile management")]
class ProfileController extends Controller
{
    #[OA\Get(
        path: "/api/company/profile",
        summary: "Get company profile",
        security: [["sanctum" => []]],
        tags: ["Company - Profile"],
        responses: [
            new OA\Response(response: 200, description: "Company profile data")
        ]
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return response()->json([
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    #[OA\Put(
        path: "/api/company/profile",
        summary: "Update company profile",
        security: [["sanctum" => []]],
        tags: ["Company - Profile"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "name", type: "string", example: "John Doe"),
                    new OA\Property(property: "company_name", type: "string", example: "Acme Corp"),
                    new OA\Property(property: "phone", type: "string", example: "+1234567890")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Profile updated successfully")
        ]
    )]
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        // Update user
        $user->update([
            'name' => $request->name ?? $user->name,
        ]);

        // Update company
        if ($company) {
            $company->update([
                'name' => $request->company_name ?? $company->name,
                'phone' => $request->phone ?? $company->phone,
            ]);
        }

        return response()->json([
            'message' => __('messages.profile_updated'),
            'user' => $user->fresh()->load('company'),
        ]);
    }
}
