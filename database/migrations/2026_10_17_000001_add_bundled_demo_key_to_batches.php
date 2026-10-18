<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->string('bundled_demo_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table): void {
            $table->dropUnique(['bundled_demo_key']);
            $table->dropColumn('bundled_demo_key');
        });
    }
};
