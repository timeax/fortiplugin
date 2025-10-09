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
		Schema::create("scpl_plugin_permissions", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->enum("permission_type", [
				"db",
				"file",
				"notification",
				"module",
				"network",
				"codec",
			]);
			$table->bigInteger("permission_id");
			$table->boolean("active")->default(true);
			$table->boolean("limited")->default(false);
			$table->string("limit_type")->nullable();
			$table->string("limit_value")->nullable();
			$table->json("constraints")->nullable();
			$table->json("audit")->nullable();
			$table->timestamps();
			$table->index("plugin_id");
			$table->unique(["plugin_id", "permission_type", "permission_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_permissions");
	}
};
