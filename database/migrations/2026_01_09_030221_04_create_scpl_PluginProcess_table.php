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
		Schema::create("scpl_PluginProcess", static function (
			Blueprint $table,
		) {
			$table->id();
			$table->bigInteger("source_id");
			$table->enum("type", ["installer", "activator"]);
			$table->enum("status", ["success", "failed", "pending"]);
			$table->string("run_id")->unique();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_PluginProcess");
	}
};
