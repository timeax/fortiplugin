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
		Schema::create("scpl_module_permissions", function (Blueprint $table) {
			$table->id();
			$table
				->string("natural_key")
				->unique()
				->comment(
					"Deterministic natural key (e.g., hash of module/apis)",
				);
			$table->string("module");
			$table->json("apis");
			$table->boolean("access")->default(false);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_module_permissions");
	}
};
