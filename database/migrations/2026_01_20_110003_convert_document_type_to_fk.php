<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add FK column to tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->after('draft_document_path')->constrained('document_types')->nullOnDelete();
            $table->index('document_type_id');
        });

        // Add FK column to contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('document_type_id')->nullable()->after('proposed_document_title')->constrained('document_types')->nullOnDelete();
            $table->index('document_type_id');
        });

        // Migrate existing ENUM data to FK
        $this->migrateTicketDocumentTypes();
        $this->migrateContractDocumentTypes();

        // Drop old ENUM columns
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }

    private function migrateTicketDocumentTypes(): void
    {
        $typeMap = DB::table('document_types')->pluck('id', 'code');
        
        foreach ($typeMap as $code => $id) {
            DB::table('tickets')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(COALESCE(document_type, 'null'), '$')) = ?", [$code])
                ->orWhere('document_type', $code)
                ->update(['document_type_id' => $id]);
        }
    }

    private function migrateContractDocumentTypes(): void
    {
        $typeMap = DB::table('document_types')->pluck('id', 'code');
        
        foreach ($typeMap as $code => $id) {
            DB::table('contracts')
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(COALESCE(document_type, 'null'), '$')) = ?", [$code])
                ->orWhere('document_type', $code)
                ->update(['document_type_id' => $id]);
        }
    }

    public function down(): void
    {
        // Recreate ENUM columns
        Schema::table('tickets', function (Blueprint $table) {
            $table->enum('document_type', ['perjanjian', 'nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'surat_lainnya'])->after('draft_document_path');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('document_type', ['perjanjian', 'nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'surat_lainnya'])->after('proposed_document_title');
        });

        // Drop FK columns
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn('document_type_id');
        });
    }
};
