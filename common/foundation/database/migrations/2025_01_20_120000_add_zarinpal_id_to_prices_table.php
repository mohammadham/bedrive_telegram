<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZarinpalIdToPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('prices', 'zarinpal_id')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->string('zarinpal_id', 50)->nullable()->after('paypal_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('prices', 'zarinpal_id')) {
            Schema::table('prices', function (Blueprint $table) {
                $table->dropColumn('zarinpal_id');
            });
        }
    }
}
