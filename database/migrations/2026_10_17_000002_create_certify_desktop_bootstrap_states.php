<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('certify_desktop_bootstrap_states', function (Blueprint $table): void {
            $table->string('key', 100)->primary();
            $table->string('result', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certify_desktop_bootstrap_states');
    }
};
