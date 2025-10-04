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
		Schema::create("scpl_plugin_tokens", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("plugin_placeholder_id")
				->constrained("placeholders", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("token_hash");
			$table->timestamp("expires_at");
			$table->timestamp("last_used")->nullable();
			$table->boolean("revoked")->default(false);
			$table
				->foreignId("author_id")
				->nullable()
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->timestamp("created_at")->useCurrent();
			$table->index("plugin_placeholder_id");
			$table->index("author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_tokens");
	}
};
