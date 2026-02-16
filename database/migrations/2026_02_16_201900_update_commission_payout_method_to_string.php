<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_payouts') || ! Schema::hasColumn('commission_payouts', 'payout_method')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `commission_payouts` MODIFY `payout_method` VARCHAR(60) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "commission_payouts" ALTER COLUMN "payout_method" TYPE VARCHAR(60)');
            DB::statement('ALTER TABLE "commission_payouts" ALTER COLUMN "payout_method" DROP NOT NULL');
        } elseif ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE [commission_payouts] ALTER COLUMN [payout_method] NVARCHAR(60) NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('commission_payouts') || ! Schema::hasColumn('commission_payouts', 'payout_method')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE `commission_payouts` SET `payout_method` = 'other' WHERE `payout_method` IS NOT NULL AND `payout_method` NOT IN ('bank','mobile','cash','other')");
            DB::statement("ALTER TABLE `commission_payouts` MODIFY `payout_method` ENUM('bank','mobile','cash','other') NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE \"commission_payouts\" SET \"payout_method\" = 'other' WHERE \"payout_method\" IS NOT NULL AND \"payout_method\" NOT IN ('bank','mobile','cash','other')");
            DB::statement("ALTER TABLE \"commission_payouts\" DROP CONSTRAINT IF EXISTS commission_payouts_payout_method_check");
            DB::statement("ALTER TABLE \"commission_payouts\" ADD CONSTRAINT commission_payouts_payout_method_check CHECK (payout_method IN ('bank','mobile','cash','other') OR payout_method IS NULL)");
        } elseif ($driver === 'sqlsrv') {
            DB::statement("UPDATE [commission_payouts] SET [payout_method] = 'other' WHERE [payout_method] IS NOT NULL AND [payout_method] NOT IN ('bank','mobile','cash','other')");
            DB::statement('ALTER TABLE [commission_payouts] ALTER COLUMN [payout_method] NVARCHAR(20) NULL');
        }
    }
};

