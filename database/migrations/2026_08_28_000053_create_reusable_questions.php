<?php

use App\Support\Uuid\UuidBinary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reusable_questions', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('organization_id', 16, true);
            $table->string('type', 32);
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->boolean('default_is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('configuration')->nullable();
            $table->string('pricing_adjustment_type', 24)->default('none');
            $table->string('pricing_application_mode', 24)->default('once');
            $table->unsignedBigInteger('pricing_amount_minor')->nullable();
            $table->unsignedInteger('pricing_percentage_bps')->nullable();
            $table->string('pricing_percentage_basis', 24)->default('base_price');
            $table->unsignedInteger('pricing_included_units')->default(0);
            $table->timestamps(6);

            $table->index(['organization_id', 'is_active', 'label'], 'rq_org_active_label_idx');
            $table->foreign('organization_id', 'rq_org_fk')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('reusable_question_options', function (Blueprint $table): void {
            $table->binary('id', 16, true)->primary();
            $table->binary('reusable_question_id', 16, true);
            $table->string('label', 255);
            $table->string('value', 180);
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('pricing_adjustment_type', 24)->default('none');
            $table->unsignedBigInteger('pricing_amount_minor')->nullable();
            $table->unsignedInteger('pricing_percentage_bps')->nullable();
            $table->string('pricing_percentage_basis', 24)->default('base_price');
            $table->timestamps(6);

            $table->unique(['reusable_question_id', 'value'], 'rqo_question_value_uq');
            $table->index(['reusable_question_id', 'is_active', 'position'], 'rqo_question_active_pos_idx');
            $table->foreign('reusable_question_id', 'rqo_question_fk')->references('id')->on('reusable_questions')->cascadeOnDelete();
        });

        Schema::table('appointment_questions', function (Blueprint $table): void {
            $table->binary('reusable_question_id', 16, true)->nullable()->after('appointment_type_id');
        });

        $questions = DB::table('appointment_questions as questions')
            ->join('appointment_types as types', 'types.id', '=', 'questions.appointment_type_id')
            ->select([
                'questions.id',
                'types.organization_id',
                'questions.type',
                'questions.label',
                'questions.description',
                'questions.placeholder',
                'questions.is_required',
                'questions.configuration',
                'questions.pricing_adjustment_type',
                'questions.pricing_application_mode',
                'questions.pricing_amount_minor',
                'questions.pricing_percentage_bps',
                'questions.pricing_percentage_basis',
                'questions.pricing_included_units',
                'questions.created_at',
                'questions.updated_at',
            ])
            ->orderBy('questions.created_at')
            ->get();

        foreach ($questions as $question) {
            $reusableQuestionId = UuidBinary::toBytes((string) Str::uuid7());
            $createdAt = $question->created_at ?? now();
            $updatedAt = $question->updated_at ?? $createdAt;

            DB::table('reusable_questions')->insert([
                'id' => $reusableQuestionId,
                'organization_id' => $question->organization_id,
                'type' => $question->type,
                'label' => $question->label,
                'description' => $question->description,
                'placeholder' => $question->placeholder,
                'default_is_required' => $question->is_required,
                'is_active' => true,
                'configuration' => $question->configuration,
                'pricing_adjustment_type' => $question->pricing_adjustment_type,
                'pricing_application_mode' => $question->pricing_application_mode,
                'pricing_amount_minor' => $question->pricing_amount_minor,
                'pricing_percentage_bps' => $question->pricing_percentage_bps,
                'pricing_percentage_basis' => $question->pricing_percentage_basis,
                'pricing_included_units' => $question->pricing_included_units,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            $options = DB::table('question_options')
                ->where('appointment_question_id', $question->id)
                ->orderBy('position')
                ->get();

            foreach ($options as $option) {
                DB::table('reusable_question_options')->insert([
                    'id' => UuidBinary::toBytes((string) Str::uuid7()),
                    'reusable_question_id' => $reusableQuestionId,
                    'label' => $option->label,
                    'value' => $option->value,
                    'position' => $option->position,
                    'is_active' => $option->is_active,
                    'pricing_adjustment_type' => $option->pricing_adjustment_type,
                    'pricing_amount_minor' => $option->pricing_amount_minor,
                    'pricing_percentage_bps' => $option->pricing_percentage_bps,
                    'pricing_percentage_basis' => $option->pricing_percentage_basis,
                    'created_at' => $option->created_at ?? $createdAt,
                    'updated_at' => $option->updated_at ?? $updatedAt,
                ]);
            }

            DB::table('appointment_questions')
                ->where('id', $question->id)
                ->update(['reusable_question_id' => $reusableQuestionId]);
        }

        Schema::table('appointment_questions', function (Blueprint $table): void {
            $table->unique(['appointment_type_id', 'reusable_question_id'], 'aq_type_reusable_uq');
            $table->foreign('reusable_question_id', 'aq_reusable_fk')->references('id')->on('reusable_questions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_questions', function (Blueprint $table): void {
            $table->dropForeign('aq_reusable_fk');
            $table->dropUnique('aq_type_reusable_uq');
            $table->dropColumn('reusable_question_id');
        });

        Schema::dropIfExists('reusable_question_options');
        Schema::dropIfExists('reusable_questions');
    }
};
