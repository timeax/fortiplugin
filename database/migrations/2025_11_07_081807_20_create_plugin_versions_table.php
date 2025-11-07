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
		Schema::create("plugin_versions", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("plugin_id")
				->constrained("plugins", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("version");
			$table->string("archive_url");
			$table->json("manifest")->nullable();
			$table->json("validation_report")->nullable();
			$table
				->enum("status", [
					"valid",
					"unchecked",
					"unverified",
					"failed",
					"pending",
				])
				->default("unchecked");
			$table->timestamps();
			$table->index("plugin_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("plugin_versions");
	}
};
