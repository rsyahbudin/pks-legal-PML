<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Department;
use App\Models\Division;
use App\Models\DocumentType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->division = Division::factory()->create();
    $this->department = Department::factory()->create(['division_id' => $this->division->id]);

    // Create role with reports.export permission
    $this->role = Role::factory()->create(['slug' => 'legal']);
    $permission = Permission::firstOrCreate(
        ['slug' => 'reports.export'],
        [
            'name' => 'Export Laporan',
            'group' => 'reports',
            'description' => 'Export laporan ke Excel/CSV',
        ]
    );
    $this->role->permissions()->sync([$permission->id]);

    $this->user = User::factory()->create([
        'role_id' => $this->role->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);
});

test('authenticated user with permission can export contracts to excel', function () {
    // Create a document type
    $docType = DocumentType::firstOrCreate(
        ['code' => 'perjanjian'],
        ['name' => 'Perjanjian', 'is_active' => true]
    );

    // Create contract status
    $status = ContractStatus::firstOrCreate(
        ['code' => 'active'],
        ['name' => 'Active', 'color' => 'green', 'is_active' => true, 'sort_order' => 1]
    );

    // Create a ticket first
    $ticket = Ticket::factory()->create([
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'counterpart_name' => 'PT Test Company',
    ]);

    // Create a contract
    Contract::create([
        'ticket_id' => $ticket->id,
        'contract_number' => 'CTR-TST-26010001',
        'agreement_name' => 'Test Agreement',
        'proposed_document_title' => 'Test Contract Title',
        'document_type_id' => $docType->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'pic_id' => $this->user->id,
        'start_date' => now(),
        'end_date' => now()->addYear(),
        'status_id' => $status->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('contracts.export'));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition');
});

test('unauthenticated user cannot export contracts', function () {
    $response = $this->get(route('contracts.export'));

    $response->assertRedirect(route('login'));
});

test('user without permission cannot export contracts', function () {
    $noPermRole = Role::factory()->create(['slug' => 'no-perm']);
    $user = User::factory()->create([
        'role_id' => $noPermRole->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('contracts.export'));

    $response->assertForbidden();
});

test('contract export respects filters', function () {
    $docType = DocumentType::firstOrCreate(
        ['code' => 'perjanjian'],
        ['name' => 'Perjanjian', 'is_active' => true]
    );

    $status = ContractStatus::firstOrCreate(
        ['code' => 'active'],
        ['name' => 'Active', 'color' => 'green', 'is_active' => true, 'sort_order' => 1]
    );

    $ticket = Ticket::factory()->create([
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'counterpart_name' => 'Filtered Company',
    ]);

    Contract::create([
        'ticket_id' => $ticket->id,
        'contract_number' => 'CTR-TST-26010002',
        'agreement_name' => 'Filtered Agreement',
        'document_type_id' => $docType->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'pic_id' => $this->user->id,
        'start_date' => now(),
        'end_date' => now()->addYear(),
        'status_id' => $status->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('contracts.export', [
            'type' => 'perjanjian',
            'division' => $this->division->id,
        ]));

    $response->assertSuccessful();
});
