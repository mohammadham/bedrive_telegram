<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZarinpalIdToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('users', 'zarinpal_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table
                ->string('zarinpal_id', 50)
                ->nullable()
                ->after('paypal_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'zarinpal_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('zarinpal_id');
            });
        }
    }
}
