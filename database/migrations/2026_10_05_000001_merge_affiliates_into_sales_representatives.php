<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Affiliates are folded into sales representatives.
 *
 * The affiliate module never went live (its referral tracking was never
 * called and its tables are empty), while sales reps already earn commission
 * on customers assigned to them. A rep now gets a referral code; a customer
 * who signs up through it is recorded as referred by that rep and gets the
 * rep as default sales rep, which the commission engine already pays on.
 */
return new class extends Migration
{
    private const AFFILIATE_TABLES = ['affiliate_payouts', 'affiliate_commissions', 'affiliate_referrals', 'affiliates'];

    public function up(): void
    {
        foreach (self::AFFILIATE_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Table {$table} has data; migrate it to sales representatives before dropping.");
            }
        }

        Schema::table('sales_representatives', function (Blueprint $table) {
            $table->string('referral_code', 20)->nullable()->unique()->after('status');
        });

        foreach (DB::table('sales_representatives')->whereNull('referral_code')->pluck('id') as $id) {
            DB::table('sales_representatives')->where('id', $id)->update(['referral_code' => $this->uniqueCode()]);
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('referred_by_sales_rep_id')->nullable()->after('default_sales_rep_id')
                ->constrained('sales_representatives')->nullOnDelete();
        });

        if (Schema::hasColumn('customers', 'referred_by_affiliate_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('referred_by_affiliate_id');
            });
        }

        foreach (self::AFFILIATE_TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('affiliate_code', 50)->unique();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->enum('commission_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('fixed_commission_amount', 10, 2)->nullable();
            $table->decimal('total_earned', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->integer('total_referrals')->default(0);
            $table->integer('total_conversions')->default(0);
            $table->text('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('referrer_url')->nullable();
            $table->string('landing_page')->nullable();
            $table->enum('status', ['pending', 'converted', 'rejected'])->default('pending');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->foreignId('referral_id')->nullable()->constrained('affiliate_referrals')->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->onDelete('cascade');
            $table->string('payout_number')->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('referred_by_affiliate_id')->nullable()->after('id')->constrained('affiliates')->onDelete('set null');
            $table->dropConstrainedForeignId('referred_by_sales_rep_id');
        });

        Schema::table('sales_representatives', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (DB::table('sales_representatives')->where('referral_code', $code)->exists());

        return $code;
    }
};
