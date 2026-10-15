<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify role column to support 'admin' (L3 Support Admin) alongside 'super_admin', 'admin_tengkulak', 'petani', 'pembeli'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'admin_tengkulak', 'petani', 'pembeli') NOT NULL DEFAULT 'pembeli'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin_tengkulak', 'petani', 'pembeli') NOT NULL DEFAULT 'pembeli'");
        }
    }
};
