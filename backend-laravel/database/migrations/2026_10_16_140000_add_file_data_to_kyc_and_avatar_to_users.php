<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_documents')) {
            Schema::table('kyc_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('kyc_documents', 'file_data')) {
                    $table->longText('file_data')->nullable()->after('file_path');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'avatar_url')) {
                    $table->longText('avatar_url')->nullable()->after('address');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kyc_documents')) {
            Schema::table('kyc_documents', function (Blueprint $table) {
                if (Schema::hasColumn('kyc_documents', 'file_data')) {
                    $table->dropColumn('file_data');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'avatar_url')) {
                    $table->dropColumn('avatar_url');
                }
            });
        }
    }
};
