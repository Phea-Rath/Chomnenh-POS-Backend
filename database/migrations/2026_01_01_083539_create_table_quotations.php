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
        Schema::create('quotations', function (Blueprint $table) {
             $table->id('quotation_id'); // int unsigned auto increment

            $table->string('quotation_number', 100);

            $table->unsignedInteger('customer_id');

            $table->date('date');

            $table->integer('credit_term')
                  ->default(0)
                  ->comment('រយៈពេល (ថ្ងៃ)');

            $table->date('date_term')
                  ->nullable()
                  ->comment('កាលបរិច្ឆេទផុតកំណត់');

            $table->decimal('order_total', 10, 2)
                  ->default(0.00)
                  ->comment('សរុបការលក់');
            $table->decimal('tax', 3, 2)
                  ->default(0.00);

            $table->decimal('delivery_fee', 10, 2)
                  ->default(0.00)
                  ->comment('សេវាដឹក');

            $table->decimal('total_discount', 10, 2)
                  ->default(0.00)
                  ->comment('បញ្ចុះតំលៃសរុប');

            $table->decimal('grand_total', 10, 2)
                  ->default(0.00)
                  ->comment('សរុបគ្រប់យ៉ាង');

            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected'
            ])->default('draft');

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('profile_id');

            $table->integer('created_by');

            $table->timestamps();

            // Optional: Foreign Keys (if exist)
            $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
            $table->foreign('profile_id')->references('id')->on('profiles')->onDelete('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('table_quotations');
    }
};
