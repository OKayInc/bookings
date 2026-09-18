<?php

namespace Tests\Feature;

use App\Domain\Galleries\GalleryImageConverter;
use App\Domain\Galleries\GalleryLimitService;
use App\Enums\MembershipRole;
use App\Enums\MembershipStatus;
use App\Models\AppointmentType;
use App\Models\GalleryPhoto;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class M9R8GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_upload_is_converted_to_webp_and_free_limit_is_enforced(): void
    {
        $this->requireWebpEncoder();
        Storage::fake('public');
        config([
            'gallery.disk' => 'public',
            'gallery.limits.organization.free' => 1,
        ]);
        [$user, $organization] = $this->ownerAndOrganization();

        $this->actingAs($user)->post(route('organizations.gallery-photos.store', $organization), [
            'placement' => 'above',
            'photos' => [$this->image('studio.png', 180, 30, 30)],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $photo = GalleryPhoto::query()->sole();
        $this->assertNull($photo->appointment_type_id);
        $this->assertSame('above', $photo->placement->value);
        $this->assertStringEndsWith('.webp', $photo->path);
        Storage::disk('public')->assertExists($photo->path);
        $webp = Storage::disk('public')->get($photo->path);
        $this->assertSame('RIFF', substr($webp, 0, 4));
        $this->assertSame('WEBP', substr($webp, 8, 4));

        $this->actingAs($user)->post(route('organizations.gallery-photos.store', $organization), [
            'placement' => 'below',
            'photos' => [$this->image('second.png', 30, 180, 30)],
        ])->assertRedirect()->assertSessionHasErrors('photos');

        $this->assertSame(1, GalleryPhoto::query()->count());
    }

    public function test_paid_and_free_limits_are_selected_from_the_organization_tier(): void
    {
        config([
            'gallery.limits.organization.free' => 6,
            'gallery.limits.organization.paid' => 60,
            'gallery.limits.appointment_type.free' => 4,
            'gallery.limits.appointment_type.paid' => 30,
        ]);
        $free = Organization::factory()->create(['plan_tier' => 'free']);
        $paid = Organization::factory()->create(['plan_tier' => 'paid']);
        $paidType = $this->appointmentType($paid, 'Paid gallery');
        $limits = app(GalleryLimitService::class);

        $this->assertSame(6, $limits->forOrganization($free));
        $this->assertSame(60, $limits->forOrganization($paid));
        $this->assertSame(30, $limits->forAppointmentType($paidType));
    }

    public function test_appointment_gallery_is_tenant_authorized_and_appears_above_and_below_content(): void
    {
        $this->requireWebpEncoder();
        Storage::fake('public');
        config(['gallery.disk' => 'public']);
        [$user, $organization] = $this->ownerAndOrganization();
        $type = $this->appointmentType($organization, 'Portrait session');

        foreach ([['above', 'top.png', 200, 40, 40], ['below', 'bottom.png', 40, 40, 200]] as [$placement, $name, $red, $green, $blue]) {
            $this->actingAs($user)
                ->withSession(['active_organization_uuid' => $organization->uuid])
                ->post(route('appointment-types.gallery-photos.store', $type), [
                    'placement' => $placement,
                    'photos' => [$this->image($name, $red, $green, $blue)],
                ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->get(route('public.appointment-types.show', [
            'organizationSlug' => $organization->slug,
            'appointmentSlug' => $type->slug,
        ]))->assertOk()->assertSeeInOrder([
            'data-gallery-placement="above"',
            '<h1>Portrait session</h1>',
            'id="booking-scheduler"',
            'data-gallery-placement="below"',
        ], false)->assertSee('data-gallery-photo', false);

        [, $otherOrganization] = $this->ownerAndOrganization();
        $otherType = $this->appointmentType($otherOrganization, 'Other tenant');
        $this->actingAs($user)
            ->withSession(['active_organization_uuid' => $organization->uuid])
            ->post(route('appointment-types.gallery-photos.store', $otherType), [
                'placement' => 'above',
                'photos' => [$this->image('forged.png', 90, 90, 90)],
            ])->assertNotFound();
    }

    public function test_organization_gallery_wraps_the_public_appointment_grid(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $this->appointmentType($organization, 'Family photos');
        $this->galleryRecord($organization, null, 'above', 'top.webp', str_repeat('a', 64));
        $this->galleryRecord($organization, null, 'below', 'bottom.webp', str_repeat('b', 64));

        $this->get(route('public.appointment-types.index', $organization->slug))
            ->assertOk()
            ->assertSeeInOrder([
                'data-gallery-placement="above"',
                '<h2>Family photos</h2>',
                'data-gallery-placement="below"',
            ], false);
    }

    public function test_public_gallery_url_uses_the_configured_cdn_origin(): void
    {
        Storage::fake('public');
        config(['filesystems.disks.public.url' => 'https://images.appointment.to/storage']);
        $organization = Organization::factory()->create();
        $photo = $this->galleryRecord($organization, null, 'above', 'example.webp', str_repeat('c', 64));

        $this->assertSame('https://images.appointment.to/storage/galleries/example.webp', $photo->url);
    }

    public function test_authorized_delete_removes_the_gallery_row_and_file(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->ownerAndOrganization();
        $photo = $this->galleryRecord($organization, null, 'above', 'delete-me.webp', str_repeat('d', 64));

        $this->actingAs($user)
            ->delete(route('gallery-photos.destroy', $photo))
            ->assertRedirect();

        $this->assertDatabaseMissing('gallery_photos', ['id' => $photo->getKey()]);
        Storage::disk('public')->assertMissing('galleries/delete-me.webp');
    }

    public function test_weekly_command_converts_a_non_webp_file_and_updates_its_path(): void
    {
        $this->requireWebpEncoder();
        Storage::fake('public');
        config(['gallery.disk' => 'public']);
        $organization = Organization::factory()->create();
        $upload = $this->image('legacy.png', 10, 20, 30);
        $legacyPath = 'galleries/legacy.png';
        Storage::disk('public')->put($legacyPath, $upload->getContent());
        $details = app(GalleryImageConverter::class)->details($upload->getContent());
        $photo = GalleryPhoto::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => null,
            'placement' => 'above',
            'position' => 1,
            'disk' => 'public',
            'path' => $legacyPath,
            'original_name' => 'legacy.png',
            'alt_text' => null,
            'width' => $details['width'],
            'height' => $details['height'],
            'file_size' => strlen($upload->getContent()),
            'sha256' => hash('sha256', $upload->getContent()),
        ]);

        $this->assertSame(0, Artisan::call('gallery:normalize-images'));

        $photo->refresh();
        $this->assertStringEndsWith('.webp', $photo->path);
        $this->assertNotSame($legacyPath, $photo->path);
        Storage::disk('public')->assertMissing($legacyPath);
        Storage::disk('public')->assertExists($photo->path);
        $this->assertSame('image/webp', app(GalleryImageConverter::class)->details(Storage::disk('public')->get($photo->path))['mime_type']);
    }

    private function requireWebpEncoder(): void
    {
        if (! app(GalleryImageConverter::class)->canConvert()) {
            $this->fail('PHP GD or ImageMagick with WebP support is required.');
        }
    }

    /** @return array{User, Organization} */
    private function ownerAndOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        OrganizationMembership::create([
            'organization_id' => $organization->getKey(),
            'person_id' => $user->person_id,
            'role' => MembershipRole::Owner,
            'status' => MembershipStatus::Active,
        ]);

        return [$user, $organization];
    }

    private function appointmentType(Organization $organization, string $name): AppointmentType
    {
        return AppointmentType::create([
            'organization_id' => $organization->getKey(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'visibility' => 'public',
            'attendance_mode' => 'single',
            'capacity' => 1,
            'duration_mode' => 'fixed',
            'duration_unit' => 'hour',
            'duration_value' => 1,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'pricing_mode' => 'free',
            'email_verification_mode' => 'none',
            'is_active' => true,
        ]);
    }

    private function galleryRecord(
        Organization $organization,
        ?AppointmentType $appointmentType,
        string $placement,
        string $filename,
        string $sha256,
    ): GalleryPhoto {
        $path = 'galleries/'.$filename;
        Storage::disk('public')->put($path, 'webp');

        return GalleryPhoto::create([
            'organization_id' => $organization->getKey(),
            'appointment_type_id' => $appointmentType?->getKey(),
            'placement' => $placement,
            'position' => 1,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $filename,
            'alt_text' => null,
            'width' => 100,
            'height' => 100,
            'file_size' => 4,
            'sha256' => $sha256,
        ]);
    }

    private function image(string $name, int $red, int $green, int $blue): UploadedFile
    {
        if (function_exists('imagepng')) {
            $image = imagecreatetruecolor(8, 6);
            imagefilledrectangle($image, 0, 0, 7, 5, imagecolorallocate($image, $red, $green, $blue));
            ob_start();
            imagepng($image);
            $contents = ob_get_clean();
            imagedestroy($image);
        } else {
            $image = new \Imagick;
            $image->newImage(8, 6, new \ImagickPixel("rgb({$red},{$green},{$blue})"), 'png');
            $contents = $image->getImageBlob();
            $image->clear();
        }

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
