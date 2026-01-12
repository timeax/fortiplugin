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
		Schema::create("scpl_plugin_process", static function (
			Blueprint $table,
		) {
			$table->id();
			$table->bigInteger("source_id");
			$table->enum("type", ["installer", "activator", "deactivator"]);
			$table->enum("status", ["success", "failed", "pending"]);
			$table->string("run_id")->unique();
			$table->timestamps();
			$table->unique(["type", "source_id", "run_id"]);
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_plugin_process");
	}
};
