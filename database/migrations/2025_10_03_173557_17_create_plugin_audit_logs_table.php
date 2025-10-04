<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create("scpl_plugin_audit_logs", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("actor")->nullable();
			$table
				->foreignId("actor_author_id")
				->nullable()
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("type");
			$table->string("action");
			$table->string("resource");
			$table->json("context")->nullable();
			$table->timestamp("created_at")->useCurrent();
			$table->index("plugin_id");
			$table->index("actor_author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_audit_logs");
	}
};
