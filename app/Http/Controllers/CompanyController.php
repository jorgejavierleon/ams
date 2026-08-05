<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Region;
use App\Models\User;
use App\Rules\ValidRut;
use App\Support\Rut;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The organization's single employer profile (KOL-32).
 *
 * There is no listing, no create and no delete: an organization *is* one
 * employer, enforced by a unique index on `companies.organization_id`. This is
 * account configuration — one form, edited in place. The many-to-one accounting
 * dimension lives in {@see CostCenterController}.
 */
class CompanyController extends Controller
{
    public function edit(): Response
    {
        $company = $this->currentCompany();

        $company?->load(['representatives:id,company_id,rut,first_name,last_name,second_last_name,personal_email']);

        return Inertia::render('companies/edit', [
            'company' => $company === null ? null : [
                'id' => $company->id,
                'rut' => $company->formatted_rut,
                'social_reason' => $company->social_reason,
                'business_line' => $company->business_line,
                'email' => $company->email,
                'region_id' => $company->region_id,
                'commune_id' => $company->commune_id,
                'address' => $company->address,
                'phone' => $company->phone,
                'company_type' => $company->company_type,
                'is_est' => $company->is_est,
                'is_active' => $company->is_active,
                'representatives' => $company->representatives->map(fn (User $user) => [
                    'id' => $user->id,
                    'rut' => $user->formatted_rut,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'second_last_name' => $user->second_last_name,
                    'email' => $user->personal_email,
                ])->all(),
            ],
            'regions' => $this->regionOptions(),
        ]);
    }

    /**
     * Save the employer profile, creating it on first submit for an
     * organization that does not have one yet.
     */
    public function update(Request $request): RedirectResponse
    {
        $company = $this->currentCompany();
        $data = $this->validateCompany($request);

        DB::transaction(function () use ($company, $data) {
            $company = $company === null
                ? Company::create(Arr::except($data, 'representatives'))
                : tap($company)->update(Arr::except($data, 'representatives'));

            $this->syncRepresentatives($company, $data['representatives'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.companies.flash.updated')]);

        return to_route('company.edit');
    }

    /**
     * The organization's single company, or null before it has been set up.
     * The `OrganizationScope` on the model does the tenant filtering.
     */
    private function currentCompany(): ?Company
    {
        return Company::query()->first();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function regionOptions(): array
    {
        return Region::query()
            ->orderBy('order')
            ->get(['id', 'name'])
            ->map(fn (Region $region) => ['value' => $region->id, 'label' => $region->name])
            ->all();
    }

    /**
     * Validate the company payload, normalising every RUT first so uniqueness
     * checks and storage share the same canonical form.
     *
     * @return array<string, mixed>
     */
    private function validateCompany(Request $request): array
    {
        $representatives = array_values($request->input('representatives', []));

        foreach ($representatives as $index => $representative) {
            if (isset($representative['rut'])) {
                $representatives[$index]['rut'] = Rut::normalize((string) $representative['rut']);
            }
        }

        $request->merge([
            'rut' => Rut::normalize((string) $request->input('rut')),
            'representatives' => $representatives,
            'is_est' => $request->boolean('is_est'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $rules = [
            'rut' => ['required', 'string', new ValidRut],
            'social_reason' => ['required', 'string', 'max:255'],
            'business_line' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'commune_id' => [
                'required', 'integer',
                Rule::exists('communes', 'id')->where('region_id', $request->integer('region_id')),
            ],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'company_type' => ['required', 'string', 'max:255'],
            'is_est' => ['boolean'],
            'is_active' => ['boolean'],
            'representatives' => ['array'],
        ];

        foreach ($representatives as $index => $representative) {
            $rules["representatives.{$index}.id"] = ['nullable', 'integer'];
            $rules["representatives.{$index}.rut"] = [
                'required', 'string', new ValidRut,
                Rule::unique('users', 'rut')->ignore($representative['id'] ?? null),
            ];
            $rules["representatives.{$index}.first_name"] = ['required', 'string', 'max:255'];
            $rules["representatives.{$index}.last_name"] = ['required', 'string', 'max:255'];
            $rules["representatives.{$index}.second_last_name"] = ['nullable', 'string', 'max:255'];
            $rules["representatives.{$index}.email"] = ['required', 'email', 'max:255'];
        }

        return $request->validate($rules);
    }

    /**
     * Create, update and prune the company's legal representatives so the
     * stored set matches the submitted one.
     *
     * @param  array<int, array<string, mixed>>  $representatives
     */
    private function syncRepresentatives(Company $company, array $representatives): void
    {
        $keptIds = [];

        foreach ($representatives as $representative) {
            $attributes = [
                'rut' => $representative['rut'],
                'first_name' => $representative['first_name'],
                'last_name' => $representative['last_name'],
                'second_last_name' => $representative['second_last_name'] ?? null,
                'name' => trim("{$representative['first_name']} {$representative['last_name']}"),
                'email' => $representative['email'],
                'personal_email' => $representative['email'],
            ];

            $existing = ! empty($representative['id'])
                ? $company->representatives()->whereKey($representative['id'])->first()
                : null;

            if ($existing !== null) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $created = $company->representatives()->create([
                ...$attributes,
                'organization_id' => $company->organization_id,
                'password' => $representative['rut'],
                'is_legal_rep' => true,
            ]);

            $keptIds[] = $created->id;
        }

        $company->representatives()->whereKeyNot($keptIds)->delete();
    }
}
