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
		Schema::create("scpl_plugin_signatures", function (Blueprint $table) {
			$table->id();
			$table
				->foreignId("placeholder_id")
				->constrained("scpl_placeholders", "id")
				->onDelete("no action")
				->onUpdate("no action");
			$table->string("host_domain");
			$table->string("owner_host");
			$table->string("plugin_key");
			$table->string("signature");
			$table->timestamps();
			$table->index("placeholder_id");
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_signatures");
	}
};
