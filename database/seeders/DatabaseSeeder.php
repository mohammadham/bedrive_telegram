<?php

namespace Database\Seeders;

use Common\Tags\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class DatabaseSeeder extends Seeder
{
    public function __construct(private Tag $tag)
    {
    }

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create tag for starring file entries
        $this->tag->firstOrCreate([
            'name' => 'starred',
            'display_name' => 'Starred',
            'type' => 'label',
        ]);

        $this->call(WorkspaceRoleSeeder::class);

        $this->call(SeedDemoProducts::class);
        if (Schema::hasTable('settings')) {
            $defaultSettings = [
                'storage_telegram_api_id' => '',
                'storage_telegram_api_hash' => '',
                'storage_telegram_phone' => '',
                'storage_telegram_chat_id' => '',
            ];

            foreach ($defaultSettings as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['name' => $key],
                    ['value' => $value]
                );
                }
            }
    }
}
