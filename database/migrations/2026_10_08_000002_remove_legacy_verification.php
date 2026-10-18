<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Schema rebuilding is necessary for old SQLite UNIQUE verification_token columns.
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::dropIfExists('verification_logs');
        if (Schema::hasTable('template_elements')) {
            DB::table('template_elements')->where('kind', 'qr')->orWhere('field_key', 'qr')->delete();
        }
        if (! Schema::hasTable('certificates') || ! Schema::hasColumn('certificates', 'verification_token')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();
            DB::beginTransaction();
            try {
                Schema::create('certificates_replacement', function (Blueprint $table) {
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
                DB::statement('INSERT INTO certificates_replacement (id, batch_id, recipient_name, certificate_number, pdf_path, pdf_sha256, status, error, issued_at, created_at, updated_at) SELECT id, batch_id, recipient_name, certificate_number, pdf_path, pdf_sha256, status, error, issued_at, created_at, updated_at FROM certificates');
                Schema::drop('certificates');
                Schema::rename('certificates_replacement', 'certificates');
                DB::commit();
            } catch (\Throwable $exception) {
                DB::rollBack();
                throw $exception;
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        } else {
            Schema::table('certificates', function (Blueprint $table) {
                $table->dropUnique('certificates_verification_token_unique');
                $table->dropColumn('verification_token');
                $table->dropColumn('revoked_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally not restored: old token records and logs are discarded.
        // Back up the database before upgrading from v1.1.
    }
};
