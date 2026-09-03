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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('telephone')->nullable()->after('email');
            $table->string('address')->nullable()->after('telephone');
        });

        // `name` is dropped in its own schema call, after the columns that
        // replace it exist - a single-request name (from Jetstream's own
        // registration/profile forms, unchanged) is split into first/last
        // name server-side by the Fortify actions that write these columns,
        // so no separate backfill step is needed for existing rows created
        // before this migration ran in a fresh install.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'telephone', 'address']);
        });
    }
};
