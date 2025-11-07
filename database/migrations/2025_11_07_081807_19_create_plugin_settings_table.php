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
		Schema::create("plugin_settings", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("plugins", "id")
				->onDelete("cascade")
				->onUpdate("no action");
			$table->string("key");
			$table->longText("value");
			$table
				->enum("type", [
					"string",
					"number",
					"boolean",
					"json",
					"file",
					"blob",
				])
				->default("string");
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
		Schema::dropIfExists("plugin_settings");
	}
};
