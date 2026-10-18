<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // SQLite foreign keys must be disabled BEFORE opening the schema rebuild transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        // This is a compatibility repair for databases that started on Certify v1.0/v1.1.
        // All certificate records, PDF paths, dates, and batch associations are preserved.
        Schema::dropIfExists('verification_logs');
        if (Schema::hasTable('template_elements')) {
            DB::table('template_elements')
                ->where('kind', 'qr')
                ->orWhere('field_key', 'qr')
                ->delete();
        }

        if (! Schema::hasTable('certificates')) {
            return;
        }

        $hasToken = Schema::hasColumn('certificates', 'verification_token');
        $hasRevoked = Schema::hasColumn('certificates', 'revoked_at');
        if (! $hasToken && ! $hasRevoked) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('certificates', function (Blueprint $table) use ($hasToken, $hasRevoked) {
                if ($hasToken) {
                    $table->dropUnique('certificates_verification_token_unique');
                    $table->dropColumn('verification_token');
                }
                if ($hasRevoked) {
                    $table->dropColumn('revoked_at');
                }
            });
            return;
        }

        // SQLite cannot safely drop a NOT NULL + UNIQUE legacy column in every
        // supported installation. Rebuild it within a transaction instead.
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function (): void {
                Schema::create('certificates_clean_v14', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
                    $table->string('recipient_name');
                    $table->string('certificate_number')->unique();
                    $table->string('pdf_path')->nullable();
                    $table->string('pdf_sha256', 64)->nullable();
                    $table->string('status')->default('pending');
                    $table->text('error')->nullable();
                    $table->timestamp('issued_at')->nullable();
                    $table->timestamps();
                });
                DB::statement(
                    'INSERT INTO certificates_clean_v14 '
                    .'(id, batch_id, recipient_name, certificate_number, pdf_path, pdf_sha256, status, error, issued_at, created_at, updated_at) '
                    .'SELECT id, batch_id, recipient_name, certificate_number, pdf_path, pdf_sha256, status, error, issued_at, created_at, updated_at FROM certificates'
                );
                Schema::drop('certificates');
                Schema::rename('certificates_clean_v14', 'certificates');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Do not silently continue if the original database contains broken links.
        $broken = DB::select('PRAGMA foreign_key_check');
        if (count($broken) > 0) {
            throw new \RuntimeException('Database contains invalid foreign keys. Restore your backup and inspect the records.');
        }
    }

    public function down(): void
    {
        // Legacy QR verification fields are intentionally not recreated.
    }
};
