<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserDocumentDisplayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_nid_rejects_invalid_file_type(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => 'Client One',
            'status' => 'active',
            'nid_file' => UploadedFile::fake()->create('nid.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('nid_file');
    }

    #[Test]
    public function customer_cv_rejects_non_pdf(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->post(route('admin.customers.store'), [
            'name' => 'Client Two',
            'status' => 'active',
            'cv_file' => UploadedFile::fake()->image('cv.png'),
        ]);

        $response->assertSessionHasErrors('cv_file');
    }

    #[Test]
    public function admin_can_view_customer_document(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Doc Client',
            'status' => 'active',
        ]);

        $path = 'nid/customers/' . $customer->id . '/nid.pdf';
        Storage::disk('public')->put($path, 'dummy');
        $customer->update(['nid_path' => $path]);

        $response = $this->actingAs($admin)->get(route('admin.user-documents.show', [
            'type' => 'customer',
            'id' => $customer->id,
            'doc' => 'nid',
        ]));

        $response->assertOk();
    }

    #[Test]
    public function non_admin_cannot_view_documents(): void
    {
        Storage::fake('public');

        $client = User::factory()->create(['role' => Role::CLIENT]);
        $customer = Customer::create([
            'name' => 'Client Doc',
            'status' => 'active',
        ]);

        $path = 'nid/customers/' . $customer->id . '/nid.pdf';
        Storage::disk('public')->put($path, 'dummy');
        $customer->update(['nid_path' => $path]);

        $response = $this->actingAs($client)->get(route('admin.user-documents.show', [
            'type' => 'customer',
            'id' => $customer->id,
            'doc' => 'nid',
        ]));

        $response->assertStatus(403);
    }

    #[Test]
    public function customer_list_shows_avatar_when_available(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $customer = Customer::create([
            'name' => 'Avatar Client',
            'status' => 'active',
        ]);

        $path = 'avatars/customers/' . $customer->id . '/avatar.png';
        Storage::disk('public')->put($path, 'dummy');
        $customer->update(['avatar_path' => $path]);

        $response = $this->actingAs($admin)->get(route('admin.customers.index'));

        $response->assertOk();

        $content = $response->getContent();
        if (preg_match('/data-page="([^"]+)"/', $content, $matches) === 1) {
            $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
            $avatarUrls = collect(data_get($payload, 'props.customers', []))
                ->pluck('avatar_url')
                ->filter()
                ->values()
                ->all();

            $expectedPath = Storage::disk('public')->url($path);
            $this->assertTrue(
                collect($avatarUrls)->contains(fn ($url) => is_string($url) && str_contains($url, $expectedPath)),
                'Expected at least one customer avatar URL to contain: '.$expectedPath
            );
            return;
        }

        $response->assertSee(Storage::disk('public')->url($path));
    }
}
