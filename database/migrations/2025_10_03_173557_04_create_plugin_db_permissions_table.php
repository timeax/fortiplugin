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
		Schema::create("scpl_plugin_db_permissions", function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("tag_id")
				->constrained("tags", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("model");
			$table->boolean("select")->default(false);
			$table->boolean("insert")->default(false);
			$table->boolean("update")->default(false);
			$table->boolean("grouped_queries")->default(false);
			$table->boolean("truncate")->default(false);
			$table->boolean("delete")->default(false);
			$table->json("hidden_fields")->nullable();
			$table->json("writable_fields")->nullable();
			$table->boolean("limited")->default(false);
			$table->string("limit_type")->nullable();
			$table->string("limit_value")->nullable();
			$table->timestamps();
			$table->index("tag_id");
			$table->unique(["tag_id", "model"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_db_permissions");
	}
};
