<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The branch whose till took the payment. Kept on the payment so a cash-up does not move
     * when a staff member later transfers to another branch.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('recorded_by')->constrained()->nullOnDelete();
            $table->index(['paid_at']);
            $table->index(['reversed_at']);
        });

        // Existing payments: best available guess is the recorder's current branch.
        DB::table('payments')->whereNull('branch_id')->update([
            'branch_id' => DB::raw('(SELECT users.branch_id FROM users WHERE users.id = payments.recorded_by)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropIndex(['reversed_at']);
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
