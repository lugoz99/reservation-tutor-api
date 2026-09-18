<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('tutor_availabilities', function (Blueprint $table) {
      $table->id();
      $table->foreignId('tutor_id')->constrained('users')->cascadeOnDelete();
      $table->date('availability_date');
      $table->time('start_time');
      $table->time('end_time');
      $table->timestamps();

      $table->unique(
        ['tutor_id', 'availability_date', 'start_time'],
        'tutor_availability_slot_unique'
      );
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('tutor_availabilities');
  }
};
