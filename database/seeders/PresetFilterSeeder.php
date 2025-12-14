<?php

namespace Database\Seeders;

use App\Models\PresetFilter;
use Illuminate\Database\Seeder;

class PresetFilterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $presets = [
            [
                'name' => 'Judi Online Indonesia',
                'description' => 'Filter untuk spam judi online, slot, togel yang umum di Indonesia',
                'category' => 'spam',
                'filters_data' => [
                    ['type' => 'keyword', 'pattern' => 'slot gacor', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'slot online', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'slot maxwin', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel online', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel singapore', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'togel hongkong', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'judi bola', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'taruhan bola', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'link alternatif', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'deposit pulsa', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'rtp live', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'rtp slot', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'maxwin', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'scatter', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'jp besar', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'gacor hari ini', 'match_type' => 'contains'],
                    ['type' => 'regex', 'pattern' => '(slot|togel|judi|casino)\\s*(gacor|online|maxwin)', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => '(deposit|wd|withdraw)\\s*(pulsa|dana|ovo|gopay)', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => 'wa\\.me\\/\\d+', 'match_type' => 'regex'],
                ],
            ],
            [
                'name' => 'Buzzer & Self Promotion',
                'description' => 'Filter untuk buzzer dan spam promosi diri',
                'category' => 'self_promotion',
                'filters_data' => [
                    ['type' => 'keyword', 'pattern' => 'cek bio', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'link di bio', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'followers gratis', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'follower gratis', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'kunjungi profil', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'visit profile', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'DM untuk info', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'DM for info', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'sub balik', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'subscribe balik', 'match_type' => 'contains'],
                    ['type' => 'keyword', 'pattern' => 'follow balik', 'match_type' => 'contains'],
                    ['type' => 'regex', 'pattern' => '(cek|klik|visit)\\s*(bio|profil|profile)', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => '(gratis|free)\\s*(followers?|likes?|subscribers?)', 'match_type' => 'regex'],
                ],
            ],
            [
                'name' => 'Suspicious URLs',
                'description' => 'Filter untuk URL mencurigakan dan shorteners',
                'category' => 'scam',
                'filters_data' => [
                    ['type' => 'url', 'pattern' => 'bit.ly', 'match_type' => 'contains'],
                    ['type' => 'url', 'pattern' => 's.id', 'match_type' => 'contains'],
                    ['type' => 'url', 'pattern' => 'linktr.ee', 'match_type' => 'contains'],
                    ['type' => 'url', 'pattern' => 'tinyurl.com', 'match_type' => 'contains'],
                    ['type' => 'regex', 'pattern' => 'https?://[^\\s]*slot[^\\s]*', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => 'https?://[^\\s]*togel[^\\s]*', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => 'https?://[^\\s]*casino[^\\s]*', 'match_type' => 'regex'],
                    ['type' => 'regex', 'pattern' => 'https?://[^\\s]*gacor[^\\s]*', 'match_type' => 'regex'],
                ],
            ],
            [
                'name' => 'Spam Patterns',
                'description' => 'Filter untuk pola spam umum (emoji spam, karakter berulang)',
                'category' => 'spam',
                'filters_data' => [
                    ['type' => 'emoji_spam', 'pattern' => '10', 'match_type' => 'exact', 'action' => 'flag'],
                    ['type' => 'repeat_char', 'pattern' => '5', 'match_type' => 'exact', 'action' => 'flag'],
                ],
            ],
            [
                'name' => 'Hate Speech Indonesia',
                'description' => 'Filter untuk kata-kata kasar dan hate speech dalam Bahasa Indonesia',
                'category' => 'hate_speech',
                'filters_data' => [
                    ['type' => 'keyword', 'pattern' => 'anjing', 'match_type' => 'contains', 'action' => 'hide'],
                    ['type' => 'keyword', 'pattern' => 'bangsat', 'match_type' => 'contains', 'action' => 'hide'],
                    ['type' => 'keyword', 'pattern' => 'babi', 'match_type' => 'contains', 'action' => 'hide'],
                    ['type' => 'keyword', 'pattern' => 'tolol', 'match_type' => 'contains', 'action' => 'hide'],
                    ['type' => 'keyword', 'pattern' => 'goblok', 'match_type' => 'contains', 'action' => 'hide'],
                    ['type' => 'keyword', 'pattern' => 'bodoh', 'match_type' => 'contains', 'action' => 'flag'],
                ],
            ],
        ];

        foreach ($presets as $preset) {
            PresetFilter::updateOrCreate(
                ['name' => $preset['name']],
                [
                    'description' => $preset['description'],
                    'category' => $preset['category'],
                    'filters_data' => $preset['filters_data'],
                    'is_active' => true,
                ]
            );
        }
    }
}
