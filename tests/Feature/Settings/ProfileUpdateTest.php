<?php

namespace Tests\Feature\Settings;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('profile.role', $user->programmeRole()->label())
            ->where('profile.hasPhoto', false)
            ->missing('profile.teams'));
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull(User::query()->find($user->id));
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_photo_is_reencoded_stored_privately_served_with_integrity_headers_and_audited(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), [
                'photo' => UploadedFile::fake()->image('portrait.jpg', 900, 600),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('local', $user->profile_photo_disk);
        $this->assertSame('image/webp', $user->profile_photo_mime_type);
        $this->assertNotNull($user->profile_photo_path);
        $this->assertNotNull($user->profile_photo_updated_at);
        Storage::disk('local')->assertExists($user->profile_photo_path);

        $content = Storage::disk('local')->get($user->profile_photo_path);
        $this->assertSame(hash('sha256', $content), $user->profile_photo_checksum);
        $this->assertSame([512, 512], array_slice(getimagesizefromstring($content), 0, 2));

        $response = $this->actingAs($user)->get(route('profile.photo'));
        $response->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($content, $response->getContent());
        $this->assertDatabaseHas((new AuditEvent)->getTable(), ['actor_id' => $user->id, 'subject_id' => $user->id, 'action' => 'profile.photo.updated']);
    }

    public function test_profile_photo_replacement_and_removal_delete_superseded_private_objects(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('first.png', 512, 512)])->assertRedirect();
        $firstPath = $user->refresh()->profile_photo_path;

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('second.jpg', 700, 700)])->assertRedirect();
        $secondPath = $user->refresh()->profile_photo_path;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->actingAs($user)->delete(route('profile.photo.destroy'))->assertRedirect(route('profile.edit'));
        Storage::disk('local')->assertMissing($secondPath);
        $this->assertNull($user->refresh()->profile_photo_path);
        $this->assertDatabaseHas((new AuditEvent)->getTable(), ['actor_id' => $user->id, 'action' => 'profile.photo.removed']);
    }

    public function test_profile_photo_validation_and_integrity_fail_closed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('small.jpg', 100, 100)])
            ->assertSessionHasErrors('photo');

        $this->actingAs($user)->post(route('profile.photo.store'), ['photo' => UploadedFile::fake()->image('valid.jpg', 512, 512)])->assertRedirect();
        $user->refresh();
        Storage::disk('local')->put($user->profile_photo_path, 'tampered');

        $this->actingAs($user)->get(route('profile.photo'))->assertStatus(409);
    }
}
