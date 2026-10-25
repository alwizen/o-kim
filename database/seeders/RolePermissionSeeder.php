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
            'verify_documents',
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
            'issue_kim',
            'schedule_inspection',
            'manage_roles',
            'manage_notification_settings',
            'approve_tad_findings',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $tadPermissions = fn (string $dept): array => [
            'access_admin_panel',
            'view_applications',
            'inspect_'.$dept,
            'manage_findings',
            'view_findings',
        ];

        $pengawasPermissions = fn (string $dept): array => [
            'access_admin_panel',
            'view_applications',
            'view_findings',
            'verify_finding_actions',
            'approve_tad_findings',
            'approve_'.$dept,
            'issue_kim',
            'view_kim_cards',
        ];

        $roles = [
            'super_admin' => $permissions,
            'admin_verifikasi' => [
                'access_admin_panel',
                'review_transporter_registrations',
                'manage_transporters',
                'manage_vehicles',
                'verify_documents',
                'view_applications',
                'view_findings',
                'manage_checklists',
                'schedule_inspection',
                'issue_kim',
                'view_kim_cards',
            ],
            'hsse' => $tadPermissions('hsse'),
            'distribusi' => $tadPermissions('distribusi'),
            'qq' => $tadPermissions('qq'),
            'ftm' => [
                'access_admin_panel',
                'view_applications',
                'view_findings',
                'approve_final',
                'issue_kim',
                'view_kim_cards',
                'verify_documents',
            ],
            'transportir' => [],
            'pengawas_hsse' => $pengawasPermissions('hsse'),
            'pengawas_qq' => $pengawasPermissions('qq'),
            'pengawas_distribusi' => $pengawasPermissions('distribusi'),
        ];

        foreach ($roles as $name => $rolePermissions) {
            $role = Role::findOrCreate($name, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
