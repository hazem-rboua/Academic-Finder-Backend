<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyService
{
    /**
     * Enable a company.
     */
    public function enableCompany(Company $company): Company
    {
        $company->update([
            'is_enabled' => true,
            'disabled_at' => null,
            'disabled_by' => null,
        ]);

        // Activate all users of this company
        $company->users()->update(['is_active' => true]);

        return $company->fresh();
    }

    /**
     * Disable a company.
     */
    public function disableCompany(Company $company, User $admin): Company
    {
        $company->update([
            'is_enabled' => false,
            'disabled_at' => now(),
            'disabled_by' => $admin->id,
        ]);

        // Deactivate all users of this company
        $company->users()->update(['is_active' => false]);

        return $company->fresh();
    }

    /**
     * Get all companies with filters.
     */
    public function getCompanies(array $filters = [])
    {
        $query = Company::with(['users', 'disabledBy']);

        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', $filters['is_enabled']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }
}

