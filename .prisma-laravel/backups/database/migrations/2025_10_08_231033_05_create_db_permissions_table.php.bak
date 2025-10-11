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
		Schema::create("scpl_db_permissions", function (Blueprint $table) {
			$table->id();
			$table
				->string("natural_key")
				->comment(
					"Deterministic natural key (e.g., hash of model/table/columns/action-set)",
				);
			$table->string("model")->nullable();
			$table->string("table")->nullable();
			$table->json("permissions");
			$table->json("readable_columns")->nullable();
			$table->json("writable_columns")->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_db_permissions");
	}
};
