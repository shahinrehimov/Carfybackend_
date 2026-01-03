<?php

namespace App\Repositories;

use App\Models\Company;
use App\Models\CompanyAdmin;
use App\Services\FileUpload;
use App\Services\LangService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyAdminRepository
{
    public function load(array $validated, ?int $companyId = null): LengthAwarePaginator
    {
        return CompanyAdmin::query()
            ->when($companyId, function (Builder $builder) use ($companyId) {
                $builder->where('company_id', $companyId);
            }, function (Builder $builder) use ($validated) {
                $builder->with([
                    'company',
                ]);
            })
            ->with([
                'creatable',
                'media',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder) use ($validated) {
                $builder->where(function (Builder $builder) use ($validated) {
                    $builder->whereLike('name', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('surname', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('email', '%' . $validated['search'] . '%');
                    $builder->orWhereLike('username', '%' . $validated['search'] . '%');
                });
            })
            ->when(isset($validated['is_active']), function (Builder $builder) use ($validated) {
                $builder->where('is_active', $validated['is_active']);
            })
            ->when(isset($validated['is_root']), function (Builder $builder) use ($validated) {
                $builder->where('is_root', $validated['is_root']);
            })
            ->orderByDesc('id')
            ->paginate($validated['limit'] ?? 10);
    }

    public function list(?int $companyId = null): Collection
    {
        return CompanyAdmin::query()
            ->with('media')
            ->when($companyId, function (Builder $builder) use ($companyId) {
                $builder->where('company_id', $companyId);
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function checkCompany(Company $company, CompanyAdmin $companyAdmin): void
    {
        if ($companyAdmin->company_id !== $company->id) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('The company admin does not belong to this company!')
                    ->getLang('company_admin_not_belong_to_company')
            );
        }
    }

    public function show(Company $company, CompanyAdmin $companyAdmin): void
    {
        $this->checkCompany($company, $companyAdmin);

        $companyAdmin->load([
            'media',
            'company',
        ]);
    }

    public function createFromData(array $data): CompanyAdmin
    {
        if (!isset($data['company_id'])) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Company ID is required!')
                    ->getLang('company_id_required')
            );
        }

        return DB::transaction(static function () use ($data) {
            return CompanyAdmin::query()->create([
                'company_id' => $data['company_id'],
                'is_root' => $data['is_root'] ?? false,
                'password_must_change' => $data['password_must_change'] ?? true,
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'name' => $data['name'],
                'surname' => $data['surname'],
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function store(Company $company, FormRequest $request): CompanyAdmin
    {
        $validated = $request->validated();

        $validated['company_id'] = $company->id;

        if (!isset($validated['company_id'])) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Company ID is required!')
                    ->getLang('company_id_required')
            );
        }

        return DB::transaction(static function () use ($validated, $request) {
            $companyAdmin = CompanyAdmin::query()->create([
                'company_id' => $validated['company_id'],
                'is_root' => $validated['is_root'] ?? false,
                'password_must_change' => true,
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'name' => $validated['name'],
                'surname' => $validated['surname'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            FileUpload::upload($request, 'image', 'company_admins', $companyAdmin);

            return $companyAdmin;
        });
    }

    public function update(Company $company, CompanyAdmin $companyAdmin, FormRequest $request): CompanyAdmin
    {
        $this->checkCompany($company, $companyAdmin);

        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        return DB::transaction(static function () use ($validated, $companyAdmin, $request) {
            $companyAdmin->update($validated);

            FileUpload::upload($request, 'image', 'company_admins', $companyAdmin);

            return $companyAdmin;
        });
    }

    public function destroy(Company $company, CompanyAdmin $companyAdmin): void
    {
        $this->checkCompany($company, $companyAdmin);

        if ($companyAdmin->is_root) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot delete the root admin!')
                    ->getLang('cannot_delete_root_admin')
            );
        }

        DB::transaction(static function () use ($companyAdmin) {
            $companyAdmin->delete();
        });
    }

    public function changeActiveStatus(Company $company, CompanyAdmin $companyAdmin): CompanyAdmin
    {
        $this->checkCompany($company, $companyAdmin);

        if ($companyAdmin->is_root) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('You cannot deactivate the root admin!')
                    ->getLang('cannot_deactivate_root_admin')
            );
        }

        return DB::transaction(static function () use ($companyAdmin) {
            $companyAdmin->update([
                'is_active' => !$companyAdmin->is_active,
            ]);

            return $companyAdmin;
        });
    }

    public function loadRelations(CompanyAdmin $companyAdmin): CompanyAdmin
    {
        return $companyAdmin->load([
            'media',
            'creatable',
        ]);
    }
}
