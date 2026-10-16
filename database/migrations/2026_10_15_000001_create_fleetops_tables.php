<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('region', 120);
            $table->string('type', 20);
            $table->timestampsTz();
        });
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number', 30)->unique();
            $table->string('type', 20);
            $table->string('ownership', 20);
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
        });
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->timestampsTz();
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->string('requester_name', 120);
            $table->string('destination');
            $table->text('purpose');
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['vehicle_id', 'start_at', 'end_at']);
            $table->index(['driver_id', 'start_at', 'end_at']);
            $table->index('status');
            $table->index('created_at');
            $table->index('completed_at');
        });
        Schema::create('booking_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('approver_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->unique(['booking_id', 'level']);
            $table->unique(['booking_id', 'approver_id']);
            $table->index(['approver_id', 'status']);
        });
        Schema::create('booking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 40);
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_type_check CHECK (type IN ('head_office', 'branch', 'mine'))");
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_type_check CHECK (type IN ('passenger', 'goods'))");
        DB::statement("ALTER TABLE vehicles ADD CONSTRAINT vehicles_ownership_check CHECK (ownership IN ('company', 'rental'))");
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'completed'))");
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_schedule_check CHECK (end_at > start_at)');
        DB::statement("ALTER TABLE bookings ADD CONSTRAINT bookings_completion_check CHECK ((status = 'completed' AND completed_at IS NOT NULL) OR (status <> 'completed' AND completed_at IS NULL))");
        DB::statement('ALTER TABLE booking_approvals ADD CONSTRAINT approvals_level_check CHECK (level IN (1, 2))');
        DB::statement("ALTER TABLE booking_approvals ADD CONSTRAINT approvals_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        DB::statement("ALTER TABLE booking_approvals ADD CONSTRAINT approvals_decision_check CHECK ((status = 'pending' AND decided_at IS NULL) OR (status IN ('approved', 'rejected') AND decided_at IS NOT NULL))");
        DB::statement("ALTER TABLE booking_logs ADD CONSTRAINT booking_logs_action_check CHECK (action IN ('BOOKING_CREATED', 'BOOKING_UPDATED', 'APPROVAL_LEVEL_1_APPROVED', 'APPROVAL_LEVEL_2_APPROVED', 'BOOKING_REJECTED', 'BOOKING_COMPLETED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_logs');
        Schema::dropIfExists('booking_approvals');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('locations');
    }
};
