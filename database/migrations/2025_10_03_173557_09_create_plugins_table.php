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
		Schema::create("scpl_plugins", function (Blueprint $table) {
			$table->id();
			$table->string("name");
			$table->string("image")->nullable();
			$table
				->foreignId("tag_id")
				->constrained("tags", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->enum("status", ["active", "inactive", "archived"])
				->default("active");
			$table->json("config")->nullable();
			$table->json("meta")->nullable();
			$table
				->foreignId("plugin_placeholder_id")
				->constrained("placeholders", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("owner_ref")->nullable();
			$table->index("tag_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugins");
	}
};
