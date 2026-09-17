<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('customer')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->unsignedInteger('no_show_count')->default(0)->after('phone');
            $table->timestamp('blocked_until')->nullable()->after('no_show_count');
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('area');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('phone');
            $table->text('pickup_instructions')->nullable();
            $table->string('image')->nullable();
            $table->string('cr_number');
            $table->string('food_licence_no');
            $table->string('status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('delivers')->default(false);
            $table->unsignedBigInteger('delivery_fee_fils')->nullable();
            $table->timestamps();

            $table->index(['status', 'lat', 'lng']);
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('item');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('retail_value_fils');
            $table->unsignedBigInteger('price_fils');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('remaining');
            $table->unsignedInteger('max_per_customer')->default(2);
            $table->date('expires_on');
            $table->dateTime('pickup_start');
            $table->dateTime('pickup_end');
            $table->string('image')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'pickup_end']);
        });

        // The 50% rule, enforced by the database as well as by validation.
        // Laravel has no fluent check-constraint builder, so this is raw SQL.
        // Two triggers (insert + update) so an edit can never push price past
        // the floor once the edit UI ships.
        DB::statement(
            'CREATE TRIGGER offers_half_off_insert BEFORE INSERT ON offers
             FOR EACH ROW BEGIN
                SELECT RAISE(ABORT, "price must be at most half of retail value")
                WHERE NEW.price_fils * 2 > NEW.retail_value_fils;
             END'
        );

        DB::statement(
            'CREATE TRIGGER offers_half_off_update BEFORE UPDATE ON offers
             FOR EACH ROW BEGIN
                SELECT RAISE(ABORT, "price must be at most half of retail value")
                WHERE NEW.price_fils * 2 > NEW.retail_value_fils;
             END'
        );

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->string('pickup_code', 6);
            $table->string('status')->default('reserved');
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'pickup_code']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->json('keys');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS offers_half_off_insert');
        DB::statement('DROP TRIGGER IF EXISTS offers_half_off_update');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('stores');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'no_show_count', 'blocked_until']);
        });
    }
};
