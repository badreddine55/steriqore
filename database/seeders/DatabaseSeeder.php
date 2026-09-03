<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\RegisterTenantAction;
use App\Domain\Identity\Models\TenantUser;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A bare `User::create()` (the Laravel starter-kit default) has no
     * tenant membership at all, so it can't get past Web\SetCurrentTenant
     * on any real page (S7) — going through the same
     * RegisterTenantAction the "create a practice" flow actually uses
     * means this seeded account is a real owner of a real practice, not
     * a login that only satisfies auth but nothing downstream of it.
     */
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first();

        if (! $tenant) {
            $result = app(RegisterTenantAction::class)->execute(
                tenantName: 'Demo Practice',
                tenantSlug: 'demo',
                ownerName: 'Admin',
                ownerEmail: 'admin@steriqore.local',
                password: 'password',
            );
            $tenant = $result['tenant'];

            TenantContext::run($tenant, function () use ($tenant) {
                $tenant->sites()->create(['name' => 'Cabinet Principal']);
            });
        }

        TenantContext::run($tenant, function () use ($tenant) {
            // Seed Super Admin Role and User
            $superAdminRole = Role::firstOrCreate(
                ['name' => 'super_admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
            );

            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@steriqore.local'],
                [
                    'name' => 'Super Administrateur',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            if (! TenantUser::where('user_id', $superAdmin->id)->where('tenant_id', $tenant->id)->exists()) {
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $superAdmin->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            $superAdmin->syncRoles([$superAdminRole]);

            // Seed Cabinet Admin User
            $adminRole = Role::where('name', 'admin')->where('tenant_id', $tenant->id)->first();
            if ($adminRole) {
                $cabinetAdmin = User::firstOrCreate(
                    ['email' => 'cabinet_admin@steriqore.local'],
                    [
                        'name' => 'Sophie Laurent',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (! TenantUser::where('user_id', $cabinetAdmin->id)->where('tenant_id', $tenant->id)->exists()) {
                    TenantUser::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $cabinetAdmin->id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ]);
                }

                $cabinetAdmin->syncRoles([$adminRole]);
            }

            // Seed Viewer / Auditor User
            $viewerRole = Role::where('name', 'viewer')->where('tenant_id', $tenant->id)->first();
            if ($viewerRole) {
                $viewer = User::firstOrCreate(
                    ['email' => 'viewer@steriqore.local'],
                    [
                        'name' => 'Jean-Luc Mercier (Auditeur ARS)',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (! TenantUser::where('user_id', $viewer->id)->where('tenant_id', $tenant->id)->exists()) {
                    TenantUser::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $viewer->id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ]);
                }

                $viewer->syncRoles([$viewerRole]);
            }

            // Seed Releaser / Quality Manager User
            $releaserRole = Role::where('name', 'releaser')->where('tenant_id', $tenant->id)->first();
            if ($releaserRole) {
                $releaser = User::firstOrCreate(
                    ['email' => 'releaser@steriqore.local'],
                    [
                        'name' => 'Claire Dubois (Responsable Qualité)',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (! TenantUser::where('user_id', $releaser->id)->where('tenant_id', $tenant->id)->exists()) {
                    TenantUser::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $releaser->id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ]);
                }

                $releaser->syncRoles([$releaserRole]);
            }

            // Seed Stock Manager / Assistant User
            $stockManagerRole = Role::where('name', 'stock_manager')->where('tenant_id', $tenant->id)->first();
            if ($stockManagerRole) {
                $stockManager = User::firstOrCreate(
                    ['email' => 'stock_manager@steriqore.local'],
                    [
                        'name' => 'Marie Martin (Gestionnaire de Stock)',
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]
                );

                if (! TenantUser::where('user_id', $stockManager->id)->where('tenant_id', $tenant->id)->exists()) {
                    TenantUser::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $stockManager->id,
                        'status' => 'active',
                        'joined_at' => now(),
                    ]);
                }

                $stockManager->syncRoles([$stockManagerRole]);
            }

            // Seed Reception User
            $receptionRole = Role::firstOrCreate(
                ['name' => 'reception', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
            );
            $reception = User::firstOrCreate(
                ['email' => 'reception@steriqore.local'],
                [
                    'name' => 'Camille Réception',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            if (! TenantUser::where('user_id', $reception->id)->where('tenant_id', $tenant->id)->exists()) {
                TenantUser::create([
                    'tenant_id' => $tenant->id,
                    'user_id' => $reception->id,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            $reception->syncRoles([$receptionRole]);

            // Seed Laboratories
            $lab1 = \App\Domain\Prosthetics\Models\Laboratory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Laboratoire Dentaire Prothèse Plus'],
                [
                    'phone' => '+33 1 45 67 89 10',
                    'email' => 'contact@protheseplus.fr',
                    'address' => '12 Rue de la Paix, 75002 Paris',
                    'contact_person' => 'Marc Voisin',
                    'specialties' => ['Couronne Zircone', 'Bridge céramique', 'Inlay/Onlay', 'Implantologie'],
                ]
            );

            $lab2 = \App\Domain\Prosthetics\Models\Laboratory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Laboratoire Esthétique & Céramique'],
                [
                    'phone' => '+33 1 42 33 44 55',
                    'email' => 'labo@esthetiqueceram.fr',
                    'address' => '8 Avenue Victor Hugo, 75116 Paris',
                    'contact_person' => 'Hélène Girard',
                    'specialties' => ['Facette feldspathique', 'Couronne E-max', 'Prothèse adjointe'],
                ]
            );

            $lab3 = \App\Domain\Prosthetics\Models\Laboratory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'DentArt Conception 3D'],
                [
                    'phone' => '+33 4 72 00 11 22',
                    'email' => 'commande@dentart3d.fr',
                    'address' => '45 Boulevard des Belges, 69006 Lyon',
                    'contact_person' => 'Antoine Roux',
                    'specialties' => ['Aligneur invisible', 'Gouttière occlusale', 'Squelettique titane'],
                ]
            );

            // Seed Prosthetic Cases for Cabinet
            $practitioner = User::where('email', 'practitioner@steriqore.local')->first() ?? $superAdmin;

            $demoCases = [
                [
                    'case_number' => 'DS-2024-001',
                    'patient_name' => 'Jean Dupont',
                    'patient_code' => 'P-00142',
                    'patient_birth_date' => '1982-05-14',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab1->id,
                    'laboratory_name' => $lab1->name,
                    'work_type' => 'Couronne Zircone',
                    'tooth_number' => '16',
                    'impression_date' => now()->subDays(10),
                    'impression_type' => 'Numérique',
                    'sent_to_lab_date' => now()->subDays(9),
                    'expected_return_date' => now()->subDays(3),
                    'returned_from_lab_date' => now()->subDays(3),
                    'planned_placement_date' => now(),
                    'status' => 'received_at_practice',
                    'priority' => 'normal',
                    'total_amount' => 600.0,
                    'deposit_requested' => true,
                    'deposit_received' => true,
                    'deposit_amount' => 250.0,
                    'final_payment_completed' => false,
                    'clinical_notes' => 'Teinte A2, limite sous-gingivale',
                    'internal_notes' => 'Patient préfère les RDV le matin',
                ],
                [
                    'case_number' => 'DS-2024-002',
                    'patient_name' => 'Marie Curie',
                    'patient_code' => 'P-00208',
                    'patient_birth_date' => '1975-11-07',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab2->id,
                    'laboratory_name' => $lab2->name,
                    'work_type' => 'Bridge céramique 3 éléments',
                    'tooth_number' => '24-26',
                    'impression_date' => now()->subDays(20),
                    'impression_type' => 'Physique Silicone',
                    'sent_to_lab_date' => now()->subDays(19),
                    'expected_return_date' => now()->subDays(12),
                    'returned_from_lab_date' => now()->subDays(12),
                    'status' => 'received_at_practice',
                    'priority' => 'high',
                    'total_amount' => 1450.0,
                    'deposit_requested' => true,
                    'deposit_received' => false,
                    'deposit_amount' => 0.0,
                    'final_payment_completed' => false,
                    'clinical_notes' => 'Patient sensible, anesthésie locale recommandée',
                    'internal_notes' => 'Relance effectuée le 28/08, en attente de rappel',
                ],
                [
                    'case_number' => 'DS-2024-003',
                    'patient_name' => 'Robert Marchand',
                    'patient_code' => 'P-00319',
                    'patient_birth_date' => '1968-03-22',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab1->id,
                    'laboratory_name' => $lab1->name,
                    'work_type' => 'Inlay-Core + Couronne',
                    'tooth_number' => '36',
                    'impression_date' => now()->subDays(14),
                    'impression_type' => 'Numérique',
                    'sent_to_lab_date' => now()->subDays(13),
                    'expected_return_date' => now()->subDays(5),
                    'returned_from_lab_date' => now()->subDays(5),
                    'planned_placement_date' => now()->addDay(),
                    'status' => 'received_at_practice',
                    'priority' => 'normal',
                    'total_amount' => 520.0,
                    'deposit_requested' => true,
                    'deposit_received' => true,
                    'deposit_amount' => 150.0,
                    'final_payment_completed' => false,
                    'clinical_notes' => 'Vérifier occlusion statique et dynamique',
                ],
                [
                    'case_number' => 'DS-2024-004',
                    'patient_name' => 'Sophie Bernard',
                    'patient_code' => 'P-00405',
                    'patient_birth_date' => '1990-08-30',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab2->id,
                    'laboratory_name' => $lab2->name,
                    'work_type' => 'Facette céramique E-max',
                    'tooth_number' => '11, 21',
                    'impression_date' => now()->subDays(5),
                    'impression_type' => 'Numérique',
                    'sent_to_lab_date' => now()->subDays(4),
                    'expected_return_date' => now()->addDays(4),
                    'status' => 'in_production',
                    'priority' => 'high',
                    'total_amount' => 980.0,
                    'deposit_requested' => true,
                    'deposit_received' => true,
                    'deposit_amount' => 300.0,
                    'final_payment_completed' => false,
                    'clinical_notes' => 'Teinte BL2, translucidité incisale marquée',
                ],
                [
                    'case_number' => 'DS-2024-005',
                    'patient_name' => 'Alexandre Dumas',
                    'patient_code' => 'P-00512',
                    'patient_birth_date' => '1985-09-18',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab3->id,
                    'laboratory_name' => $lab3->name,
                    'work_type' => 'Gouttière de libération occlusale',
                    'impression_date' => now()->subDays(2),
                    'impression_type' => 'Numérique',
                    'sent_to_lab_date' => now()->subDay(),
                    'expected_return_date' => now()->addDays(7),
                    'status' => 'sent_to_lab',
                    'priority' => 'normal',
                    'total_amount' => 320.0,
                    'deposit_requested' => true,
                    'deposit_received' => false,
                    'deposit_amount' => 0.0,
                    'final_payment_completed' => false,
                ],
                [
                    'case_number' => 'DS-2024-006',
                    'patient_name' => 'Claire Fontaine',
                    'patient_code' => 'P-00601',
                    'patient_birth_date' => '1962-12-05',
                    'practitioner_id' => 1,
                    'practitioner_name' => $practitioner->name,
                    'laboratory_id' => $lab1->id,
                    'laboratory_name' => $lab1->name,
                    'work_type' => 'Couronne Métallo-Céramique',
                    'tooth_number' => '46',
                    'impression_date' => now()->subDays(25),
                    'impression_type' => 'Physique Alginate',
                    'sent_to_lab_date' => now()->subDays(24),
                    'expected_return_date' => now()->subDays(15),
                    'returned_from_lab_date' => now()->subDays(14),
                    'planned_placement_date' => now()->subDays(8),
                    'actual_placement_date' => now()->subDays(8),
                    'status' => 'placed',
                    'priority' => 'normal',
                    'total_amount' => 480.0,
                    'deposit_requested' => true,
                    'deposit_received' => true,
                    'deposit_amount' => 150.0,
                    'final_payment_completed' => true,
                    'clinical_notes' => 'Pose validée, parfait ajustement occlusal',
                ],
            ];

            foreach ($demoCases as $caseData) {
                \App\Domain\Prosthetics\Models\ProstheticCase::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'case_number' => $caseData['case_number'],
                    ],
                    $caseData
                );
            }
        });
    }
}
