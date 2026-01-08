<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorrectionBreaksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('correction_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_correction_id');
            $table->dateTime('break_start_at');
            $table->dateTime('break_end_at');
            $table->timestamps();

            $table->foreign('attendance_correction_id')
                ->references('id')->on('attendance_corrections')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('correction_breaks', function (Blueprint $table) {
            $table->dropForeign(['attendance_correction_id']);
        });

        Schema::dropIfExists('correction_breaks');
    }
}
