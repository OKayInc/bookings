<?php

namespace Tests\Feature;

use App\Domain\Api\ApiKeyService;
use App\Domain\Organizations\OrganizationPurgeService;
use App\Models\Appointment;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingAnswerFile;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\OrganizationMembership;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class M10PurgeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        app(ApiKeyService::class)->regenerate($org);
        app(ApiKeyService::class)->regenerate($user);
        OrganizationMembership::create(['organization_id' => $org->getKey(), 'person_id' => $user->person_id, 'role' => 'owner', 'status' => 'active']);
        $resource = Resource::create(['organization_id' => $org->getKey(), 'name' => 'Camera', 'type' => 'equipment', 'is_active' => true]);
        $type = AppointmentType::create(['organization_id' => $org->getKey(), 'name' => 'Session', 'slug' => 'session',
            'visibility' => 'public', 'attendance_mode' => 'single', 'capacity' => 1, 'duration_mode' => 'fixed',
            'duration_unit' => 'minute', 'duration_value' => 60, 'start_interval_minutes' => 60,
            'pricing_mode' => 'free', 'email_verification_mode' => 'none', 'is_active' => true]);
        $question = AppointmentQuestion::create(['appointment_type_id' => $type->getKey(), 'type' => 'text', 'label' => 'Name']);
        $start = now('UTC')->addDays(10);
        $appointment = Appointment::create(['organization_id' => $org->getKey(), 'appointment_type_id' => $type->getKey(),
            'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(), 'blocked_starts_at_utc' => $start,
            'blocked_ends_at_utc' => $start->copy()->addHour(), 'scheduling_timezone' => 'America/Toronto', 'duration_value' => 60, 'capacity' => 1, 'status' => 'scheduled']);
        $contact = OrganizationContact::create(['organization_id' => $org->getKey(), 'first_name' => 'Guest', 'email' => 'guest@example.test']);
        $booking = Booking::create(['organization_id' => $org->getKey(), 'appointment_id' => $appointment->getKey(), 'appointment_type_id' => $type->getKey(),
            'organization_contact_id' => $contact->getKey(), 'reference' => strtoupper(Str::random(12)), 'status' => 'confirmed', 'attendee_count' => 1,
            'booking_timezone' => 'America/Toronto', 'base_price_minor' => 0, 'price_minor' => 0, 'currency' => 'CAD',
            'first_name' => 'Guest', 'last_name' => 'Test', 'email' => 'guest@example.test', 'email_normalized' => 'guest@example.test', 'manage_token_hash' => random_bytes(32)]);
        return [$org, $user, $resource, $type, $question, $booking];
    }

    public function test_all_five_levels_preserve_exact_roots_and_other_tenant_history(): void
    {
        Notification::fake();
        [$other, , , , , $otherBooking] = $this->fixture();
        foreach (OrganizationPurgeService::LEVELS as $rank => $level) {
            [$org, $user, $resource, $type, $question, $booking] = $this->fixture();
            $orgHash = $org->api_key_hash; $userHash = $user->api_key_hash;
            app(OrganizationPurgeService::class)->purge($org, $level);
            $this->assertSame(0, $org->bookings()->count());
            $this->assertSame(0, $org->appointments()->count());
            $this->assertSame($rank >= 1 ? 1 : 0, $org->memberships()->count(), $level);
            $this->assertSame($rank >= 2, Resource::whereKey($resource->getKey())->exists(), $level);
            $this->assertSame($rank >= 3, AppointmentType::whereKey($type->getKey())->exists(), $level);
            $this->assertSame($rank >= 4, AppointmentQuestion::whereKey($question->getKey())->exists(), $level);
            $this->assertSame($orgHash, $org->fresh()->api_key_hash);
            $this->assertSame($userHash, $user->fresh()->api_key_hash);
            $this->assertTrue(Booking::whereKey($otherBooking->getKey())->exists());
        }
        Notification::assertNothingSent();
    }

    public function test_shared_resources_are_not_deleted_and_other_tenant_links_survive(): void
    {
        [$org, , $resource] = $this->fixture();
        $other = Organization::factory()->create();
        $other->resources()->attach($resource->getKey(), ['is_required_by_default' => true]);
        app(OrganizationPurgeService::class)->purge($org, 'all');
        $this->assertTrue(Resource::whereKey($resource->getKey())->exists());
        $this->assertSame(1, $other->resources()->count());
        $this->assertSame(0, $org->resources()->count());
    }

    public function test_files_are_queued_transactionally_and_removed_after_commit(): void
    {
        Storage::fake('local');
        [$org, , , , $question, $booking] = $this->fixture();
        $answer = BookingAnswer::create(['booking_id' => $booking->getKey(), 'appointment_question_id' => $question->getKey(), 'question_uuid_snapshot' => $question->uuid, 'question_label' => 'Upload', 'question_type' => 'file']);
        Storage::disk('local')->put('uploads/test.txt', 'private');
        BookingAnswerFile::create(['booking_answer_id' => $answer->getKey(), 'booking_id' => $booking->getKey(), 'disk' => 'local', 'path' => 'uploads/test.txt',
            'original_name' => 'test.txt', 'mime_type' => 'text/plain', 'size_bytes' => 7, 'sha256' => hash('sha256', 'private', true), 'position' => 0]);
        $purge = app(OrganizationPurgeService::class);
        $purge->purge($org, 'configuration');
        Storage::disk('local')->assertExists('uploads/test.txt');
        $this->assertSame(1, DB::table('purge_file_deletions')->count());
        $this->assertSame(0, $purge->cleanupFiles($org));
        Storage::disk('local')->assertMissing('uploads/test.txt');
        $this->assertSame(0, DB::table('purge_file_deletions')->count());
    }

    public function test_file_cleanup_failure_is_retryable(): void
    {
        [$org] = $this->fixture();
        DB::table('purge_file_deletions')->insert(['id' => \App\Support\Uuid\UuidBinary::toBytes((string) Str::uuid7()), 'organization_id' => $org->getKey(), 'disk' => 'missing-disk', 'path' => 'file', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, app(OrganizationPurgeService::class)->cleanupFiles($org));
        $this->assertSame(1, DB::table('purge_file_deletions')->count());
    }

    public function test_dry_run_never_deletes_and_invalid_level_fails(): void
    {
        [$org] = $this->fixture();
        $this->artisan('organizations:purge', ['organization' => $org->uuid, '--level' => 'all', '--dry-run' => true])->assertExitCode(0);
        $this->assertSame(1, $org->bookings()->count());
        $this->artisan('organizations:purge', ['organization' => $org->uuid, '--level' => 'invalid', '--force' => true])->assertExitCode(1);
        $this->assertSame(1, $org->bookings()->count());
    }

    public function test_unknown_uuid_is_rejected(): void
    {
        $this->artisan('organizations:purge', ['organization' => 'bad', '--force' => true])->assertExitCode(1);
        $this->artisan('organizations:purge', ['organization' => (string) Str::uuid(), '--force' => true])->assertExitCode(1);
    }
}
