<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Companies",
 *     description="API endpoints for managing companies (Admin only)"
 * )
 */
class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService)
    {
    }

    /**
     * @OA\Get(
     *     path="/api/admin/companies",
     *     summary="List all companies",
     *     tags={"Admin - Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="is_enabled",
     *         in="query",
     *         description="Filter by enabled status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name or email",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response=200, description="List of companies")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['is_enabled', 'search', 'per_page']);
        $companies = $this->companyService->getCompanies($filters);

        return response()->json($companies);
    }

    /**
     * @OA\Get(
     *     path="/api/admin/companies/{id}",
     *     summary="Get company details",
     *     tags={"Admin - Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Company details"),
     *     @OA\Response(response=404, description="Company not found")
     * )
     */
    public function show(Company $company): JsonResponse
    {
        return response()->json([
            'company' => $company->load(['users', 'disabledBy']),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/companies/{id}/enable",
     *     summary="Enable a company",
     *     tags={"Admin - Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Company enabled successfully")
     * )
     */
    public function enable(Company $company): JsonResponse
    {
        $company = $this->companyService->enableCompany($company);

        return response()->json([
            'message' => __('messages.company_enabled'),
            'company' => $company,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/admin/companies/{id}/disable",
     *     summary="Disable a company",
     *     tags={"Admin - Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Company disabled successfully")
     * )
     */
    public function disable(Company $company, Request $request): JsonResponse
    {
        $company = $this->companyService->disableCompany($company, $request->user());

        return response()->json([
            'message' => __('messages.company_disabled'),
            'company' => $company,
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/admin/companies/{id}",
     *     summary="Delete a company",
     *     tags={"Admin - Companies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Company deleted successfully")
     * )
     */
    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json([
            'message' => __('messages.company_deleted'),
        ]);
    }
}
