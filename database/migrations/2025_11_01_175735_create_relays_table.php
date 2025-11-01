<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('relays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('inbox_url')->unique();
            $table->string('actor_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('following')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('last_successful_delivery_at')->nullable();
            $table->timestamp('last_failed_delivery_at')->nullable();
            $table->integer('failed_delivery_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'following']);
            $table->index('last_successful_delivery_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('relays');
    }
};
