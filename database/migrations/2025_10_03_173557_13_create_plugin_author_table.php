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
		Schema::create("scpl_plugin_author", function (Blueprint $table) {
			$table
				->foreignId("plugin_id")
				->constrained("plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->foreignId("author_id")
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->enum("role", ["owner", "maintainer", "contributor"])
				->default("contributor");
			$table->timestamp("created_at")->useCurrent();
			$table->primary(["plugin_id", "author_id"]);
			$table->index("author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_author");
	}
};
