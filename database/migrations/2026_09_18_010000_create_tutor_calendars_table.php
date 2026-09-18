<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('tutor_calendars', function (Blueprint $table) {
      $table->id();
      $table->foreignId('tutor_id')->constrained('users')->cascadeOnDelete();
      $table->date('calendar_date');
      $table->string('name', 150);
      $table->timestamps();

      $table->unique(['tutor_id', 'calendar_date']);
    });

    Schema::table('tutor_availabilities', function (Blueprint $table) {
      $table->foreignId('calendar_id')
        ->nullable()
        ->after('tutor_id')
        ->constrained('tutor_calendars')
        ->cascadeOnDelete();
    });
  }

  public function down(): void
  {
    Schema::table('tutor_availabilities', function (Blueprint $table) {
      $table->dropForeign(['calendar_id']);
      $table->dropColumn('calendar_id');
    });

    Schema::dropIfExists('tutor_calendars');
  }
};
