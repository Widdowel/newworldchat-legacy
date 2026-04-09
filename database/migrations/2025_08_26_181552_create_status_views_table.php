<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatusViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('status_views', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('status_id')->constrained('statuses')->onDelete('cascade');
        //     $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        //     $table->timestamp('viewed_at');
        //     $table->timestamps();


        //     // Un utilisateur ne peut voir qu'une fois le même statut
        //     $table->unique(['status_id', 'user_id']);
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('status_views');
    }
}
