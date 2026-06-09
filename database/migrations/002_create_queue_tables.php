<?php

declare(strict_types=1);

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;

return new class
{
    public function up(Schema $schema): void
    {
        $schema->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100)->default('default');
            $table->longText('payload');
            $table->integer('attempts', false, true)->default(0);
            $table->string('unique_id', 255)->nullable();
            $table->timestamp('available_at');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('queue');
            $table->index(['queue', 'available_at']);
            $table->unique('unique_id');
        });

        $schema->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue', 100);
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index('queue');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('failed_jobs');
        $schema->dropIfExists('jobs');
    }
};
