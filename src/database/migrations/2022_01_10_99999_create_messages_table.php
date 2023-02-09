<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ch_messages', function (Blueprint $table) {
            $table->bigInteger('id');
            $table->string('type');
            $table->string('form_type');
            $table->bigInteger('from_id');
            $table->bigInteger('to_id');
            $table->string('to_type');
            $table->bigInteger('room_id')->nullable();
            $table->string('body',5000)->nullable();
            $table->string('attachment')->nullable();
            $table->boolean('seen')->default(false);
            $table->timestamps();

            // TODO create indices
            // $table->index('from_id');
            // $table->index('to_id');
            // $table->index('to_type');
            // $table->index('room_id');



            $table->primary('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ch_messages');
    }
}
