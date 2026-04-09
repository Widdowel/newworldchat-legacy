<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['voice', 'video']);
            $table->enum('status', ['ongoing', 'ended', 'missed', 'cancelled'])->default('ongoing');
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade');

            $table->integer('duration')->nullable(); // en secondes
            $table->timestamp('ended_at')->nullable();
            $table->string('channel_id')->nullable(); // identifiant salle (si WebRTC / Agora)

            $table->timestamps();
        });

        Schema::create('call_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('role', ['host', 'participant'])->default('participant');
            $table->enum('status', ['joined', 'missed', 'left'])->default('joined');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calls');
    }
}
