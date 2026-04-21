<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('locked_until');
            }

            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            }

            if (! Schema::hasColumn('users', 'temp_password_expires_at')) {
                $table->timestamp('temp_password_expires_at')->nullable()->after('password_changed_at');
            }

            if (! Schema::hasColumn('users', 'lifecycle_locked_at')) {
                $table->timestamp('lifecycle_locked_at')->nullable()->after('temp_password_expires_at');
            }

            if (! Schema::hasColumn('users', 'lifecycle_lock_reason')) {
                $table->string('lifecycle_lock_reason', 255)->nullable()->after('lifecycle_locked_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'lifecycle_lock_reason')) {
                $table->dropColumn('lifecycle_lock_reason');
            }

            if (Schema::hasColumn('users', 'lifecycle_locked_at')) {
                $table->dropColumn('lifecycle_locked_at');
            }

            if (Schema::hasColumn('users', 'temp_password_expires_at')) {
                $table->dropColumn('temp_password_expires_at');
            }

            if (Schema::hasColumn('users', 'password_changed_at')) {
                $table->dropColumn('password_changed_at');
            }

            if (Schema::hasColumn('users', 'must_change_password')) {
                $table->dropColumn('must_change_password');
            }
        });
    }
};
