<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTapMultipliersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tap_multipliers', function (Blueprint $table) {
            $table->id();
            $table->decimal('coefficient', 10, 2)->unique(); // Ex: 1, 2, 5, 10
            $table->integer('required_taps');
            $table->timestamps();

            // Index pour optimiser la recherche par coefficient
            $table->index('coefficient');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tap_multipliers');
    }
}
