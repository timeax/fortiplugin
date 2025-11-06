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
				->enum("status", ["active", "inactive", "archived"])
				->default("active");
			$table->json("config")->nullable();
			$table->json("meta")->nullable();
			$table
				->foreignId("plugin_placeholder_id")
				->constrained("scpl_placeholders", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->bigInteger("active_version_id");
			$table->string("owner_ref")->nullable();
			$table->timestamp("activated_at")->nullable();
			$table->bigInteger("activated_by")->nullable();
			$table->timestamps();
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
