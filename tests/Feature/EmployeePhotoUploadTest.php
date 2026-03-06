<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_upload_employee_photo_and_access_it(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        $employee = Employee::create([
            'name' => 'Employee One',
            'email' => 'employee@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.hr.employees.update', $employee), [
            'name' => 'Employee One',
            'email' => 'employee@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
            'salary_type' => 'monthly',
            'currency' => 'BDT',
            'basic_pay' => 1000,
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertRedirect(route('admin.hr.employees.index'));

        $employee->refresh();
        $this->assertNotEmpty($employee->photo_path);
        $this->assertStringStartsWith('avatars/employees/'.$employee->id.'/', $employee->photo_path);
        Storage::disk('public')->assertExists($employee->photo_path);
    }

    #[Test]
    public function employee_index_hides_missing_photo_urls_instead_of_rendering_broken_images(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'master_admin',
        ]);

        Employee::create([
            'name' => 'Missing Photo Employee',
            'email' => 'missing-photo@example.com',
            'employment_type' => 'full_time',
            'work_mode' => 'remote',
            'join_date' => now()->toDateString(),
            'status' => 'active',
            'photo_path' => 'employees/photos/missing-photo.png',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.employees.index'))
            ->assertOk()
            ->assertDontSee('/storage/employees/photos/missing-photo.png', false);
    }
}
