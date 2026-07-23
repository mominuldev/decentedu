<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- Fees --------------------------------------------------------

        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'fee_head_name_unique');
        });

        Schema::create('fee_sub_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_bn')->nullable();
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'fee_head_id', 'name'], 'fee_sub_head_name_unique');
        });

        // Flat-amount or percentage waiver definitions (e.g. "Staff Ward 50%", "Merit Scholarship").
        Schema::create('fee_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 8, 2);
            $table->unsignedInteger('serial')->default(0);
            $table->boolean('status')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name'], 'fee_waiver_name_unique');
        });

        // Payable amount per class_config x sub_head x academic_year (the "fee structure").
        Schema::create('fee_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('fee_sub_head_id')->constrained('fee_sub_heads')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['class_config_id', 'fee_sub_head_id', 'academic_year_id'], 'fee_config_unique');
        });

        // Due date + flat fine per sub_head x academic_year (fine calculation: flat, not per-day).
        Schema::create('fee_time_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_sub_head_id')->constrained('fee_sub_heads')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('fine_amount', 8, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['fee_sub_head_id', 'academic_year_id'], 'fee_time_config_unique');
        });

        // Per-student waiver assignment; null fee_sub_head_id = applies to every sub_head.
        Schema::create('fee_waiver_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('fee_waiver_id')->constrained('fee_waivers')->cascadeOnDelete();
            $table->foreignId('fee_sub_head_id')->nullable()->constrained('fee_sub_heads')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['branch_id', 'student_id', 'academic_year_id'], 'fee_waiver_config_student_index');
        });

        // Per-student assessed fee (one row per student x sub_head x academic_year), generated
        // from fee_configs + fee_waiver_configs by the "assess" action. Partial payments are
        // allowed: due = payable + fine - waiver - paid.
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->foreignId('class_config_id')->constrained('class_configs')->cascadeOnDelete();
            $table->foreignId('fee_sub_head_id')->constrained('fee_sub_heads')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->decimal('payable_amount', 10, 2)->default(0);
            $table->decimal('waiver_amount', 10, 2)->default(0);
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->enum('status', ['due', 'partial', 'paid'])->default('due');
            $table->timestamps();

            $table->unique(['student_id', 'fee_sub_head_id', 'academic_year_id'], 'student_fee_unique');
            $table->index(['branch_id', 'class_config_id', 'academic_year_id'], 'student_fee_lookup_index');
        });

        // ---- Accounting ----------------------------------------------------
        // (created before fee_collections, which has a FK to vouchers)

        // Chart of accounts — a fixed default chart is auto-provisioned per branch (Cash,
        // Bank, one income account per fee head, Fine & Penalty Income); admins can add more
        // for manual journal/payment/contra vouchers (e.g. salary/utility expense accounts).
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->enum('type', ['asset', 'liability', 'income', 'expense', 'equity']);
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->boolean('is_system')->default(false);
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'code'], 'ledger_account_code_unique');
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['receive', 'payment', 'contra', 'journal']);
            $table->string('voucher_no');
            $table->date('date');
            $table->text('note')->nullable();
            $table->decimal('total', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['branch_id', 'voucher_no'], 'voucher_no_unique');
        });

        // sum(debit) = sum(credit) per voucher is an application-level invariant (enforced in
        // VoucherController/PostFeeCollectionToLedger inside a DB transaction) — not expressible
        // as a portable DB CHECK constraint since it's an aggregate across sibling rows.
        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['ledger_account_id'], 'voucher_entry_ledger_index');
        });

        // Receipt header — one collection can cover several student_fees at once.
        Schema::create('fee_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('receipt_no');
            $table->dateTime('collected_at');
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'bank', 'mobile_banking', 'cheque'])->default('cash');
            $table->string('note')->nullable();
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->foreignId('collected_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['branch_id', 'receipt_no'], 'fee_collection_receipt_unique');
        });

        Schema::create('fee_collection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_collection_id')->constrained('fee_collections')->cascadeOnDelete();
            $table->foreignId('student_fee_id')->constrained('student_fees')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('fine_paid', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
        Schema::dropIfExists('fee_collection_items');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_waiver_configs');
        Schema::dropIfExists('fee_time_configs');
        Schema::dropIfExists('fee_configs');
        Schema::dropIfExists('fee_waivers');
        Schema::dropIfExists('fee_sub_heads');
        Schema::dropIfExists('fee_heads');
    }
};
