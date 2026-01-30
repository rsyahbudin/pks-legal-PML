<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update enum to include 'perjanjian'
        DB::statement("ALTER TABLE contracts MODIFY COLUMN document_type ENUM('perjanjian', 'nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'lainnya') DEFAULT 'lainnya'");
    }

    public function down(): void
    {
        // Revert back to old enum values
        DB::statement("ALTER TABLE contracts MODIFY COLUMN document_type ENUM('nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'lainnya') DEFAULT 'lainnya'");
    }
};
