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
		Schema::create("scpl_plugin_settings", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("scpl_plugins", "id")
				->onDelete("cascade")
				->onUpdate("no action");
			$table->string("key")->unique();
			$table->string("group");
			$table->string("label");
			$table->longText("value");
			$table
				->enum("type", [
					"string",
					"number",
					"boolean",
					"json",
					"file",
					"blob",
					"tristate",
					"multiselect",
					"select",
					"checkbox",
					"radio",
					"chips",
				])
				->default("string");
			$table->boolean("is_required")->default(true);
			$table->boolean("is_sensitive")->default(false);
			$table->timestamps();
			$table->index("plugin_id");
			$table->unique(["plugin_id", "key"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_settings");
	}
};
