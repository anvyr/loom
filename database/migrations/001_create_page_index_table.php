<?php

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('page_index', function (Blueprint $table) {
            $table->string('id', 36);
            $table->string('slug', 500);
            $table->string('path', 1000);
            $table->integer('mtime');
            $table->string('format', 20);
            $table->string('title', 500);
            $table->string('status', 20);
            $table->string('layout', 100)->nullable();
            $table->text('excerpt')->nullable();
            $table->boolean('trusted')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('meta_json')->default('{}');
            $table->primary('slug');
            $table->unique('id');
            $table->index('status');
            $table->index('created_at');
            $table->index('published_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('page_index');
    }
};
