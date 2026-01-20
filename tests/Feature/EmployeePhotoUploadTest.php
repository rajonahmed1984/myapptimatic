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
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->assertRedirect(route('admin.hr.employees.index'));

        $employee->refresh();
        $this->assertNotEmpty($employee->photo_path);
        Storage::disk('public')->assertExists($employee->photo_path);

        $this->actingAs($admin)
            ->get('/storage/' . $employee->photo_path)
            ->assertOk();
    }
}
