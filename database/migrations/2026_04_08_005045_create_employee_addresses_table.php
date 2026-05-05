<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Employee addresses: many rows per employee; type distinguishes current vs on-file address.
     *
     * type current|permanent (e.g. where the employee lives now vs registered/permanent address).
     * is_primary meaning is product-defined (e.g. one primary overall or per type); enforce in app.
     */
    public function up(): void
    {
        Schema::create('employee_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->comment('FK to employees; removed when employee is deleted.')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('type', 20)
                ->comment('current|permanent. Present vs on-file address for UI and validation.');
            $table->string('address_line_1', 255)
                ->comment('Primary street or building line.');
            $table->string('address_line_2', 255)
                ->nullable()
                ->comment('Optional unit, floor, or secondary line.');
            $table->string('barangay', 120)
                ->comment('Barangay or district.');
            $table->string('barangay_code', 10)
                ->nullable()
                ->comment('PSGC barangay code for canonical matching.');
            $table->string('city', 120)
                ->comment('City or municipality.');
            $table->string('city_code', 10)
                ->nullable()
                ->comment('PSGC city or municipality code for canonical matching.');
            $table->string('province', 120)
                ->comment('Province or region.');
            $table->string('province_code', 10)
                ->nullable()
                ->comment('PSGC province code for canonical matching.');
            $table->string('zip_code', 20)
                ->comment('Postal or ZIP code.');
            $table->string('country', 100)
                ->default('Philippines')
                ->comment('Country name or code.');
            $table->boolean('is_primary')
                ->default(false)
                ->comment('Primary address flag; uniqueness rules enforced in application logic.');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_addresses');
    }
};
