<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Company - Profile",
 *     description="API endpoints for company profile management"
 * )
 */
class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/company/profile",
     *     summary="Get company profile",
     *     tags={"Company - Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Company profile data")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('company');

        return response()->json([
            'user' => $user,
            'company' => $user->company,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/company/profile",
     *     summary="Update company profile",
     *     tags={"Company - Profile"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="company_name", type="string", example="Acme Corp"),
     *             @OA\Property(property="phone", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated successfully")
     * )
     */
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
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load('company'),
        ]);
    }
}
