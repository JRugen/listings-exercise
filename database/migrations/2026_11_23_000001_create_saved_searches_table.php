<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Every criterion is optional; a null column means "any".
            $table->string('property_type')->nullable();
            // Not a foreign key: branches.region isn't unique, so it's checked
            // with an exists:branches,region validation rule instead.
            $table->string('region')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('region');
            $table->index('property_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
