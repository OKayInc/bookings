<?php

namespace App\Domain\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class OrganizationPurgeService
{
    public const LEVELS = ['all', 'members', 'resources', 'types', 'configuration'];

    private function level(string $level): int
    {
        $rank = array_search($level, self::LEVELS, true);
        if ($rank === false) { throw new InvalidArgumentException('Unknown purge level.'); }
        return $rank;
    }

    public function preview(Organization $org, string $level): array
    {
        $rank = $this->level($level);
        $rows = [];
        foreach (['bookings', 'appointments', 'booking_holds', 'organization_contacts', 'coupons', 'payment_transactions', 'payment_refunds'] as $table) {
            $rows[$table] = DB::table($table)->where('organization_id', $org->getKey())->count();
        }
        $rows['appointment_types'] = $rank < 3 ? $org->appointmentTypes()->count() : 0;
        $rows['resource_links'] = $rank < 2 ? $org->resources()->count() : 0;
        $rows['memberships'] = $rank < 1 ? $org->memberships()->count() : 0;
        return $rows;
    }

    public function purge(Organization $org, string $level): void
    {
        $rank = $this->level($level);
        DB::transaction(function () use ($org, $rank): void {
            Organization::whereKey($org->getKey())->lockForUpdate()->firstOrFail();
            $id = $org->getKey();
            $types = DB::table('appointment_types')->where('organization_id', $id)->pluck('id');
            $bookings = DB::table('bookings')->where('organization_id', $id)->pluck('id');
            $submissions = DB::table('booking_contract_submissions')->where('organization_id', $id)->pluck('id');
            $this->queueFiles($id, DB::table('booking_answer_files')->whereIn('booking_id', $bookings)->get(['disk', 'path']));
            $this->queueFiles($id, DB::table('booking_contract_files')->whereIn('booking_contract_submission_id', $submissions)->get(['disk', 'path']));
            // Delete restrictive dependents before their parents. Never disable FK checks.
            foreach (['coupon_redemptions', 'payment_refunds', 'payment_transactions', 'payment_webhook_events',
                'booking_schedule_proposals', 'resource_confirmations', 'event_admission_approvals', 'reminder_deliveries'] as $table) {
                DB::table($table)->where('organization_id', $id)->delete();
            }
            DB::table('booking_reschedules')->whereIn('booking_id', $bookings)->delete();
            foreach (['bookings', 'appointments', 'booking_holds', 'organization_contacts', 'coupons',
                'appointment_type_invitations', 'organization_member_invitations', 'calendar_oauth_states'] as $table) {
                DB::table($table)->where('organization_id', $id)->delete();
            }
            // Bookings/appointments cascade their answers, files, tickets, deposits and sync history.
            if ($rank < 4) {
                $connections = DB::table('calendar_connections')->where('organization_id', $id)->pluck('id');
                $calendars = DB::table('external_calendars')->whereIn('calendar_connection_id', $connections)->pluck('id');
                $foreignTypes = DB::table('appointment_types')->where('organization_id', '!=', $id)->select('id');
                if (DB::table('appointment_type_calendars')->whereIn('external_calendar_id', $calendars)->whereIn('appointment_type_id', $foreignTypes)->exists()
                    || DB::table('appointment_external_events')->whereIn('external_calendar_id', $calendars)
                        ->whereIn('appointment_id', DB::table('appointments')->where('organization_id', '!=', $id)->select('id'))->exists()) {
                    throw new RuntimeException('A calendar is used by another organization. Remove that cross-organization dependency before purging configuration.');
                }
                $this->queueFiles($id, DB::table('appointment_contract_templates')->where('organization_id', $id)->get(['disk', 'path']));
                $this->queueFiles($id, DB::table('gallery_photos')->where('organization_id', $id)->get(['disk', 'path']));
                foreach (['appointment_questions', 'short_notice_fee_rules'] as $table) {
                    DB::table($table)->whereIn('appointment_type_id', $types)->delete();
                }
                foreach (['appointment_contract_templates', 'gallery_photos', 'reusable_questions', 'availability_schedules',
                    'organization_holidays', 'organization_taxes', 'organization_payment_settings', 'organization_conference_settings',
                    'payment_rules', 'coupon_offers', 'attendee_email_templates', 'calendar_connections'] as $table) {
                    DB::table($table)->where('organization_id', $id)->delete();
                }
                if ($org->logo_path) {
                    $this->queueFiles($id, [(object) ['disk' => config('organizations.logo_disk', 'public'), 'path' => $org->logo_path]]);
                }
                DB::table('organizations')->where('id', $id)->update(['logo_path' => null, 'collects_taxes' => false, 'tax_identifier' => null]);
                foreach (DB::table('appointment_types')->where('organization_id', $id)->whereNotNull('logo_path')->get(['logo_path']) as $type) {
                    $this->queueFiles($id, [(object) ['disk' => config('appointment-types.logo_disk', 'public'), 'path' => $type->logo_path]]);
                }
                DB::table('appointment_types')->where('organization_id', $id)->update(['logo_path' => null]);
            } else {
                // Keep credentials and calendar selections; clear historical error/sync state.
                DB::table('calendar_connections')->where('organization_id', $id)->update(['last_error' => null]);
            }
            if ($rank < 3) { DB::table('appointment_types')->where('organization_id', $id)->delete(); }
            if ($rank < 2) {
                // Physical resources may be shared. Keep every resource referenced outside this tenant.
                $owned = DB::table('resources')->where('organization_id', $id)->pluck('id');
                foreach ($owned as $resource) {
                    $shared = DB::table('organization_resources')->where('resource_id', $resource)->where('organization_id', '!=', $id)->exists();
                    foreach (['appointment_type_resources' => ['appointment_types', 'appointment_type_id'],
                        'appointment_resources' => ['appointments', 'appointment_id'],
                        'booking_hold_resources' => ['booking_holds', 'booking_hold_id']] as $pivot => [$parent, $fk]) {
                       $shared = $shared || DB::table($pivot)->where('resource_id', $resource)
                            ->whereIn($fk, DB::table($parent)->where('organization_id', '!=', $id)->select('id'))->exists();
                    }
                    $shared = $shared || DB::table('appointment_question_resource_rule_resources')->where('resource_id', $resource)
                        ->whereIn('resource_rule_id', DB::table('appointment_question_resource_rules')->whereIn('appointment_question_id',
                            DB::table('appointment_questions')->whereIn('appointment_type_id',
                                DB::table('appointment_types')->where('organization_id', '!=', $id)->select('id'))->select('id'))->select('id'))->exists();
                    $shared = $shared || DB::table('calendar_connections')->where('resource_id', $resource)->where('organization_id', '!=', $id)->exists();
                    if (! $shared) { DB::table('resources')->where('id', $resource)->delete(); }
                }
                DB::table('organization_resources')->where('organization_id', $id)->delete();
            }
            if ($rank < 1) { DB::table('organization_memberships')->where('organization_id', $id)->delete(); }
        });
    }

