<?php

namespace Tests\Feature;

use App\Domain\Galleries\GalleryImageConverter;
use App\Domain\Galleries\GalleryLimitService;
use App\Domain\Galleries\GalleryPhotoService;
use App\Domain\Plans\PlanStorageService;
use App\Domain\Appointments\AppointmentTypeDeletionService;
use App\Domain\Organizations\OrganizationDeletionService;
use App\Domain\Organizations\OrganizationPurgeService;
use App\Enums\GalleryPlacement;
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
use Illuminate\Support\Facades\Event;
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

    public function test_existing_photos_can_be_reordered_and_moved_between_top_and_bottom(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->ownerAndOrganization();
        $first = $this->galleryRecord($organization, null, 'above', 'first.webp', str_repeat('1', 64));
        $second = $this->galleryRecord($organization, null, 'above', 'second.webp', str_repeat('2', 64));
        $second->update(['position' => 2]);
        $bottom = $this->galleryRecord($organization, null, 'below', 'bottom.webp', str_repeat('3', 64));
        $type = $this->appointmentType($organization, 'Separate gallery');
        $separate = $this->galleryRecord($organization, $type, 'above', 'separate.webp', str_repeat('4', 64));

        $this->actingAs($user)->patch(route('gallery-photos.update', $second), [
            'placement' => 'above', 'position' => 2, 'move' => 'first',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
        $this->actingAs($user)->patch(route('gallery-photos.update', $second), [
            'placement' => 'below', 'position' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('below', $second->fresh()->placement->value);
        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $bottom->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
        $this->assertSame(1, $separate->fresh()->position);
        Storage::disk('public')->assertExists($second->path);
    }

    public function test_photo_reordering_rejects_another_tenant_and_invalid_positions(): void
    {
        Storage::fake('public');
        [$user] = $this->ownerAndOrganization();
        [$owner, $organization] = $this->ownerAndOrganization();
        foreach ([null, $this->appointmentType($organization, 'Private gallery')] as $type) {
            $photo = $this->galleryRecord($organization, $type, 'above', Str::random(10).'.webp', hash('sha256', Str::random(20)));
            $this->actingAs($user)->patch(route('gallery-photos.update', $photo), [
                'placement' => 'below', 'position' => 1,
            ])->assertForbidden();
            $this->actingAs($owner)->patch(route('gallery-photos.update', $photo), [
                'placement' => 'invalid', 'position' => 0,
            ])->assertSessionHasErrors(['placement', 'position']);
            $this->assertSame('above', $photo->fresh()->placement->value);
        }
    }

    public function test_sequential_gallery_upload_returns_json_confirmation(): void
    {
        $this->requireWebpEncoder();
        Storage::fake('public');
        [$user, $organization] = $this->ownerAndOrganization();
        $this->actingAs($user)->postJson(route('organizations.gallery-photos.store', $organization), [
            'placement' => 'above', 'photos' => [$this->image('one.png', 50, 80, 90)],
        ])->assertOk()->assertJsonPath('count', 1);
    }

    public function test_oversized_upload_has_a_friendly_html_and_json_response_even_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        \Illuminate\Support\Facades\Route::post('/test-oversized-upload', function () {
            throw new \Illuminate\Http\Exceptions\PostTooLargeException;
        });
        $this->post('/test-oversized-upload')->assertStatus(413)
            ->assertSee('Upload too large')->assertSee('Go back')->assertDontSee('Stack trace');
        $this->postJson('/test-oversized-upload')->assertStatus(413)
            ->assertJsonStructure(['message'])->assertJsonMissingPath('trace');
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

    public function test_large_upload_uses_a_small_preview_in_grids_and_the_full_image_in_the_lightbox(): void
    {
        Storage::fake('public');
        [$user, $organization] = $this->ownerAndOrganization();
        $this->actingAs($user)->post(route('organizations.gallery-photos.store', $organization), [
            'placement' => 'above', 'photos' => [$this->largeImage()],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $photo = GalleryPhoto::query()->sole();
        $disk = Storage::disk('public');
        $this->assertSame([1600, 1000], [$photo->width, $photo->height]);
        $this->assertSame([640, 400], [$photo->thumbnail_width, $photo->thumbnail_height]);
        $this->assertNotSame($photo->path, $photo->thumbnail_path);
        $this->assertLessThan($photo->file_size, $photo->thumbnail_file_size);
        $this->assertSame($photo->thumbnail_file_size, $disk->size($photo->thumbnail_path));
        $this->assertSame('image/webp', app(GalleryImageConverter::class)->details($disk->get($photo->thumbnail_path))['mime_type']);
        // Derived previews do not change customers' billable source-file usage.
        $this->assertSame($photo->file_size, app(PlanStorageService::class)->usageBytes($organization));
        $this->get(route('public.appointment-types.index', $organization->slug))->assertOk()
            ->assertSee('src="'.$photo->thumbnail_url.'"', false)
            ->assertSee('data-full-src="'.$photo->url.'"', false);
        $this->get(route('organizations.edit', $organization))->assertOk()
            ->assertSee('src="'.$photo->thumbnail_url.'"', false)->assertSee('href="'.$photo->url.'"', false);
        config(['filesystems.disks.public.url' => 'https://images.example.test']);
        $this->assertSame('https://images.example.test/'.$photo->thumbnail_path, $photo->thumbnail_url);
    }

    public function test_thumbnail_backfill_is_idempotent_repairs_missing_files_and_preserves_originals(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $contents = app(GalleryImageConverter::class)->toWebp($this->largeImage()->getContent())['contents'];
        $photo = $this->galleryRecord($organization, null, 'above', 'existing.webp', hash('sha256', $contents));
        Storage::disk('public')->put($photo->path, $contents);
        $this->assertSame($photo->url, $photo->thumbnail_url);
        $this->artisan('gallery:generate-thumbnails')->expectsOutput('Generated 1; already ready 0; failed 0.')->assertSuccessful();
        $photo->refresh();
        $firstPath = $photo->thumbnail_path;
        $this->assertSame([640, 400], [$photo->thumbnail_width, $photo->thumbnail_height]);
        $this->artisan('gallery:generate-thumbnails')->expectsOutput('Generated 0; already ready 1; failed 0.')->assertSuccessful();
        Storage::disk('public')->delete($firstPath);
        $this->artisan('gallery:generate-thumbnails')->assertSuccessful();
        $photo->refresh();
        $firstPath = $photo->thumbnail_path;
        Storage::disk('public')->assertExists($firstPath);
        config(['gallery.thumbnail_size' => 320]);
        $this->artisan('gallery:generate-thumbnails', ['--force' => true])->assertSuccessful();
        $photo->refresh();
        $this->assertSame([320, 200], [$photo->thumbnail_width, $photo->thumbnail_height]);
        Storage::disk('public')->assertMissing($firstPath);
        $this->assertSame($contents, Storage::disk('public')->get($photo->path));
        $paths = $photo->storedPaths();
        app(GalleryPhotoService::class)->delete($photo);
        Storage::disk('public')->assertMissing($paths);
    }

    public function test_small_webp_previews_reuse_the_original_file(): void
    {
        $this->requireWebpEncoder();
        Storage::fake('public');
        $organization = Organization::factory()->create();
        app(GalleryPhotoService::class)->uploadForOrganization($organization, [$this->image('tiny.png', 50, 100, 200)], GalleryPlacement::Above);
        $photo = GalleryPhoto::query()->sole();
        $this->assertSame($photo->path, $photo->thumbnail_path);
        $this->assertSame([8, 6], [$photo->thumbnail_width, $photo->thumbnail_height]);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_failed_gallery_insert_removes_both_new_files(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        Event::listen('eloquent.creating: '.GalleryPhoto::class, function (): void {
            throw new \RuntimeException('Simulated gallery insert failure');
        });
        try {
            app(GalleryPhotoService::class)->uploadForOrganization($organization, [$this->largeImage()], GalleryPlacement::Above);
            $this->fail('The simulated failure must reach the caller.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated gallery insert failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('gallery_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_normalizing_a_legacy_image_replaces_its_preview_and_keeps_full_dimensions(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $contents = $this->largeImage()->getContent();
        $photo = $this->galleryRecord($organization, null, 'above', 'legacy.png', hash('sha256', $contents));
        Storage::disk('public')->put($photo->path, $contents);
        Storage::disk('public')->put('galleries/old-preview.webp', 'old preview');
        $photo->update(['thumbnail_path' => 'galleries/old-preview.webp']);
        $this->assertTrue(app(GalleryPhotoService::class)->normalize($photo));
        $photo->refresh();
        $this->assertSame([1600, 1000], [$photo->width, $photo->height]);
        $this->assertSame([640, 400], [$photo->thumbnail_width, $photo->thumbnail_height]);
        Storage::disk('public')->assertMissing(['galleries/legacy.png', 'galleries/old-preview.webp']);
        Storage::disk('public')->assertExists($photo->storedPaths());
    }

    public function test_owner_deletion_and_configuration_purge_remove_thumbnail_files(): void
    {
        Storage::fake('public');
        foreach (['type', 'organization', 'purge'] as $operation) {
            $organization = Organization::factory()->create();
            $type = $operation === 'type' ? $this->appointmentType($organization, 'Delete gallery') : null;
            $photo = $this->galleryRecord($organization, $type, 'above', $operation.'.webp', hash('sha256', $operation));
            $previewPath = 'galleries/'.$operation.'-preview.webp';
            Storage::disk('public')->put($previewPath, 'preview');
            $photo->update(['thumbnail_path' => $previewPath]);
            $paths = $photo->storedPaths();
            if ($operation === 'type') {
                $this->assertTrue(app(AppointmentTypeDeletionService::class)->deleteIfUnused($type));
            } elseif ($operation === 'organization') {
                app(OrganizationDeletionService::class)->delete($organization);
            } else {
                $purge = app(OrganizationPurgeService::class);
                $purge->purge($organization, 'types');
                $this->assertSame(0, $purge->cleanupFiles($organization));
            }
            Storage::disk('public')->assertMissing($paths);
        }
    }

    public function test_preview_never_increases_bytes_for_flat_artwork(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        app(GalleryPhotoService::class)->uploadForOrganization($organization, [$this->largeImage(8)], GalleryPlacement::Above);
        $photo = GalleryPhoto::query()->sole();
        $this->assertLessThanOrEqual($photo->file_size, $photo->thumbnail_file_size);
        // This graphic's resized form is larger with GD: reuse its compact original.
        if ($photo->thumbnail_path === $photo->path) {
            $this->assertCount(1, Storage::disk('public')->allFiles());
            $this->assertSame($photo->width, $photo->thumbnail_width);
        }
    }

    private function largeImage(int $blockSize = 4): UploadedFile
    {
        $this->requireWebpEncoder();
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is needed to generate the detailed thumbnail fixture.');
        }
        $image = imagecreatetruecolor(1600, 1000);
        for ($x = 0; $x < 1600; $x += $blockSize) {
            for ($y = 0; $y < 1000; $y += $blockSize) {
                $color = imagecolorallocate($image, ($x * 13 + $y) % 256, ($y * 17 + $x) % 256, ($x + $y * 7) % 256);
                imagefilledrectangle($image, $x, $y, $x + $blockSize - 1, $y + $blockSize - 1, $color);
            }
        }
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('large.png', $contents);
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
