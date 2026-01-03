<?php

namespace App\Services;

use App\Http\Resources\Sellers\Auth\UserResource;
use App\Models\Company;
use App\Models\CompanyAdmin;
use App\Repositories\CompanyAdminRepository;
use App\Repositories\CompanyRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CompanyService
{
    private static ?CompanyService $instance = null;
    private CompanyRepository $companyRepository;
    private CompanyAdminRepository $companyAdminRepository;

    private function __construct()
    {
        $this->companyRepository = new CompanyRepository();
        $this->companyAdminRepository = new CompanyAdminRepository();
    }

    public static function instance(): CompanyService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Base method to create company with root admin
     */
    private function createCompanyWithAdmin(
        array $companyData,
        array $adminData,
        ?FormRequest $request = null,
        bool $autoVerify = false,
        bool $passwordMustChange = false
    ): Company {
        $_this = $this;

        return DB::transaction(function () use ($companyData, $adminData, $request, $autoVerify, $passwordMustChange, $_this) {
            // Prepare company data for creation
            $companyCreateData = [
                'voen' => $companyData['voen'],
                'phone' => $companyData['phone'],
                'email' => $companyData['email'],
                'latitude' => $companyData['latitude'] ?? null,
                'longitude' => $companyData['longitude'] ?? null,
                'website' => $companyData['website'] ?? null,
                'root_admin_email' => $adminData['email'],
                'verified_at' => $autoVerify ? now() : null,
                'is_active' => $companyData['is_active'] ?? true,
                'translations' => $companyData['translations'] ?? [],
            ];

            // Create company via repository
            $company = $_this->companyRepository->createFromData($companyCreateData, $request);

            // Prepare admin data for creation
            $adminCreateData = [
                'company_id' => $company->id,
                'is_root' => true,
                'password_must_change' => $passwordMustChange,
                'username' => $adminData['username'],
                'email' => $adminData['email'],
                'password' => $adminData['password'],
                'name' => $adminData['name'],
                'surname' => $adminData['surname'],
                'is_active' => true,
            ];

            // Create root admin via repository
            $_this->companyAdminRepository->createFromData($adminCreateData);

            return $company;
        });
    }

    /**
     * Register company by user (self-registration)
     * Company: verified_at = null (needs admin verification)
     * Root Admin: password_must_change = false (user chose password)
     */
    public function register(FormRequest $request): CompanyAdmin
    {
        $validated = $request->validated();

        try {
            $company = $this->createCompanyWithAdmin(
                companyData: $validated['company'],
                adminData: $validated['admin'],
                request: $request,
                autoVerify: false,
                passwordMustChange: false
            );

            return $company->admins()->root()->active()->first();
        } catch (\Exception $e) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Registration failed. Please try again.')
                    ->getLang('registration_failed')
            );
        }
    }

    /**
     * Create company by admin
     * Company: verified_at = now() (auto-verified)
     * Root Admin: password_must_change = true (admin generated password)
     */
    public function createByAdmin(FormRequest $request): Company
    {
        $validated = $request->validated();

        // Generate password based on validation rules
        $generatedPassword = $this->generatePassword();

        // Prepare company data
        $companyData = [
            'voen' => $validated['voen'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'website' => $validated['website'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'translations' => $validated['translations'] ?? [],
        ];

        // Prepare admin data
        $adminData = [
            'username' => $validated['root_admin_username'],
            'email' => $validated['root_admin_email'],
            'password' => $generatedPassword,
            'name' => $validated['root_admin_name'],
            'surname' => $validated['root_admin_surname'],
        ];

        $company = $this->createCompanyWithAdmin(
            companyData: $companyData,
            adminData: $adminData,
            request: $request,
            autoVerify: true,
            passwordMustChange: true
        );

        // TODO: Send email with credentials to root admin
        // CompanyAdminService::instance()->sendRootAdminCredentialsNotification($company, $adminData['email'], $generatedPassword);

        return $company;
    }

    /**
     * Generate password based on validation rules
     */
    private function generatePassword(): string
    {
        $minLength = 12; // Default minimum length

        // Generate password with letters, numbers and symbols
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $generatedPassword = '';

        for ($i = 0; $i < $minLength; $i++) {
            $generatedPassword .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $generatedPassword;
    }
}
