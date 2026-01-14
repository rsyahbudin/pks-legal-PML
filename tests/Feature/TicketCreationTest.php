<?php

declare(strict_types=1);

use App\Models\{User, Ticket, Division, Department, Role, Permission};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    
    // Create divisions and departments
    $this->division = Division::factory()->create();
    $this->department = Department::factory()->create(['division_id' => $this->division->id]);
});

test('superadmin can create ticket', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->role->update(['slug' => 'super-admin']);
    
    $this->actingAs($superAdmin);
    
    $response = $this->post(route('tickets.create'), [
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'has_financial_impact' => true,
        'proposed_document_title' => 'Test Document',
        'document_type' => 'perjanjian',
        'counterpart_name' => 'Test Company',
        'agreement_start_date' => now()->addDays(10)->format('Y-m-d'),
        'agreement_duration' => '2 years',
        'is_auto_renewal' => false,
        'agreement_end_date' => now()->addYears(2)->format('Y-m-d'),
        'tat_legal_compliance' => true,
        'mandatory_documents' => [UploadedFile::fake()->create('doc.pdf')],
        'approval_document' => UploadedFile::fake()->image('approval.jpg'),
    ]);
    
    expect(Ticket::count())->toBe(1);
});

test('user with tickets.create permission can create ticket', function () {
    // Create user role with tickets.create permission
    $role = Role::factory()->create(['slug' => 'user']);
    $permission = Permission::where('slug', 'tickets.create')->first();
    
    if (!$permission) {
        $permission = Permission::create([
            'name' => 'Buat Ticket',
            'slug' => 'tickets.create',
            'group' => 'tickets',
            'description' => 'Membuat ticket baru',
        ]);
    }
    
    $role->permissions()->sync([$permission->id]);
    
    $user = User::factory()->create([
        'role_id' => $role->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);
    
    $this->actingAs($user);
    
    $response = $this->post(route('tickets.create'), [
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'has_financial_impact' => false,
        'proposed_document_title' => 'User Created Document',
        'document_type' => 'surat_kuasa',
        'kuasa_pemberi' => 'John Doe',
        'kuasa_penerima' => 'Jane Smith',
        'kuasa_start_date' => now()->addDays(5)->format('Y-m-d'),
        'kuasa_end_date' => now()->addMonths(6)->format('Y-m-d'),
        'tat_legal_compliance' => true,
        'mandatory_documents' => [UploadedFile::fake()->create('doc.pdf')],
        'approval_document' => UploadedFile::fake()->image('approval.jpg'),
    ]);
    
    expect(Ticket::count())->toBe(1);
    expect(Ticket::first()->created_by)->toBe($user->id);
});

test('legal role user can create ticket', function () {
    // Create legal role with tickets.create permission
    $role = Role::where('slug', 'legal')->first();
    if (!$role) {
        $role = Role::factory()->create(['slug' => 'legal']);
    }
    
    $permission = Permission::where('slug', 'tickets.create')->first();
    if (!$permission) {
        $permission = Permission::create([
            'name' => 'Buat Ticket',
            'slug' => 'tickets.create',
            'group' => 'tickets',
            'description' => 'Membuat ticket baru',
        ]);
    }
    
    $role->permissions()->sync([$permission->id]);
    
    $legal = User::factory()->create([
        'role_id' => $role->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);
    
    $this->actingAs($legal);
    
    $response = $this->post(route('tickets.create'), [
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
        'has_financial_impact' => true,
        'proposed_document_title' => 'Legal Team Document',
        'document_type' => 'nda',
        'counterpart_name' => 'Partner Company',
        'agreement_start_date' => now()->format('Y-m-d'),
        'agreement_duration' => '1 year',
        'is_auto_renewal' => true,
        'renewal_period' => '1 year',
        'renewal_notification_days' => 30,
        'tat_legal_compliance' => false,
        'mandatory_documents' => [UploadedFile::fake()->create('doc.pdf')],
        'approval_document' => UploadedFile::fake()->image('approval.jpg'),
    ]);
    
    expect(Ticket::count())->toBe(1);
});

test('user without tickets.create permission cannot create ticket', function () {
    // Create user without ticket creation permission
    $role = Role::factory()->create(['slug' => 'no-permission']);
    $user = User::factory()->create([
        'role_id' => $role->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);
    
    $this->actingAs($user);
    
    $response = $this->get(route('tickets.create'));
    
    // Should be forbidden (403)
    $response->assertForbidden();
});

test('permission check uses tickets.create not contracts.create', function () {
    // This test verifies that the code checks for tickets.create permission
    $role = Role::factory()->create(['slug' => 'test-user']);
    
    // Give ONLY contracts.create (wrong permission)
    $wrongPermission = Permission::firstOrCreate(
        ['slug' => 'contracts.create'],
        [
            'name' => 'Wrong Permission',
            'group' => 'contracts',
            'description' => 'This should not grant ticket creation',
        ]
    );
    
    $role->permissions()->sync([$wrongPermission->id]);
    
    $user = User::factory()->create([
        'role_id' => $role->id,
        'division_id' => $this->division->id,
        'department_id' => $this->department->id,
    ]);
    
    $this->actingAs($user);
    
    // User should NOT be able to access create page because they don't have tickets.create
    $response = $this->get(route('tickets.create'));
    $response->assertForbidden();
    
    // Now give the CORRECT permission
    $correctPermission = Permission::where('slug', 'tickets.create')->first();
    if (!$correctPermission) {
        $correctPermission = Permission::create([
            'name' => 'Buat Ticket',
            'slug' => 'tickets.create',
            'group' => 'tickets',
            'description' => 'Membuat ticket baru',
        ]);
    }
    
    $role->permissions()->sync([$correctPermission->id]);
    $user->refresh();
    
    // Now it should work
    $response = $this->get(route('tickets.create'));
    $response->assertSuccessful();
});
