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
		Schema::create("scpl_file_permissions", static function (
			Blueprint $table,
		) {
			$table->id();
			$table
				->string("natural_key")
				->unique()
				->comment(
					"Deterministic natural key (e.g., hash of base_dir/paths/action-set)",
				);
			$table->string("base_dir");
			$table->json("paths");
			$table->json("permissions");
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_file_permissions");
	}
};
