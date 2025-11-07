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
		Schema::create("scpl_plugin_route_permissions", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("scpl_plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->string("route_id")
				->comment("The JSON-declared route `id` (unique per plugin).");
			$table
				->enum("status", ["pending", "approved", "denied", "revoked"])
				->default("pending")
				->comment("Current permission state.");
			$table
				->string("guard")
				->nullable()
				->comment(
					"Optional: lock the guard used when writing this route.",
				);
			$table
				->json("meta")
				->nullable()
				->comment(
					"Host-defined metadata (notes, expiresAt, reasons, etc.).",
				);
			$table->timestamp("approved_at")->nullable();
			$table->timestamps();
			$table->index(["plugin_id", "status"]);
			$table->unique(["plugin_id", "route_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_route_permissions");
	}
};
