<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->jsonb('specialties')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        DB::statement('ALTER TABLE laboratories ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE laboratories FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON laboratories
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);

        Schema::create('prosthetic_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('case_number');
            $table->uuid('patient_id')->nullable();
            $table->string('patient_name');
            $table->date('patient_birth_date')->nullable();
            $table->string('patient_code')->nullable();
            $table->unsignedBigInteger('practitioner_id')->nullable();
            $table->string('practitioner_name')->nullable();
            $table->string('practitioner_specialty')->nullable();
            $table->unsignedBigInteger('laboratory_id')->nullable();
            $table->string('laboratory_name')->nullable();
            $table->string('work_type');
            $table->string('tooth_number')->nullable();
            $table->date('impression_date');
            $table->string('impression_type')->default('Numérique');
            $table->date('sent_to_lab_date')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->date('returned_from_lab_date')->nullable();
            $table->date('planned_placement_date')->nullable();
            $table->date('actual_placement_date')->nullable();
            $table->string('status')->default('impression_completed');
            $table->string('priority')->default('normal');
            $table->text('clinical_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('deposit_requested')->default(false);
            $table->boolean('deposit_received')->default(false);
            $table->decimal('deposit_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->boolean('final_payment_completed')->default(false);
            $table->text('administrative_comments')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->jsonb('status_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'case_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'patient_id']);
        });

        DB::statement('ALTER TABLE prosthetic_cases ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE prosthetic_cases FORCE ROW LEVEL SECURITY');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON prosthetic_cases
                USING (tenant_id = current_setting('app.tenant_id', true)::uuid)
                WITH CHECK (tenant_id = current_setting('app.tenant_id', true)::uuid)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('prosthetic_cases');
        Schema::dropIfExists('laboratories');
    }
};
