<?php

use Anvyr\Loom\Database\Schema\Blueprint;
use Anvyr\Loom\Database\Schema\Schema;

return new class {
    public function up(Schema $schema): void
    {
        $schema->create('data_store', function (Blueprint $table) {
            $table->id();
            $table->string('collection', 100);
            $table->string('key', 255);
            $table->text('data');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['collection', 'key']);
            $table->index('collection');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('data_store');
    }
};
