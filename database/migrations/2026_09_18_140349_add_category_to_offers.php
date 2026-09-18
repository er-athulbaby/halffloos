<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // 'pantry' is the safe default for rows that predate categories:
            // shelf-stable, no cold-chain claim attached to it.
            $table->string('category')->default('pantry')->after('type');
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['category', 'status']);
            $table->dropColumn('category');
        });
    }
};