    private function queueFiles(string $id, iterable $files): void
    {
        foreach ($files as $file) {
            DB::table('purge_file_deletions')->insert(['id' => \App\Support\Uuid\UuidBinary::toBytes((string) \Illuminate\Support\Str::uuid7()), 'organization_id' => $id, 'disk' => $file->disk,
                'path' => $file->path, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Filesystem cleanup runs after commit and remains retryable after a failure. */
    public function cleanupFiles(Organization $org): int
    {
        $failed = 0;
        foreach (DB::table('purge_file_deletions')->where('organization_id', $org->getKey())->orderBy('id')->cursor() as $file) {
            try {
                $referenced = false;
                foreach (['booking_answer_files', 'booking_contract_files', 'appointment_contract_templates', 'gallery_photos'] as $table) {
                    $referenced = $referenced || DB::table($table)->where('disk', $file->disk)->where('path', $file->path)->exists();
                }
                foreach (['organizations' => 'organizations', 'appointment_types' => 'appointment-types'] as $table => $config) {
                    if ($file->disk === config($config.'.logo_disk', 'public')) {
                        $referenced = $referenced || DB::table($table)->where('logo_path', $file->path)->exists();
                    }
                }
                if (! $referenced) {
                    $disk = Storage::disk($file->disk);
                    if ($disk->exists($file->path) && ! $disk->delete($file->path)) {
                        throw new RuntimeException('File deletion failed.');
                    }
                }
                DB::table('purge_file_deletions')->where('id', $file->id)->delete();
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }
        return $failed;
    }
}
