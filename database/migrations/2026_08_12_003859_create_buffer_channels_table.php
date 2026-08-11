<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buffer_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buffer_connection_id')->constrained()->cascadeOnDelete();
            $table->string('service');
            $table->string('channel_id');
            $table->string('channel_name');
            $table->timestamps();

            $table->unique(['buffer_connection_id', 'service']);
        });

        foreach (DB::table('buffer_connections')->whereNotNull('channel_id')->get() as $connection) {
            DB::table('buffer_channels')->insert([
                'buffer_connection_id' => $connection->id,
                'service' => 'twitter',
                'channel_id' => $connection->channel_id,
                'channel_name' => $connection->channel_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('buffer_connections', function (Blueprint $table) {
            $table->dropColumn(['channel_id', 'channel_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buffer_connections', function (Blueprint $table) {
            $table->string('channel_id')->nullable();
            $table->string('channel_name')->nullable();
        });

        foreach (DB::table('buffer_channels')->where('service', 'twitter')->get() as $channel) {
            DB::table('buffer_connections')->where('id', $channel->buffer_connection_id)->update([
                'channel_id' => $channel->channel_id,
                'channel_name' => $channel->channel_name,
            ]);
        }

        Schema::dropIfExists('buffer_channels');
    }
};
