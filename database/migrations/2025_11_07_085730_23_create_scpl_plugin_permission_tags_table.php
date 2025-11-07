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
		Schema::create("scpl_plugin_permission_tags", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("scpl_plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table
				->foreignId("tag_id")
				->constrained("scpl_permission_tags", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->boolean("active")->default(true);
			$table->boolean("limited")->default(false);
			$table->string("limit_type")->nullable();
			$table->string("limit_value")->nullable();
			$table->timestamps();
			$table->index("plugin_id");
			$table->index("tag_id");
			$table->unique(["plugin_id", "tag_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_permission_tags");
	}
};
