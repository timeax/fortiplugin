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
		Schema::create("network_permissions", function (Blueprint $table) {
			$table->id();
			$table
				->string("natural_key")
				->unique()
				->comment(
					"Deterministic fingerprint for this rule (e.g., sha1 over hosts/methods/schemes/ports/paths).",
				);
			$table
				->string("label")
				->nullable()
				->comment(
					"Optional human-readable label for admins/reviewers.",
				);
			$table
				->boolean("access")
				->default(false)
				->comment("Gate for network egress via the host HTTP client.");
			$table
				->json("hosts")
				->comment("Allowlists (manifest `target` fields)");
			$table->json("methods");
			$table->json("schemes")->nullable();
			$table->json("ports")->nullable();
			$table->json("paths")->nullable();
			$table->json("headers_allowed")->nullable();
			$table->json("ips_allowed")->nullable();
			$table
				->boolean("auth_via_host_secret")
				->default(true)
				->comment(
					"Secrets policy: if true, the host injects credentials; plugins don't supply secrets.",
				);
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists("network_permissions");
	}
};
