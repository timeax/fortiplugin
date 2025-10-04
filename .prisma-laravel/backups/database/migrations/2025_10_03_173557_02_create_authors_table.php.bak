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
		Schema::create("scpl_authors", function (Blueprint $table) {
			$table->id();
			$table->string("slug");
			$table->string("name");
			$table->string("handle")->nullable();
			$table->string("email")->nullable();
			$table->string("password");
			$table->string("avatar_url")->nullable();
			$table->string("org")->nullable();
			$table->string("website")->nullable();
			$table->json("meta")->nullable();
			$table
				->enum("status", ["pending", "active", "inactive", "blocked"])
				->default("pending");
			$table->boolean("verified")->default(false);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("scpl_authors");
	}
};
