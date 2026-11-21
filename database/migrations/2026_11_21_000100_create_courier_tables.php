<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedTinyInteger('route_order')->unique();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('licence_number')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('capacity_kg')->nullable();
            $table->string('status')->default('available');
            $table->foreignId('default_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('origin_branch_id')->constrained('branches');
            $table->foreignId('destination_branch_id')->constrained('branches');
            $table->foreignId('sender_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('receiver_id')->constrained('customers');
            $table->foreignId('created_by')->constrained('users');
            $table->string('status')->default('received')->index();
            $table->unsignedBigInteger('total_amount');
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number')->unique();
            $table->foreignId('vehicle_id')->constrained();
            $table->foreignId('driver_id')->constrained();
            $table->foreignId('origin_branch_id')->constrained('branches');
            $table->foreignId('current_branch_id')->constrained('branches');
            $table->string('status')->default('loading')->index();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unloaded_at')->nullable();
            $table->unique(['trip_id', 'consignment_id']);
        });

        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('method');
            $table->string('status')->default('completed');
            $table->string('reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('trip_consignments');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['branch_id']));
        Schema::dropIfExists('branches');
    }
};
