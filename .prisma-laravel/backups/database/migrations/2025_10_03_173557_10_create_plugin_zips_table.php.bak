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
		Schema::create("scpl_plugin_zips", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("placeholder_id")
				->constrained("placeholders", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->text("path");
			$table->json("meta")->default("{}");
			$table
				->enum("status", ["active", "inactive", "archived"])
				->default("active");
			$table
				->enum("validation_status", [
					"valid",
					"unchecked",
					"failed",
					"pending",
				])
				->default("unchecked");
			$table
				->foreignId("uploaded_by_author_id")
				->nullable()
				->constrained("authors", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->timestamps();
			$table->index("placeholder_id");
			$table->index("uploaded_by_author_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_zips");
	}
};
