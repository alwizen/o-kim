<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'access_admin_panel',
            'manage_users',
            'review_transporter_registrations',
            'manage_transporters',
            'manage_vehicles',
            'manage_document_types',
            'manage_checklists',
            'view_applications',
            'inspect_hsse',
            'inspect_distribusi',
            'inspect_qq',
            'manage_findings',
            'view_findings',
            'verify_finding_actions',
            'approve_hsse',
            'approve_distribusi',
            'approve_qq',
            'approve_final',
            'view_kim_cards',
            'view_audit_logs',
            'manage_settings',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'super_admin' => $permissions,
            'admin_verifikasi' => [
                'access_admin_panel',
                'review_transporter_registrations',
                'manage_transporters',
            ],
            'hsse' => [
                'access_admin_panel',
                'view_applications',
                'inspect_hsse',
                'manage_findings',
                'view_findings',
                'verify_finding_actions',
                'approve_hsse',
            ],
            'distribusi' => [
                'access_admin_panel',
                'view_applications',
                'inspect_distribusi',
                'manage_findings',
                'view_findings',
                'verify_finding_actions',
                'approve_distribusi',
            ],
            'qq' => [
                'access_admin_panel',
                'view_applications',
                'inspect_qq',
                'manage_findings',
                'view_findings',
                'verify_finding_actions',
                'approve_qq',
            ],
            'ftm' => [
                'access_admin_panel',
                'view_applications',
                'view_findings',
                'approve_final',
                'view_kim_cards',
            ],
            'transportir' => [],
        ];

        foreach ($roles as $name => $rolePermissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
