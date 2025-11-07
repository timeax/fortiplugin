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
		Schema::create("permission_tag_items", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("tag_id")
				->constrained("permission_tags", "id")
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
			$table->json("constraints")->nullable();
			$table->json("audit")->nullable();
			$table->timestamps();
			$table->index("tag_id");
			$table->unique(["tag_id", "permission_type", "permission_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("permission_tag_items");
	}
};
