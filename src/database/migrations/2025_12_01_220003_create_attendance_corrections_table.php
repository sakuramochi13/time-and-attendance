<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceCorrectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_id');

            $table->dateTime('requested_clock_in_at')->nullable();
            $table->dateTime('requested_clock_out_at')->nullable();

            $table->text('reason');

            $table->unsignedTinyInteger('status')->default(0);

            $table->unsignedTinyInteger('type')->default(1);

            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->dateTime('approved_at')->nullable();

            $table->timestamps();

            $table->foreign('attendance_id')
                ->references('id')->on('attendances')
                ->cascadeOnDelete();

            $table->foreign('approved_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('attendance_corrections', function (Blueprint $table) {
            $table->dropForeign(['attendance_id']);
            $table->dropForeign(['approved_by_user_id']);
        });

        Schema::dropIfExists('attendance_corrections');
    }
}
