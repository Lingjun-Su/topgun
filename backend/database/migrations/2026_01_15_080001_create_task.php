<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->morphs('taskable');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // 改为 no action 或 restrict
            $table->foreignId('assignee_id')
                ->constrained('users')
                ->onDelete('no action'); // 或者 ->onDelete('restrict')

            $table->string('action_url')->nullable();
            $table->string('status')->default('todo')->index();
            $table->string('priority')->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
