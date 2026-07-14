<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Database\Seeder;

/**
 * Classic CSS gradient/abstract background library imported from the
 * user-supplied biolink_backgrounds preset file (July 2026).
 * Idempotent: updateOrCreate by slug, same contract as BgTemplateSeeder.
 */
class ClassicGradientBgTemplatesSeeder extends Seeder
{
    private const SORT_BASE = 400;

    public function run(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->templates() as $i => $tpl) {
            $tpl['css'] = \App\Modules\Admin\Controllers\BgTemplateController::sanitizeCss((string) $tpl['css']);
            $rows[] = array_merge($tpl, [
                'is_active'  => true,
                'sort_order' => self::SORT_BASE + $i,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        // Chunked bulk upsert: row-by-row updateOrCreate is far too slow
        // over the remote RDS (one round trip per template).
        foreach (array_chunk($rows, 40) as $chunk) {
            BgTemplate::upsert(
                $chunk,
                ['slug'],
                ['name', 'preview_color', 'category', 'css', 'js', 'is_active', 'sort_order', 'updated_at']
            );
        }
    }

    /** @return array<int, array<string,mixed>> */
    public function templates(): array
    {
        return array (
  0 => 
  array (
    'name' => 'Classic Gradient 01',
    'slug' => 'classic-gradient-01',
    'preview_color' => 'linear-gradient(135deg, #4158D0, #4158D0)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-01 { position:fixed; inset:0; z-index:-1; background-color: #4158D0;background-image: linear-gradient(43deg, #4158D0 0%, #C850C0 46%, #FFCC70 100%); }',
    'js' => NULL,
  ),
  1 => 
  array (
    'name' => 'Classic Gradient 02',
    'slug' => 'classic-gradient-02',
    'preview_color' => 'linear-gradient(135deg, #21D4FD, #21D4FD)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-02 { position:fixed; inset:0; z-index:-1; background-color: #21D4FD;background-image: linear-gradient(19deg, #21D4FD 0%, #B721FF 100%); }',
    'js' => NULL,
  ),
  2 => 
  array (
    'name' => 'Classic Gradient 03',
    'slug' => 'classic-gradient-03',
    'preview_color' => 'linear-gradient(135deg, #ffb418, #f73131)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-03 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(109.6deg, #ffb418 11.2%, #f73131 91.1%); }',
    'js' => NULL,
  ),
  3 => 
  array (
    'name' => 'Classic Gradient 04',
    'slug' => 'classic-gradient-04',
    'preview_color' => 'linear-gradient(135deg, #79F1A4, #0E5CAD)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-04 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, #79F1A4 10%, #0E5CAD 100%); }',
    'js' => NULL,
  ),
  4 => 
  array (
    'name' => 'Classic Gradient 05',
    'slug' => 'classic-gradient-05',
    'preview_color' => 'linear-gradient(135deg, #ff758c, #ff7eb3)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-05 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(to bottom, #ff758c, #ff7eb3); }',
    'js' => NULL,
  ),
  5 => 
  array (
    'name' => 'Classic Gradient 06',
    'slug' => 'classic-gradient-06',
    'preview_color' => 'linear-gradient(135deg, #3355ff, #0088ff)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-06 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(292.2deg, #3355ff 33.7%, #0088ff 93.7%); }',
    'js' => NULL,
  ),
  6 => 
  array (
    'name' => 'Classic Gradient 07',
    'slug' => 'classic-gradient-07',
    'preview_color' => 'linear-gradient(135deg, #fc5c7d, #6a82fb)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-07 { position:fixed; inset:0; z-index:-1; background: linear-gradient(to bottom, #fc5c7d, #6a82fb); }',
    'js' => NULL,
  ),
  7 => 
  array (
    'name' => 'Classic Gradient 08',
    'slug' => 'classic-gradient-08',
    'preview_color' => 'linear-gradient(135deg, rgb(32, 38, 57), rgb(63, 76, 119))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-08 { position:fixed; inset:0; z-index:-1; background: linear-gradient(112.1deg, rgb(32, 38, 57) 11.4%, rgb(63, 76, 119) 70.2%); }',
    'js' => NULL,
  ),
  8 => 
  array (
    'name' => 'Classic Gradient 09',
    'slug' => 'classic-gradient-09',
    'preview_color' => 'linear-gradient(135deg, rgba(100,43,115,1), rgba(4,0,4,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-09 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle farthest-corner at 10% 20%,  rgba(100,43,115,1) 0%, rgba(4,0,4,1) 90% ); }',
    'js' => NULL,
  ),
  9 => 
  array (
    'name' => 'Classic Gradient 10',
    'slug' => 'classic-gradient-10',
    'preview_color' => 'linear-gradient(135deg, #8EC5FC, #8EC5FC)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-10 { position:fixed; inset:0; z-index:-1; background-color: #8EC5FC;background-image: linear-gradient(62deg, #8EC5FC 0%, #E0C3FC 100%); }',
    'js' => NULL,
  ),
  10 => 
  array (
    'name' => 'Classic Gradient 11',
    'slug' => 'classic-gradient-11',
    'preview_color' => 'linear-gradient(135deg, #FFDEE9, #FFDEE9)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-11 { position:fixed; inset:0; z-index:-1; background-color: #FFDEE9;background-image: linear-gradient(0deg, #FFDEE9 0%, #B5FFFC 100%); }',
    'js' => NULL,
  ),
  11 => 
  array (
    'name' => 'Classic Gradient 12',
    'slug' => 'classic-gradient-12',
    'preview_color' => 'linear-gradient(135deg, #74EBD5, #74EBD5)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-12 { position:fixed; inset:0; z-index:-1; background-color: #74EBD5;background-image: linear-gradient(90deg, #74EBD5 0%, #9FACE6 100%); }',
    'js' => NULL,
  ),
  12 => 
  array (
    'name' => 'Classic Gradient 13',
    'slug' => 'classic-gradient-13',
    'preview_color' => 'linear-gradient(135deg, #FBDA61, #FBDA61)',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-13 { position:fixed; inset:0; z-index:-1; background-color: #FBDA61;background-image: linear-gradient(45deg, #FBDA61 0%, #FF5ACD 100%); }',
    'js' => NULL,
  ),
  13 => 
  array (
    'name' => 'Classic Gradient 14',
    'slug' => 'classic-gradient-14',
    'preview_color' => 'linear-gradient(135deg, rgba(249,21,215,1), rgba(22,0,98,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-14 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 179.7deg,  rgba(249,21,215,1) 1.1%, rgba(22,0,98,1) 99% ); }',
    'js' => NULL,
  ),
  14 => 
  array (
    'name' => 'Classic Gradient 15',
    'slug' => 'classic-gradient-15',
    'preview_color' => 'linear-gradient(135deg, rgba(2,37,78,1), rgba(4,56,126,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-15 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle farthest-corner at 10% 20%,  rgba(2,37,78,1) 0%, rgba(4,56,126,1) 19.7%, rgba(85,245,221,1) 100.2% ); }',
    'js' => NULL,
  ),
  15 => 
  array (
    'name' => 'Classic Gradient 16',
    'slug' => 'classic-gradient-16',
    'preview_color' => 'linear-gradient(135deg, rgba(136,80,226,1), rgba(16,13,91,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-16 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 91.2deg,  rgba(136,80,226,1) 4%, rgba(16,13,91,1) 96.5% ); }',
    'js' => NULL,
  ),
  16 => 
  array (
    'name' => 'Classic Gradient 17',
    'slug' => 'classic-gradient-17',
    'preview_color' => 'linear-gradient(135deg, rgba(254,253,205,1), rgba(163,230,255,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-17 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 109.6deg,  rgba(254,253,205,1) 11.2%, rgba(163,230,255,1) 91.1% ); }',
    'js' => NULL,
  ),
  17 => 
  array (
    'name' => 'Classic Gradient 18',
    'slug' => 'classic-gradient-18',
    'preview_color' => 'linear-gradient(135deg, rgba(255,244,228,1), rgba(240,246,238,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-18 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 174.2deg,  rgba(255,244,228,1) 7.1%, rgba(240,246,238,1) 67.4% ); }',
    'js' => NULL,
  ),
  18 => 
  array (
    'name' => 'Classic Gradient 19',
    'slug' => 'classic-gradient-19',
    'preview_color' => 'linear-gradient(135deg, rgba(254,122,152,0.81), rgba(255,206,134,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-19 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 64.3deg,  rgba(254,122,152,0.81) 17.7%, rgba(255,206,134,1) 64.7%, rgba(172,253,163,0.64) 112.1% ); }',
    'js' => NULL,
  ),
  19 => 
  array (
    'name' => 'Classic Gradient 20',
    'slug' => 'classic-gradient-20',
    'preview_color' => 'linear-gradient(135deg, rgba(252,37,103,1), rgba(250,38,151,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-20 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle farthest-corner at 10.2% 55.8%,  rgba(252,37,103,1) 0%, rgba(250,38,151,1) 46.2%, rgba(186,8,181,1) 90.1% ); }',
    'js' => NULL,
  ),
  20 => 
  array (
    'name' => 'Classic Gradient 21',
    'slug' => 'classic-gradient-21',
    'preview_color' => 'linear-gradient(135deg, rgba(97,186,255,1), rgba(166,239,253,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-21 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle farthest-corner at 10% 20%,  rgba(97,186,255,1) 0%, rgba(166,239,253,1) 90.1% ); }',
    'js' => NULL,
  ),
  21 => 
  array (
    'name' => 'Classic Gradient 22',
    'slug' => 'classic-gradient-22',
    'preview_color' => 'linear-gradient(135deg, rgba(225,200,239,1), rgba(163,225,233,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-22 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle 588px at 31.7% 40.2%,  rgba(225,200,239,1) 21.4%, rgba(163,225,233,1) 57.1% ); }',
    'js' => NULL,
  ),
  22 => 
  array (
    'name' => 'Classic Gradient 23',
    'slug' => 'classic-gradient-23',
    'preview_color' => 'linear-gradient(135deg, rgba(209,0,116,1), rgba(110,44,107,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-23 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient( 109.6deg,  rgba(209,0,116,1) 11.2%, rgba(110,44,107,1) 91.1% ); }',
    'js' => NULL,
  ),
  23 => 
  array (
    'name' => 'Classic Gradient 24',
    'slug' => 'classic-gradient-24',
    'preview_color' => 'linear-gradient(135deg, rgba(255,99,152,1), rgba(251,213,149,1))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-24 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient( circle 685.3px at 47.8% 55.1%,  rgba(255,99,152,1) 0%, rgba(251,213,149,1) 90.1% ); }',
    'js' => NULL,
  ),
  24 => 
  array (
    'name' => 'Classic Gradient 25',
    'slug' => 'classic-gradient-25',
    'preview_color' => 'linear-gradient(135deg, rgb(176, 207, 255), rgb(92, 104, 168))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-25 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgb(176, 207, 255),rgb(92, 104, 168)); }',
    'js' => NULL,
  ),
  25 => 
  array (
    'name' => 'Classic Gradient 26',
    'slug' => 'classic-gradient-26',
    'preview_color' => 'linear-gradient(135deg, rgb(1, 198, 86), rgb(47, 152, 24))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-26 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(1, 198, 86),rgb(47, 152, 24)); }',
    'js' => NULL,
  ),
  26 => 
  array (
    'name' => 'Classic Gradient 27',
    'slug' => 'classic-gradient-27',
    'preview_color' => 'linear-gradient(135deg, rgb(119, 14, 191), rgb(238, 141, 125))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-27 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(119, 14, 191),rgb(238, 141, 125)); }',
    'js' => NULL,
  ),
  27 => 
  array (
    'name' => 'Classic Gradient 28',
    'slug' => 'classic-gradient-28',
    'preview_color' => 'linear-gradient(135deg, rgb(238, 4, 44), rgb(202, 203, 40))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-28 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgb(238, 4, 44),rgb(202, 203, 40)); }',
    'js' => NULL,
  ),
  28 => 
  array (
    'name' => 'Classic Gradient 29',
    'slug' => 'classic-gradient-29',
    'preview_color' => 'linear-gradient(135deg, rgb(246, 19, 218), rgb(2, 28, 51))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-29 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgb(246, 19, 218),rgb(2, 28, 51)); }',
    'js' => NULL,
  ),
  29 => 
  array (
    'name' => 'Classic Gradient 30',
    'slug' => 'classic-gradient-30',
    'preview_color' => 'linear-gradient(135deg, rgb(18, 88, 19), rgb(40, 226, 59))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-30 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(18, 88, 19),rgb(40, 226, 59)); }',
    'js' => NULL,
  ),
  30 => 
  array (
    'name' => 'Classic Gradient 31',
    'slug' => 'classic-gradient-31',
    'preview_color' => 'linear-gradient(135deg, rgb(46, 243, 212), rgb(80, 17, 213))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-31 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom right, rgb(46, 243, 212),rgb(80, 17, 213),rgb(63, 130, 213)); }',
    'js' => NULL,
  ),
  31 => 
  array (
    'name' => 'Classic Gradient 32',
    'slug' => 'classic-gradient-32',
    'preview_color' => 'linear-gradient(135deg, rgb(254, 195, 95), rgb(233, 119, 44))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-32 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom center, rgb(254, 195, 95),rgb(233, 119, 44),rgb(244, 157, 70)); }',
    'js' => NULL,
  ),
  32 => 
  array (
    'name' => 'Classic Gradient 33',
    'slug' => 'classic-gradient-33',
    'preview_color' => 'linear-gradient(135deg, rgb(235, 41, 254), rgb(185, 42, 217))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-33 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top center, rgb(235, 41, 254),rgb(185, 42, 217),rgb(134, 42, 180),rgb(84, 43, 142),rgb(33, 43, 105)); }',
    'js' => NULL,
  ),
  33 => 
  array (
    'name' => 'Classic Gradient 34',
    'slug' => 'classic-gradient-34',
    'preview_color' => 'linear-gradient(135deg, rgb(80, 32, 84), rgb(62, 35, 81))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-34 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgb(80, 32, 84),rgb(62, 35, 81),rgb(44, 38, 79),rgb(26, 40, 76),rgb(8, 43, 73)); }',
    'js' => NULL,
  ),
  34 => 
  array (
    'name' => 'Classic Gradient 35',
    'slug' => 'classic-gradient-35',
    'preview_color' => 'linear-gradient(135deg, rgb(255, 13, 187), rgb(115, 55, 110))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-35 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top center, rgb(255, 13, 187),rgb(115, 55, 110)); }',
    'js' => NULL,
  ),
  35 => 
  array (
    'name' => 'Classic Gradient 36',
    'slug' => 'classic-gradient-36',
    'preview_color' => 'linear-gradient(135deg, rgb(95, 30, 254), rgb(66, 32, 127))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-36 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom center, rgb(95, 30, 254),rgb(66, 32, 127)); }',
    'js' => NULL,
  ),
  36 => 
  array (
    'name' => 'Classic Gradient 37',
    'slug' => 'classic-gradient-37',
    'preview_color' => 'linear-gradient(135deg, rgb(6, 204, 208), rgb(29, 101, 238))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-37 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center right, rgb(6, 204, 208),rgb(29, 101, 238)); }',
    'js' => NULL,
  ),
  37 => 
  array (
    'name' => 'Classic Gradient 38',
    'slug' => 'classic-gradient-38',
    'preview_color' => 'linear-gradient(135deg, rgb(203, 6, 229), rgb(9, 123, 207))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-38 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top center, rgb(203, 6, 229),rgb(9, 123, 207)); }',
    'js' => NULL,
  ),
  38 => 
  array (
    'name' => 'Classic Gradient 39',
    'slug' => 'classic-gradient-39',
    'preview_color' => 'linear-gradient(135deg, rgb(204, 90, 255), rgb(30, 8, 124))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-39 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center right, rgb(204, 90, 255),rgb(30, 8, 124)); }',
    'js' => NULL,
  ),
  39 => 
  array (
    'name' => 'Classic Gradient 40',
    'slug' => 'classic-gradient-40',
    'preview_color' => 'linear-gradient(135deg, rgb(213, 109, 39), rgb(29, 16, 5))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-40 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgb(213, 109, 39),rgb(29, 16, 5)); }',
    'js' => NULL,
  ),
  40 => 
  array (
    'name' => 'Classic Gradient 41',
    'slug' => 'classic-gradient-41',
    'preview_color' => 'linear-gradient(135deg, rgb(71, 71, 71), rgb(8, 8, 8))',
    'category' => 'gradient',
    'css' => '.bg-template-classic-gradient-41 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgb(71, 71, 71),rgb(8, 8, 8)); }',
    'js' => NULL,
  ),
  41 => 
  array (
    'name' => 'Abstract Blend 01',
    'slug' => 'classic-abstract-01',
    'preview_color' => 'linear-gradient(135deg, #FF00C7, #51003F)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-01 { position:fixed; inset:0; z-index:-1; background: linear-gradient(120deg, #FF00C7 0%, #51003F 100%), linear-gradient(120deg, #0030AD 0%, #00071A 100%), linear-gradient(180deg, #000346 0%, #FF0000 100%), linear-gradient(60deg, #0029FF 0%, #AA0014 100%), radial-gradient(100% 165% at 100% 100%, #FF00A8 0%, #00FF47 100%), radial-gradient(100% 150% at 0% 0%, #FFF500 0%, #51D500 100%);background-blend-mode: overlay, color-dodge, overlay, overlay, difference, normal; }',
    'js' => NULL,
  ),
  42 => 
  array (
    'name' => 'Abstract Blend 02',
    'slug' => 'classic-abstract-02',
    'preview_color' => 'linear-gradient(135deg, rgb(211, 255, 215), rgb(0, 0, 0))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-02 { position:fixed; inset:0; z-index:-1; background: linear-gradient(115deg, rgb(211, 255, 215) 0%, rgb(0, 0, 0) 100%), radial-gradient(90% 100% at 50% 0%, rgb(200, 200, 200) 0%, rgb(22, 0, 45) 100%), radial-gradient(100% 100% at 80% 0%, rgb(250, 255, 0) 0%, rgb(36, 0, 0) 100%), radial-gradient(150% 210% at 100% 0%, rgb(112, 255, 0) 0%, rgb(20, 175, 125) 0%, rgb(0, 10, 255) 100%), radial-gradient(100% 100% at 100% 30%, rgb(255, 77, 0) 0%, rgba(0, 200, 255, 1) 100%), linear-gradient(60deg, rgb(255, 0, 0) 0%, rgb(120, 86, 255) 100%);background-blend-mode: overlay, overlay, difference, difference, difference, normal; }',
    'js' => NULL,
  ),
  43 => 
  array (
    'name' => 'Abstract Blend 03',
    'slug' => 'classic-abstract-03',
    'preview_color' => 'linear-gradient(135deg, #000000, #00C508)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-03 { position:fixed; inset:0; z-index:-1; background: linear-gradient(115deg, #000000 0%, #00C508 55%, #000000 100%), linear-gradient(115deg, #0057FF 0%, #020077 100%), conic-gradient(from 110deg at -5% 35%, #000000 0deg, #FAFF00 360deg), conic-gradient(from 220deg at 30% 30%, #FF0000 0deg, #0000FF 220deg, #240060 360deg), conic-gradient(from 235deg at 60% 35%, #0089D7 0deg, #0000FF 180deg, #240060 360deg);background-blend-mode: soft-light, soft-light, overlay, screen, normal; }',
    'js' => NULL,
  ),
  44 => 
  array (
    'name' => 'Abstract Blend 04',
    'slug' => 'classic-abstract-04',
    'preview_color' => 'linear-gradient(135deg, #FFB7B7, #727272)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-04 { position:fixed; inset:0; z-index:-1; background: linear-gradient(180deg, #FFB7B7 0%, #727272 100%), radial-gradient(60.91% 100% at 50% 0%, #FFD1D1 0%, #260000 100%), linear-gradient(238.72deg, #FFDDDD 0%, #720066 100%), linear-gradient(127.43deg, #00FFFF 0%, #FF4444 100%), radial-gradient(100.22% 100% at 70.57% 0%, #FF0000 0%, #00FFE0 100%), linear-gradient(127.43deg, #B7D500 0%, #3300FF 100%);background-blend-mode: screen, overlay, hard-light, color-burn, color-dodge, normal; }',
    'js' => NULL,
  ),
  45 => 
  array (
    'name' => 'Abstract Blend 05',
    'slug' => 'classic-abstract-05',
    'preview_color' => 'linear-gradient(135deg, #DE3E3E, #17115C)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-05 { position:fixed; inset:0; z-index:-1; background: radial-gradient(100% 225% at 0% 0%, #DE3E3E 0%, #17115C 100%), radial-gradient(100% 225% at 100% 0%, #FF9040 0%, #FF0000 100%), linear-gradient(180deg, #CE63B7 0%, #ED6283 100%), radial-gradient(100% 120% at 75% 0%, #A74600 0%, #000000 100%), linear-gradient(310deg, #0063D8 0%, #16009A 50%);background-blend-mode: overlay, color-dodge, color-burn, color-dodge, normal; }',
    'js' => NULL,
  ),
  46 => 
  array (
    'name' => 'Abstract Blend 06',
    'slug' => 'classic-abstract-06',
    'preview_color' => 'linear-gradient(135deg, #FF0000, #2400FF)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-06 { position:fixed; inset:0; z-index:-1; background: linear-gradient(120deg, #FF0000 0%, #2400FF 100%), linear-gradient(120deg, #FA00FF 0%, #208200 100%), linear-gradient(130deg, #00F0FF 0%, #000000 100%), radial-gradient(110% 140% at 15% 90%, #ffffff 0%, #1700A4 100%), radial-gradient(100% 100% at 50% 0%, #AD00FF 0%, #00FFE0 100%), radial-gradient(100% 100% at 50% 0%, #00FFE0 0%, #7300A9 80%), linear-gradient(30deg, #7ca304 0%, #2200AA 100%);background-blend-mode: overlay, color, overlay, difference, color-dodge, difference, normal; }',
    'js' => NULL,
  ),
  47 => 
  array (
    'name' => 'Abstract Blend 07',
    'slug' => 'classic-abstract-07',
    'preview_color' => 'linear-gradient(135deg, #0C003C, #BFFFAF)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-07 { position:fixed; inset:0; z-index:-1; background: linear-gradient(180deg, #0C003C 0%, #BFFFAF 100%), linear-gradient(165deg, #480045 25%, #E9EAAF 100%), linear-gradient(145deg, #480045 25%, #E9EAAF 100%), linear-gradient(300deg, rgba(233, 223, 255, 0) 0%, #AF89FF 100%), linear-gradient(90deg, #45EBA5 0%, #45EBA5 30%, #21ABA5 30%, #21ABA5 60%, #1D566E 60%, #1D566E 70%, #163A5F 70%, #163A5F 100%);background-blend-mode: overlay, overlay, overlay, multiply, normal; }',
    'js' => NULL,
  ),
  48 => 
  array (
    'name' => 'Abstract Blend 08',
    'slug' => 'classic-abstract-08',
    'preview_color' => 'linear-gradient(135deg, #BABC4A, #000000)',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-08 { position:fixed; inset:0; z-index:-1; background: linear-gradient(235deg, #BABC4A 0%, #000000 100%), linear-gradient(235deg, #0026AC 0%, #282534 100%), linear-gradient(235deg, #00FFD1 0%, #000000 100%), radial-gradient(120% 185% at 25% -25%, #EEEEEE 0%, #EEEEEE 40%, #7971EA calc(40% + 1px), #7971EA 50%, #393E46 calc(50% + 1px), #393E46 70%, #222831 calc(70% + 1px), #222831 100%), radial-gradient(70% 140% at 90% 10%, #F5F5C6 0%, #F5F5C6 30%, #7DA87B calc(30% + 1px), #7DA87B 60%, #326765 calc(60% + 1px), #326765 80%, #27253D calc(80% + 1px), #27253D 100%);background-blend-mode: overlay, lighten, overlay, color-burn, normal; }',
    'js' => NULL,
  ),
  49 => 
  array (
    'name' => 'Abstract Blend 09',
    'slug' => 'classic-abstract-09',
    'preview_color' => 'linear-gradient(135deg, rgb(214, 214, 214), rgb(214, 214, 214))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-09 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(112.5deg, rgb(214, 214, 214) 0%, rgb(214, 214, 214) 10%,rgb(195, 195, 195) 10%, rgb(195, 195, 195) 53%,rgb(176, 176, 176) 53%, rgb(176, 176, 176) 55%,rgb(157, 157, 157) 55%, rgb(157, 157, 157) 60%,rgb(137, 137, 137) 60%, rgb(137, 137, 137) 88%,rgb(118, 118, 118) 88%, rgb(118, 118, 118) 91%,rgb(99, 99, 99) 91%, rgb(99, 99, 99) 100%),linear-gradient(157.5deg, rgb(214, 214, 214) 0%, rgb(214, 214, 214) 10%,rgb(195, 195, 195) 10%, rgb(195, 195, 195) 53%,rgb(176, 176, 176) 53%, rgb(176, 176, 176) 55%,rgb(157, 157, 157) 55%, rgb(157, 157, 157) 60%,rgb(137, 137, 137) 60%, rgb(137, 137, 137) 88%,rgb(118, 118, 118) 88%, rgb(118, 118, 118) 91%,rgb(99, 99, 99) 91%, rgb(99, 99, 99) 100%),linear-gradient(135deg, rgb(214, 214, 214) 0%, rgb(214, 214, 214) 10%,rgb(195, 195, 195) 10%, rgb(195, 195, 195) 53%,rgb(176, 176, 176) 53%, rgb(176, 176, 176) 55%,rgb(157, 157, 157) 55%, rgb(157, 157, 157) 60%,rgb(137, 137, 137) 60%, rgb(137, 137, 137) 88%,rgb(118, 118, 118) 88%, rgb(118, 118, 118) 91%,rgb(99, 99, 99) 91%, rgb(99, 99, 99) 100%),linear-gradient(90deg, rgb(195, 195, 195),rgb(228, 228, 228)); background-blend-mode:overlay,overlay,overlay,normal; }',
    'js' => NULL,
  ),
  50 => 
  array (
    'name' => 'Abstract Blend 10',
    'slug' => 'classic-abstract-10',
    'preview_color' => 'linear-gradient(135deg, rgba(217, 217, 217,0.05), rgba(217, 217, 217,0.05))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-10 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgba(217, 217, 217,0.05) 0%, rgba(217, 217, 217,0.05) 15%,rgba(197, 197, 197,0.05) 15%, rgba(197, 197, 197,0.05) 34%,rgba(178, 178, 178,0.05) 34%, rgba(178, 178, 178,0.05) 51%,rgba(237, 237, 237,0.05) 51%, rgba(237, 237, 237,0.05) 75%,rgba(138, 138, 138,0.05) 75%, rgba(138, 138, 138,0.05) 89%,rgba(158, 158, 158,0.05) 89%, rgba(158, 158, 158,0.05) 100%),radial-gradient(circle at center center, rgb(255,255,255) 0%, rgb(255,255,255) 6%,rgb(255,255,255) 6%, rgb(255,255,255) 12%,rgb(255,255,255) 12%, rgb(255,255,255) 31%,rgb(255,255,255) 31%, rgb(255,255,255) 92%,rgb(255,255,255) 92%, rgb(255,255,255) 97%,rgb(255,255,255) 97%, rgb(255,255,255) 100%); background-size: 42px 42px; }',
    'js' => NULL,
  ),
  51 => 
  array (
    'name' => 'Abstract Blend 11',
    'slug' => 'classic-abstract-11',
    'preview_color' => 'linear-gradient(135deg, rgba(140, 140, 140,0.03), rgba(140, 140, 140,0.03))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-11 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 13% 47%, rgba(140, 140, 140,0.03) 0%, rgba(140, 140, 140,0.03) 25%,transparent 25%, transparent 100%),radial-gradient(circle at 28% 63%, rgba(143, 143, 143,0.03) 0%, rgba(143, 143, 143,0.03) 16%,transparent 16%, transparent 100%),radial-gradient(circle at 81% 56%, rgba(65, 65, 65,0.03) 0%, rgba(65, 65, 65,0.03) 12%,transparent 12%, transparent 100%),radial-gradient(circle at 26% 48%, rgba(60, 60, 60,0.03) 0%, rgba(60, 60, 60,0.03) 6%,transparent 6%, transparent 100%),radial-gradient(circle at 97% 17%, rgba(150, 150, 150,0.03) 0%, rgba(150, 150, 150,0.03) 56%,transparent 56%, transparent 100%),radial-gradient(circle at 50% 100%, rgba(25, 25, 25,0.03) 0%, rgba(25, 25, 25,0.03) 36%,transparent 36%, transparent 100%),radial-gradient(circle at 55% 52%, rgba(69, 69, 69,0.03) 0%, rgba(69, 69, 69,0.03) 6%,transparent 6%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  52 => 
  array (
    'name' => 'Abstract Blend 12',
    'slug' => 'classic-abstract-12',
    'preview_color' => 'linear-gradient(135deg, rgba(104, 104, 104,0.05), rgba(104, 104, 104,0.05))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-12 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, transparent 0%, transparent 58%,rgba(104, 104, 104,0.05) 58%, rgba(104, 104, 104,0.05) 92%,transparent 92%, transparent 100%),linear-gradient(45deg, transparent 0%, transparent 34%,rgba(104, 104, 104,0.05) 34%, rgba(104, 104, 104,0.05) 77%,transparent 77%, transparent 100%),linear-gradient(0deg, transparent 0%, transparent 33%,rgba(104, 104, 104,0.05) 33%, rgba(104, 104, 104,0.05) 53%,transparent 53%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  53 => 
  array (
    'name' => 'Abstract Blend 13',
    'slug' => 'classic-abstract-13',
    'preview_color' => 'linear-gradient(135deg, rgba(33,33,33,0), rgb(33,33,33))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-13 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgba(33,33,33,0),rgb(33,33,33)),repeating-linear-gradient(135deg, rgb(33,33,33) 0px, rgb(33,33,33) 1px,transparent 1px, transparent 4px),repeating-linear-gradient(45deg, rgb(56,56,56) 0px, rgb(56,56,56) 5px,transparent 5px, transparent 6px),linear-gradient(90deg, rgb(33,33,33),rgb(33,33,33)); }',
    'js' => NULL,
  ),
  54 => 
  array (
    'name' => 'Abstract Blend 14',
    'slug' => 'classic-abstract-14',
    'preview_color' => 'linear-gradient(135deg, rgba(187, 187, 187,0.04), rgba(187, 187, 187,0.04))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-14 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgba(187, 187, 187,0.04) 0%, rgba(187, 187, 187,0.04) 50%,rgba(86, 86, 86,0.04) 50%, rgba(86, 86, 86,0.04) 100%),linear-gradient(135deg, rgba(166, 166, 166,0.04) 0%, rgba(166, 166, 166,0.04) 50%,rgba(92, 92, 92,0.04) 50%, rgba(92, 92, 92,0.04) 100%),linear-gradient(90deg, rgb(20,20,20),rgb(20,20,20)); background-size: 142px 142px; }',
    'js' => NULL,
  ),
  55 => 
  array (
    'name' => 'Abstract Blend 15',
    'slug' => 'classic-abstract-15',
    'preview_color' => 'linear-gradient(135deg, rgba(214, 214, 214,0.06), rgba(214, 214, 214,0.06))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-15 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, transparent 0%, transparent 27%,rgba(214, 214, 214,0.06) 27%, rgba(214, 214, 214,0.06) 38%,transparent 38%, transparent 100%),linear-gradient(45deg, transparent 0%, transparent 39%,rgba(214, 214, 214,0.06) 39%, rgba(214, 214, 214,0.06) 68%,transparent 68%, transparent 100%),linear-gradient(90deg, transparent 0%, transparent 74%,rgba(214, 214, 214,0.06) 74%, rgba(214, 214, 214,0.06) 79%,transparent 79%, transparent 100%),linear-gradient(90deg, rgb(0,0,0),rgb(0,0,0)); }',
    'js' => NULL,
  ),
  56 => 
  array (
    'name' => 'Abstract Blend 16',
    'slug' => 'classic-abstract-16',
    'preview_color' => 'linear-gradient(135deg, rgb(3, 3, 3), rgb(3, 3, 3))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-16 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(90deg, rgb(3, 3, 3) 0px, rgb(3, 3, 3) 18px,rgb(11, 11, 11) 18px, rgb(11, 11, 11) 36px,rgb(0, 0, 0) 36px, rgb(0, 0, 0) 54px,rgb(5, 5, 5) 54px, rgb(5, 5, 5) 72px,rgb(8, 8, 8) 72px, rgb(8, 8, 8) 90px,rgb(14, 14, 14) 90px, rgb(14, 14, 14) 108px,rgb(19, 19, 19) 108px, rgb(19, 19, 19) 126px,rgb(16, 16, 16) 126px, rgb(16, 16, 16) 144px); }',
    'js' => NULL,
  ),
  57 => 
  array (
    'name' => 'Abstract Blend 17',
    'slug' => 'classic-abstract-17',
    'preview_color' => 'linear-gradient(135deg, hsla(329,0%,99%,0.05), hsla(329,0%,99%,0.05))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-17 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 29% 55%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 4%,transparent 4%, transparent 44%,transparent 44%, transparent 100%),radial-gradient(circle at 85% 89%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 51%,transparent 51%, transparent 52%,transparent 52%, transparent 100%),radial-gradient(circle at 6% 90%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 53%,transparent 53%, transparent 64%,transparent 64%, transparent 100%),radial-gradient(circle at 35% 75%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 6%,transparent 6%, transparent 98%,transparent 98%, transparent 100%),radial-gradient(circle at 56% 75%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 16%,transparent 16%, transparent 23%,transparent 23%, transparent 100%),radial-gradient(circle at 42% 0%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 3%,transparent 3%, transparent 26%,transparent 26%, transparent 100%),radial-gradient(circle at 29% 28%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 51%,transparent 51%, transparent 75%,transparent 75%, transparent 100%),radial-gradient(circle at 77% 21%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 35%,transparent 35%, transparent 55%,transparent 55%, transparent 100%),radial-gradient(circle at 65% 91%, hsla(329,0%,99%,0.05) 0%, hsla(329,0%,99%,0.05) 46%,transparent 46%, transparent 76%,transparent 76%, transparent 100%),linear-gradient(45deg, rgb(83, 91, 235),rgb(76, 11, 174)); }',
    'js' => NULL,
  ),
  58 => 
  array (
    'name' => 'Abstract Blend 18',
    'slug' => 'classic-abstract-18',
    'preview_color' => 'linear-gradient(135deg, hsla(317,0%,96%,0.05), hsla(317,0%,96%,0.05))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-18 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 67% 83%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 1%,transparent 1%, transparent 5%,transparent 5%, transparent 100%),radial-gradient(circle at 24% 80%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 27%,transparent 27%, transparent 63%,transparent 63%, transparent 100%),radial-gradient(circle at 23% 5%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 26%,transparent 26%, transparent 82%,transparent 82%, transparent 100%),radial-gradient(circle at 21% 11%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 35%,transparent 35%, transparent 45%,transparent 45%, transparent 100%),radial-gradient(circle at 10% 11%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 21%,transparent 21%, transparent 81%,transparent 81%, transparent 100%),radial-gradient(circle at 19% 61%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 20%,transparent 20%, transparent 61%,transparent 61%, transparent 100%),radial-gradient(circle at 13% 77%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 63%,transparent 63%, transparent 72%,transparent 72%, transparent 100%),radial-gradient(circle at 30% 93%, hsla(317,0%,96%,0.05) 0%, hsla(317,0%,96%,0.05) 33%,transparent 33%, transparent 82%,transparent 82%, transparent 100%),linear-gradient(90deg, rgb(22, 176, 207),rgb(103, 7, 215)); }',
    'js' => NULL,
  ),
  59 => 
  array (
    'name' => 'Abstract Blend 19',
    'slug' => 'classic-abstract-19',
    'preview_color' => 'linear-gradient(135deg, rgba(228, 228, 228,0.06), rgba(228, 228, 228,0.06))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-19 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 46% 40%, rgba(228, 228, 228,0.06) 0%, rgba(228, 228, 228,0.06) 13%,transparent 13%, transparent 100%),radial-gradient(circle at 11% 41%, rgba(198, 198, 198,0.06) 0%, rgba(198, 198, 198,0.06) 19%,transparent 19%, transparent 100%),radial-gradient(circle at 52% 23%, rgba(14, 14, 14,0.06) 0%, rgba(14, 14, 14,0.06) 69%,transparent 69%, transparent 100%),radial-gradient(circle at 13% 85%, rgba(148, 148, 148,0.06) 0%, rgba(148, 148, 148,0.06) 44%,transparent 44%, transparent 100%),radial-gradient(circle at 57% 74%, rgba(232, 232, 232,0.06) 0%, rgba(232, 232, 232,0.06) 21%,transparent 21%, transparent 100%),radial-gradient(circle at 59% 54%, rgba(39, 39, 39,0.06) 0%, rgba(39, 39, 39,0.06) 49%,transparent 49%, transparent 100%),radial-gradient(circle at 98% 38%, rgba(157, 157, 157,0.06) 0%, rgba(157, 157, 157,0.06) 24%,transparent 24%, transparent 100%),radial-gradient(circle at 8% 6%, rgba(60, 60, 60,0.06) 0%, rgba(60, 60, 60,0.06) 12%,transparent 12%, transparent 100%),linear-gradient(90deg, rgb(148, 220, 10),rgb(18, 123, 10)); }',
    'js' => NULL,
  ),
  60 => 
  array (
    'name' => 'Abstract Blend 20',
    'slug' => 'classic-abstract-20',
    'preview_color' => 'linear-gradient(135deg, rgba(183, 183, 183,0.09), rgba(183, 183, 183,0.09))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-20 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 82% 63%, rgba(183, 183, 183,0.09) 0%, rgba(183, 183, 183,0.09) 84%,transparent 84%, transparent 100%),radial-gradient(circle at 88% 98%, rgba(232, 232, 232,0.07) 0%, rgba(232, 232, 232,0.07) 15%,transparent 15%, transparent 100%),radial-gradient(circle at 77% 83%, rgba(252, 252, 252,0.05) 0%, rgba(252, 252, 252,0.05) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 64% 0%, rgba(140, 140, 140,0.05) 0%, rgba(140, 140, 140,0.05) 54%,transparent 54%, transparent 100%),radial-gradient(circle at 57% 86%, rgba(241, 241, 241,0.07) 0%, rgba(241, 241, 241,0.07) 80%,transparent 80%, transparent 100%),radial-gradient(circle at 17% 93%, rgba(68, 68, 68,0.05) 0%, rgba(68, 68, 68,0.05) 82%,transparent 82%, transparent 100%),radial-gradient(circle at 85% 70%, rgba(10, 10, 10,0.02) 0%, rgba(10, 10, 10,0.02) 13%,transparent 13%, transparent 100%),linear-gradient(90deg, rgb(48, 62, 175),rgb(254, 18, 105)); }',
    'js' => NULL,
  ),
  61 => 
  array (
    'name' => 'Abstract Blend 21',
    'slug' => 'classic-abstract-21',
    'preview_color' => 'linear-gradient(135deg, hsla(193,0%,4%,0.06), hsla(193,0%,4%,0.06))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-21 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 68% 88%, hsla(193,0%,4%,0.06) 0%, hsla(193,0%,4%,0.06) 68%,transparent 68%, transparent 100%),radial-gradient(circle at 64% 21%, hsla(193,0%,4%,0.06) 0%, hsla(193,0%,4%,0.06) 45%,transparent 45%, transparent 100%),radial-gradient(circle at 18% 4%, hsla(193,0%,4%,0.06) 0%, hsla(193,0%,4%,0.06) 26%,transparent 26%, transparent 100%),radial-gradient(circle at 17% 69%, hsla(193,0%,4%,0.06) 0%, hsla(193,0%,4%,0.06) 15%,transparent 15%, transparent 100%),radial-gradient(circle at 84% 26%, hsla(193,0%,4%,0.06) 0%, hsla(193,0%,4%,0.06) 45%,transparent 45%, transparent 100%),linear-gradient(45deg, rgb(181, 73, 30),rgb(187, 114, 141)); }',
    'js' => NULL,
  ),
  62 => 
  array (
    'name' => 'Abstract Blend 22',
    'slug' => 'classic-abstract-22',
    'preview_color' => 'linear-gradient(135deg, hsla(165,0%,91%,0.05), hsla(165,0%,91%,0.05))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-22 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 52% 37%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 15%,transparent 15%, transparent 65%,transparent 65%, transparent 100%),radial-gradient(circle at 70% 3%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 33%,transparent 33%, transparent 62%,transparent 62%, transparent 100%),radial-gradient(circle at 38% 28%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 15%,transparent 15%, transparent 94%,transparent 94%, transparent 100%),radial-gradient(circle at 12% 92%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 1%,transparent 1%, transparent 19%,transparent 19%, transparent 100%),radial-gradient(circle at 50% 84%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 28%,transparent 28%, transparent 96%,transparent 96%, transparent 100%),radial-gradient(circle at 11% 43%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 20%,transparent 20%, transparent 63%,transparent 63%, transparent 100%),radial-gradient(circle at 45% 11%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 19%,transparent 19%, transparent 65%,transparent 65%, transparent 100%),radial-gradient(circle at 90% 54%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 11%,transparent 11%, transparent 19%,transparent 19%, transparent 100%),radial-gradient(circle at 23% 100%, hsla(165,0%,91%,0.05) 0%, hsla(165,0%,91%,0.05) 35%,transparent 35%, transparent 86%,transparent 86%, transparent 100%),linear-gradient(0deg, rgb(95, 19, 0),rgb(187, 23, 4)); }',
    'js' => NULL,
  ),
  63 => 
  array (
    'name' => 'Abstract Blend 23',
    'slug' => 'classic-abstract-23',
    'preview_color' => 'linear-gradient(135deg, hsla(150,0%,8%,0.07), hsla(150,0%,8%,0.07))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-23 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 35% 14%, hsla(150,0%,8%,0.07) 0%, hsla(150,0%,8%,0.07) 39%,transparent 39%, transparent 90%,transparent 90%, transparent 100%),radial-gradient(circle at 76% 4%, hsla(150,0%,8%,0.07) 0%, hsla(150,0%,8%,0.07) 83%,transparent 83%, transparent 89%,transparent 89%, transparent 100%),radial-gradient(circle at 20% 33%, hsla(150,0%,8%,0.07) 0%, hsla(150,0%,8%,0.07) 83%,transparent 83%, transparent 90%,transparent 90%, transparent 100%),radial-gradient(circle at 44% 74%, hsla(150,0%,8%,0.07) 0%, hsla(150,0%,8%,0.07) 73%,transparent 73%, transparent 89%,transparent 89%, transparent 100%),radial-gradient(circle at 26% 31%, hsla(150,0%,8%,0.07) 0%, hsla(150,0%,8%,0.07) 44%,transparent 44%, transparent 75%,transparent 75%, transparent 100%),linear-gradient(0deg, rgb(148, 28, 74),rgb(238, 52, 93)); }',
    'js' => NULL,
  ),
  64 => 
  array (
    'name' => 'Abstract Blend 24',
    'slug' => 'classic-abstract-24',
    'preview_color' => 'linear-gradient(135deg, hsla(280,0%,87%,0.1), hsla(280,0%,87%,0.1))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-24 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 29% 25%, hsla(280,0%,87%,0.1) 0%, hsla(280,0%,87%,0.1) 54%,transparent 54%, transparent 89%,transparent 89%, transparent 100%),radial-gradient(circle at 35% 35%, hsla(280,0%,87%,0.1) 0%, hsla(280,0%,87%,0.1) 2%,transparent 2%, transparent 48%,transparent 48%, transparent 100%),radial-gradient(circle at 47% 56%, hsla(280,0%,87%,0.1) 0%, hsla(280,0%,87%,0.1) 43%,transparent 43%, transparent 76%,transparent 76%, transparent 100%),radial-gradient(circle at 48% 84%, hsla(280,0%,87%,0.1) 0%, hsla(280,0%,87%,0.1) 64%,transparent 64%, transparent 84%,transparent 84%, transparent 100%),radial-gradient(circle at 31% 76%, hsla(280,0%,87%,0.1) 0%, hsla(280,0%,87%,0.1) 45%,transparent 45%, transparent 86%,transparent 86%, transparent 100%),linear-gradient(135deg, rgb(2, 39, 217),rgb(100, 100, 207)); }',
    'js' => NULL,
  ),
  65 => 
  array (
    'name' => 'Abstract Blend 25',
    'slug' => 'classic-abstract-25',
    'preview_color' => 'linear-gradient(135deg, rgba(203, 74, 233, 0.36), rgba(203, 74, 233, 0.36))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-25 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgba(203, 74, 233, 0.36) 0%, rgba(203, 74, 233, 0.36) 23%,rgba(229, 51, 239, 0.36) 23%, rgba(229, 51, 239, 0.36) 26%,rgba(151, 120, 220, 0.36) 26%, rgba(151, 120, 220, 0.36) 30%,rgba(73, 189, 202, 0.36) 30%, rgba(73, 189, 202, 0.36) 33%,rgba(99, 166, 208, 0.36) 33%, rgba(99, 166, 208, 0.36) 37%,rgba(255, 28, 245, 0.36) 37%, rgba(255, 28, 245, 0.36) 61%,rgba(177, 97, 227, 0.36) 61%, rgba(177, 97, 227, 0.36) 63%,rgba(125, 143, 214, 0.36) 63%, rgba(125, 143, 214, 0.36) 100%),linear-gradient(45deg, rgba(21, 202, 234, 0.37) 0%, rgba(21, 202, 234, 0.37) 12.5%,rgba(31, 119, 182, 0.37) 12.5%, rgba(31, 119, 182, 0.37) 25%,rgba(28, 147, 199, 0.37) 25%, rgba(28, 147, 199, 0.37) 37.5%,rgba(38, 64, 147, 0.37) 37.5%, rgba(38, 64, 147, 0.37) 50%,rgba(41, 37, 130, 0.37) 50%, rgba(41, 37, 130, 0.37) 62.5%,rgba(18, 229, 251, 0.37) 62.5%, rgba(18, 229, 251, 0.37) 75%,rgba(25, 174, 216, 0.37) 75%, rgba(25, 174, 216, 0.37) 87.5%,rgba(34, 92, 165, 0.37) 87.5%, rgba(34, 92, 165, 0.37) 100%),linear-gradient(248deg, rgb(197, 70, 212),rgb(40, 42, 210)); }',
    'js' => NULL,
  ),
  66 => 
  array (
    'name' => 'Abstract Blend 26',
    'slug' => 'classic-abstract-26',
    'preview_color' => 'linear-gradient(135deg, rgba(196, 196, 196, 0.05), rgba(196, 196, 196, 0.05))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-26 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(196, 196, 196, 0.05) 0%, rgba(196, 196, 196, 0.05) 10%,rgba(245, 245, 245, 0.05) 10%, rgba(245, 245, 245, 0.05) 23%,rgba(72, 72, 72, 0.05) 23%, rgba(72, 72, 72, 0.05) 27%,rgba(231, 231, 231, 0.05) 27%, rgba(231, 231, 231, 0.05) 28%,rgba(192, 192, 192, 0.05) 28%, rgba(192, 192, 192, 0.05) 39%,rgba(105, 105, 105, 0.05) 39%, rgba(105, 105, 105, 0.05) 47%,rgba(216, 216, 216, 0.05) 47%, rgba(216, 216, 216, 0.05) 100%),linear-gradient(90deg, rgba(98, 98, 98, 0.07) 0%, rgba(98, 98, 98, 0.07) 6%,rgba(13, 13, 13, 0.07) 6%, rgba(13, 13, 13, 0.07) 10%,rgba(31, 31, 31, 0.07) 10%, rgba(31, 31, 31, 0.07) 25%,rgba(2, 2, 2, 0.07) 25%, rgba(2, 2, 2, 0.07) 33%,rgba(170, 170, 170, 0.07) 33%, rgba(170, 170, 170, 0.07) 39%,rgba(203, 203, 203, 0.07) 39%, rgba(203, 203, 203, 0.07) 87%,rgba(254, 254, 254, 0.07) 87%, rgba(254, 254, 254, 0.07) 100%),linear-gradient(90deg, rgba(180, 180, 180, 0.03) 0%, rgba(180, 180, 180, 0.03) 10%,rgba(178, 178, 178, 0.03) 10%, rgba(178, 178, 178, 0.03) 15%,rgba(22, 22, 22, 0.03) 15%, rgba(22, 22, 22, 0.03) 27%,rgba(149, 149, 149, 0.03) 27%, rgba(149, 149, 149, 0.03) 32%,rgba(85, 85, 85, 0.03) 32%, rgba(85, 85, 85, 0.03) 45%,rgba(100, 100, 100, 0.03) 45%, rgba(100, 100, 100, 0.03) 57%,rgba(228, 228, 228, 0.03) 57%, rgba(228, 228, 228, 0.03) 65%,rgba(178, 178, 178, 0.03) 65%, rgba(178, 178, 178, 0.03) 100%),linear-gradient(90deg, rgba(239, 239, 239, 0.02) 0%, rgba(239, 239, 239, 0.02) 1%,rgba(192, 192, 192, 0.02) 1%, rgba(192, 192, 192, 0.02) 2%,rgba(151, 151, 151, 0.02) 2%, rgba(151, 151, 151, 0.02) 19%,rgba(62, 62, 62, 0.02) 19%, rgba(62, 62, 62, 0.02) 90%,rgba(143, 143, 143, 0.02) 90%, rgba(143, 143, 143, 0.02) 91%,rgba(137, 137, 137, 0.02) 91%, rgba(137, 137, 137, 0.02) 98%,rgba(216, 216, 216, 0.02) 98%, rgba(216, 216, 216, 0.02) 100%),linear-gradient(90deg, rgba(91, 91, 91, 0.02) 0%, rgba(91, 91, 91, 0.02) 5%,rgba(56, 56, 56, 0.02) 5%, rgba(56, 56, 56, 0.02) 30%,rgba(79, 79, 79, 0.02) 30%, rgba(79, 79, 79, 0.02) 73%,rgba(81, 81, 81, 0.02) 73%, rgba(81, 81, 81, 0.02) 92%,rgba(68, 68, 68, 0.02) 92%, rgba(68, 68, 68, 0.02) 100%),linear-gradient(90deg, rgba(60, 60, 60, 0.06) 0%, rgba(60, 60, 60, 0.06) 8%,rgba(21, 21, 21, 0.06) 8%, rgba(21, 21, 21, 0.06) 21%,rgba(127, 127, 127, 0.06) 21%, rgba(127, 127, 127, 0.06) 22%,rgba(250, 250, 250, 0.06) 22%, rgba(250, 250, 250, 0.06) 55%,rgba(119, 119, 119, 0.06) 55%, rgba(119, 119, 119, 0.06) 60%,rgba(120, 120, 120, 0.06) 60%, rgba(120, 120, 120, 0.06) 61%,rgba(222, 222, 222, 0.06) 61%, rgba(222, 222, 222, 0.06) 100%),linear-gradient(90deg, rgba(64, 64, 64, 0.07) 0%, rgba(64, 64, 64, 0.07) 18%,rgba(57, 57, 57, 0.07) 18%, rgba(57, 57, 57, 0.07) 21%,rgba(125, 125, 125, 0.07) 21%, rgba(125, 125, 125, 0.07) 26%,rgba(252, 252, 252, 0.07) 26%, rgba(252, 252, 252, 0.07) 40%,rgba(64, 64, 64, 0.07) 40%, rgba(64, 64, 64, 0.07) 47%,rgba(21, 21, 21, 0.07) 47%, rgba(21, 21, 21, 0.07) 67%,rgba(119, 119, 119, 0.07) 67%, rgba(119, 119, 119, 0.07) 100%),linear-gradient(90deg, rgb(17, 117, 250),rgb(248, 47, 141),rgb(121, 67, 171)); }',
    'js' => NULL,
  ),
  67 => 
  array (
    'name' => 'Abstract Blend 27',
    'slug' => 'classic-abstract-27',
    'preview_color' => 'linear-gradient(135deg, rgba(43, 43, 43, 0.06), rgba(43, 43, 43, 0.06))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-27 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(43, 43, 43, 0.06) 0%, rgba(43, 43, 43, 0.06) 5%,rgba(173, 173, 173, 0.06) 5%, rgba(173, 173, 173, 0.06) 37%,rgba(32, 32, 32, 0.06) 37%, rgba(32, 32, 32, 0.06) 90%,rgba(193, 193, 193, 0.06) 90%, rgba(193, 193, 193, 0.06) 100%),linear-gradient(90deg, rgba(56, 56, 56, 0.02) 0%, rgba(56, 56, 56, 0.02) 13%,rgba(224, 224, 224, 0.02) 13%, rgba(224, 224, 224, 0.02) 45%,rgba(211, 211, 211, 0.02) 45%, rgba(211, 211, 211, 0.02) 65%,rgba(89, 89, 89, 0.02) 65%, rgba(89, 89, 89, 0.02) 100%),linear-gradient(90deg, rgba(53, 53, 53, 0.04) 0%, rgba(53, 53, 53, 0.04) 1%,rgba(33, 33, 33, 0.04) 1%, rgba(33, 33, 33, 0.04) 23%,rgba(105, 105, 105, 0.04) 23%, rgba(105, 105, 105, 0.04) 41%,rgba(151, 151, 151, 0.04) 41%, rgba(151, 151, 151, 0.04) 72%,rgba(129, 129, 129, 0.04) 72%, rgba(129, 129, 129, 0.04) 100%),linear-gradient(90deg, rgba(254, 254, 254, 0.07) 0%, rgba(254, 254, 254, 0.07) 11%,rgba(223, 223, 223, 0.07) 11%, rgba(223, 223, 223, 0.07) 16%,rgba(108, 108, 108, 0.07) 16%, rgba(108, 108, 108, 0.07) 31%,rgba(224, 224, 224, 0.07) 31%, rgba(224, 224, 224, 0.07) 64%,rgba(66, 66, 66, 0.07) 64%, rgba(66, 66, 66, 0.07) 100%),linear-gradient(90deg, rgba(26, 26, 26, 0.02) 0%, rgba(26, 26, 26, 0.02) 30%,rgba(137, 137, 137, 0.02) 30%, rgba(137, 137, 137, 0.02) 46%,rgba(63, 63, 63, 0.02) 46%, rgba(63, 63, 63, 0.02) 63%,rgba(245, 245, 245, 0.02) 63%, rgba(245, 245, 245, 0.02) 100%),linear-gradient(90deg, rgb(196, 68, 141),rgb(243, 219, 77)); }',
    'js' => NULL,
  ),
  68 => 
  array (
    'name' => 'Abstract Blend 28',
    'slug' => 'classic-abstract-28',
    'preview_color' => 'linear-gradient(135deg, rgba(231, 231, 231, 0.08), rgba(231, 231, 231, 0.08))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-28 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(231, 231, 231, 0.08) 0%, rgba(231, 231, 231, 0.08) 14%,rgba(224, 224, 224, 0.08) 14%, rgba(224, 224, 224, 0.08) 51%,rgba(140, 140, 140, 0.08) 51%, rgba(140, 140, 140, 0.08) 100%),linear-gradient(90deg, rgba(244, 244, 244, 0.09) 0%, rgba(244, 244, 244, 0.09) 21%,rgba(158, 158, 158, 0.09) 21%, rgba(158, 158, 158, 0.09) 31%,rgba(162, 162, 162, 0.09) 31%, rgba(162, 162, 162, 0.09) 89%,rgba(115, 115, 115, 0.09) 89%, rgba(115, 115, 115, 0.09) 100%),linear-gradient(90deg, rgb(7, 243, 201),rgb(51, 74, 207)); }',
    'js' => NULL,
  ),
  69 => 
  array (
    'name' => 'Abstract Blend 29',
    'slug' => 'classic-abstract-29',
    'preview_color' => 'linear-gradient(135deg, rgba(24, 155, 227,0.5), rgba(24, 155, 227,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-29 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgba(24, 155, 227,0.5) 0%, rgba(24, 155, 227,0.5) 4%,transparent 4%, transparent 16%,rgba(13, 183, 231,0.5) 16%, rgba(13, 183, 231,0.5) 87%,rgba(44, 99, 220,0.5) 87%, rgba(44, 99, 220,0.5) 100%),linear-gradient(90deg, rgb(24, 155, 227) 0%, rgb(24, 155, 227) 72%,rgb(55, 71, 216) 72%, rgb(55, 71, 216) 77%,transparent 77%, transparent 85%,rgb(44, 99, 220) 85%, rgb(44, 99, 220) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  70 => 
  array (
    'name' => 'Abstract Blend 30',
    'slug' => 'classic-abstract-30',
    'preview_color' => 'linear-gradient(135deg, rgba(3, 121, 241,0.5), rgba(3, 121, 241,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-30 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgba(3, 121, 241,0.5) 0%, rgba(3, 121, 241,0.5) 9%,transparent 9%, transparent 16%,rgba(11, 75, 199,0.5) 16%, rgba(11, 75, 199,0.5) 49%,rgba(9, 86, 209,0.5) 49%, rgba(9, 86, 209,0.5) 100%),linear-gradient(135deg, rgb(3, 121, 241) 0%, rgb(3, 121, 241) 31%,rgb(13, 63, 188) 31%, rgb(13, 63, 188) 37%,transparent 37%, transparent 56%,rgb(9, 86, 209) 56%, rgb(9, 86, 209) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  71 => 
  array (
    'name' => 'Abstract Blend 31',
    'slug' => 'classic-abstract-31',
    'preview_color' => 'linear-gradient(135deg, rgba(7, 130, 103,0.5), rgba(7, 130, 103,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-31 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgba(7, 130, 103,0.5) 0%, rgba(7, 130, 103,0.5) 3%,transparent 3%, transparent 27%,rgba(43, 209, 74,0.5) 27%, rgba(43, 209, 74,0.5) 41%,rgba(21, 162, 91,0.5) 41%, rgba(21, 162, 91,0.5) 100%),linear-gradient(90deg, rgb(7, 130, 103) 0%, rgb(7, 130, 103) 37%,rgb(14, 146, 97) 37%, rgb(14, 146, 97) 56%,transparent 56%, transparent 61%,rgb(21, 162, 91) 61%, rgb(21, 162, 91) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  72 => 
  array (
    'name' => 'Abstract Blend 32',
    'slug' => 'classic-abstract-32',
    'preview_color' => 'linear-gradient(135deg, rgba(187, 31, 80,0.5), rgba(187, 31, 80,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-32 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgba(187, 31, 80,0.5) 0%, rgba(187, 31, 80,0.5) 34%,transparent 34%, transparent 53%,rgba(119, 9, 85,0.5) 53%, rgba(119, 9, 85,0.5) 61%,rgba(102, 3, 86,0.5) 61%, rgba(102, 3, 86,0.5) 76%,rgba(170, 26, 81,0.5) 76%, rgba(170, 26, 81,0.5) 84%,rgba(153, 20, 83,0.5) 84%, rgba(153, 20, 83,0.5) 100%),linear-gradient(90deg, rgb(187, 31, 80) 0%, rgb(187, 31, 80) 15%,transparent 15%, transparent 18%,transparent 18%, transparent 20%,rgb(102, 3, 86) 20%, rgb(102, 3, 86) 41%,rgb(170, 26, 81) 41%, rgb(170, 26, 81) 49%,rgb(153, 20, 83) 49%, rgb(153, 20, 83) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  73 => 
  array (
    'name' => 'Abstract Blend 33',
    'slug' => 'classic-abstract-33',
    'preview_color' => 'linear-gradient(135deg, rgba(203, 78, 191,0.5), rgba(203, 78, 191,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-33 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgba(203, 78, 191,0.5) 0%, rgba(203, 78, 191,0.5) 12%,transparent 12%, transparent 20%,rgba(225, 118, 209,0.5) 20%, rgba(225, 118, 209,0.5) 24%,rgba(236, 138, 217,0.5) 24%, rgba(236, 138, 217,0.5) 35%,rgba(192, 58, 183,0.5) 35%, rgba(192, 58, 183,0.5) 36%,rgba(214, 98, 200,0.5) 36%, rgba(214, 98, 200,0.5) 100%),linear-gradient(135deg, rgb(203, 78, 191) 0%, rgb(203, 78, 191) 11%,transparent 11%, transparent 23%,transparent 23%, transparent 33%,rgb(236, 138, 217) 33%, rgb(236, 138, 217) 64%,rgb(192, 58, 183) 64%, rgb(192, 58, 183) 83%,rgb(214, 98, 200) 83%, rgb(214, 98, 200) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  74 => 
  array (
    'name' => 'Abstract Blend 34',
    'slug' => 'classic-abstract-34',
    'preview_color' => 'linear-gradient(135deg, rgba(243, 245, 245,0.5), rgba(243, 245, 245,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-34 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(243, 245, 245,0.5) 0%, rgba(243, 245, 245,0.5) 6%,transparent 6%, transparent 17%,rgba(209, 236, 244,0.5) 17%, rgba(209, 236, 244,0.5) 29%,rgba(39, 189, 241,0.5) 29%, rgba(39, 189, 241,0.5) 33%,transparent 33%, transparent 57%,rgba(175, 226, 244,0.5) 57%, rgba(175, 226, 244,0.5) 70%,rgba(107, 208, 242,0.5) 70%, rgba(107, 208, 242,0.5) 100%),linear-gradient(0deg, rgb(243, 245, 245) 0%, rgb(243, 245, 245) 18%,transparent 18%, transparent 35%,transparent 35%, transparent 40%,rgb(39, 189, 241) 40%, rgb(39, 189, 241) 71%,rgb(141, 217, 243) 71%, rgb(141, 217, 243) 79%,rgb(175, 226, 244) 79%, rgb(175, 226, 244) 92%,rgb(107, 208, 242) 92%, rgb(107, 208, 242) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  75 => 
  array (
    'name' => 'Abstract Blend 35',
    'slug' => 'classic-abstract-35',
    'preview_color' => 'linear-gradient(135deg, rgba(51, 26, 91,0.5), rgba(51, 26, 91,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-35 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgba(51, 26, 91,0.5) 0%, rgba(51, 26, 91,0.5) 20%,transparent 20%, transparent 25%,transparent 25%, transparent 79%,rgba(76, 48, 115,0.5) 79%, rgba(76, 48, 115,0.5) 100%),linear-gradient(90deg, rgb(51, 26, 91) 0%, rgb(51, 26, 91) 53%,transparent 53%, transparent 54%,rgb(127, 92, 163) 54%, rgb(127, 92, 163) 89%,rgb(76, 48, 115) 89%, rgb(76, 48, 115) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  76 => 
  array (
    'name' => 'Abstract Blend 36',
    'slug' => 'classic-abstract-36',
    'preview_color' => 'linear-gradient(135deg, rgba(42, 154, 181,0.5), rgba(42, 154, 181,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-36 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(42, 154, 181,0.5) 0%, rgba(42, 154, 181,0.5) 16%,transparent 16%, transparent 36%,transparent 36%, transparent 86%,rgba(42, 107, 139,0.5) 86%, rgba(42, 107, 139,0.5) 100%),linear-gradient(0deg, rgb(42, 154, 181) 0%, rgb(42, 154, 181) 38%,transparent 38%, transparent 47%,rgb(41, 201, 223) 47%, rgb(41, 201, 223) 50%,rgb(42, 107, 139) 50%, rgb(42, 107, 139) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  77 => 
  array (
    'name' => 'Abstract Blend 37',
    'slug' => 'classic-abstract-37',
    'preview_color' => 'linear-gradient(135deg, rgba(254, 177, 8,0.5), rgba(254, 177, 8,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-37 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgba(254, 177, 8,0.5) 0%, rgba(254, 177, 8,0.5) 19%,transparent 19%, transparent 43%,transparent 43%, transparent 55%,rgba(228, 150, 33,0.5) 55%, rgba(228, 150, 33,0.5) 100%),linear-gradient(135deg, rgb(254, 177, 8) 0%, rgb(254, 177, 8) 2%,transparent 2%, transparent 19%,rgb(203, 123, 58) 19%, rgb(203, 123, 58) 57%,rgb(228, 150, 33) 57%, rgb(228, 150, 33) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  78 => 
  array (
    'name' => 'Abstract Blend 38',
    'slug' => 'classic-abstract-38',
    'preview_color' => 'linear-gradient(135deg, rgba(231, 84, 216,0.5), rgba(231, 84, 216,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-38 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, transparent 0%, transparent 30%,rgba(231, 84, 216,0.5) 30%, rgba(231, 84, 216,0.5) 58%,transparent 58%, transparent 72%,rgba(122, 77, 232,0.5) 72%, rgba(122, 77, 232,0.5) 100%),linear-gradient(0deg, rgb(67, 74, 240) 0%, rgb(67, 74, 240) 33%,rgb(231, 84, 216) 33%, rgb(231, 84, 216) 47%,transparent 47%, transparent 69%,rgb(122, 77, 232) 69%, rgb(122, 77, 232) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  79 => 
  array (
    'name' => 'Abstract Blend 39',
    'slug' => 'classic-abstract-39',
    'preview_color' => 'linear-gradient(135deg, rgba(212, 215, 38,0.6), rgba(212, 215, 38,0.6))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-39 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, rgba(212, 215, 38,0.6) 0%, rgba(212, 215, 38,0.6) 11%,transparent 11%, transparent 22%,rgba(223, 157, 64,0.6) 22%, rgba(223, 157, 64,0.6) 59%,transparent 59%, transparent 76%,rgba(235, 99, 89,0.6) 76%, rgba(235, 99, 89,0.6) 100%),linear-gradient(45deg, transparent 0%, transparent 23%,rgb(218, 186, 51) 23%, rgb(218, 186, 51) 34%,rgb(223, 157, 64) 34%, rgb(223, 157, 64) 57%,transparent 57%, transparent 58%,rgb(235, 99, 89) 58%, rgb(235, 99, 89) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  80 => 
  array (
    'name' => 'Abstract Blend 40',
    'slug' => 'classic-abstract-40',
    'preview_color' => 'linear-gradient(135deg, rgba(205, 92, 179,0.6), rgba(205, 92, 179,0.6))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-40 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, transparent 0%, transparent 45%,rgba(205, 92, 179,0.6) 45%, rgba(205, 92, 179,0.6) 89%,transparent 89%, transparent 91%,rgba(172, 89, 159,0.6) 91%, rgba(172, 89, 159,0.6) 100%),linear-gradient(45deg, transparent 0%, transparent 2%,rgb(205, 92, 179) 2%, rgb(205, 92, 179) 31%,rgb(139, 86, 140) 31%, rgb(139, 86, 140) 51%,transparent 51%, transparent 59%,rgb(39, 77, 82) 59%, rgb(39, 77, 82) 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  81 => 
  array (
    'name' => 'Abstract Blend 41',
    'slug' => 'classic-abstract-41',
    'preview_color' => 'linear-gradient(135deg, rgba(221, 113, 32,0.6), rgba(221, 113, 32,0.6))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-41 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, transparent 0%, transparent 9%,rgba(221, 113, 32,0.6) 9%, rgba(221, 113, 32,0.6) 22%,transparent 22%, transparent 48%,rgba(239, 148, 10,0.6) 48%, rgba(239, 148, 10,0.6) 100%),linear-gradient(45deg, transparent 0%, transparent 29%,rgb(221, 113, 32) 29%, rgb(221, 113, 32) 48%,rgb(202, 77, 53) 48%, rgb(202, 77, 53) 84%,transparent 84%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  82 => 
  array (
    'name' => 'Abstract Blend 42',
    'slug' => 'classic-abstract-42',
    'preview_color' => 'linear-gradient(135deg, rgba(201, 24, 88,0.5), rgba(201, 24, 88,0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-42 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, transparent 0%, transparent 15%,rgba(201, 24, 88,0.5) 15%, rgba(201, 24, 88,0.5) 33%,transparent 33%, transparent 79%,rgba(210, 36, 85,0.5) 79%, rgba(210, 36, 85,0.5) 100%),linear-gradient(45deg, transparent 0%, transparent 52%,rgb(201, 24, 88) 52%, rgb(201, 24, 88) 77%,rgb(238, 70, 74) 77%, rgb(238, 70, 74) 87%,transparent 87%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  83 => 
  array (
    'name' => 'Abstract Blend 43',
    'slug' => 'classic-abstract-43',
    'preview_color' => 'linear-gradient(135deg, rgba(142, 36, 180,0.15), rgba(142, 36, 180,0.15))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-43 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, transparent 0%, transparent 26%,rgba(142, 36, 180,0.15) 26%, rgba(142, 36, 180,0.15) 56%,transparent 56%, transparent 100%),linear-gradient(135deg, transparent 0%, transparent 36%,rgba(142, 36, 180,0.15) 36%, rgba(142, 36, 180,0.15) 71%,transparent 71%, transparent 100%),linear-gradient(135deg, transparent 0%, transparent 31%,rgba(142, 36, 180,0.15) 31%, rgba(142, 36, 180,0.15) 56%,transparent 56%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  84 => 
  array (
    'name' => 'Abstract Blend 44',
    'slug' => 'classic-abstract-44',
    'preview_color' => 'linear-gradient(135deg, rgba(63, 106, 202,0.08), rgba(63, 106, 202,0.08))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-44 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(147deg, transparent 0%, transparent 8%,rgba(63, 106, 202,0.08) 8%, rgba(63, 106, 202,0.08) 46%,transparent 46%, transparent 100%),linear-gradient(107deg, transparent 0%, transparent 21%,rgba(63, 106, 202,0.08) 21%, rgba(63, 106, 202,0.08) 53%,transparent 53%, transparent 100%),linear-gradient(288deg, transparent 0%, transparent 35%,rgba(63, 106, 202,0.08) 35%, rgba(63, 106, 202,0.08) 91%,transparent 91%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); }',
    'js' => NULL,
  ),
  85 => 
  array (
    'name' => 'Abstract Blend 45',
    'slug' => 'classic-abstract-45',
    'preview_color' => 'linear-gradient(135deg, rgba(255,255,255,0.04), rgba(255,255,255,0.04))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-45 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 48% 33%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 28% 16%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 34% 52%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 92% 52%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 77% 84%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 75% 64%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 70% 62%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 55% 100%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 12% 11%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 35% 55%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),linear-gradient(45deg, rgb(26, 21, 192),rgb(171, 83, 239)); }',
    'js' => NULL,
  ),
  86 => 
  array (
    'name' => 'Abstract Blend 46',
    'slug' => 'classic-abstract-46',
    'preview_color' => 'linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.03))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-46 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 88% 44%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 46% 95%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 83% 91%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 24% 95%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 42% 85%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 61% 40%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 27% 33%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 30% 12%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 99% 87%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 84% 63%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 4%,transparent 4%, transparent 96%),linear-gradient(45deg, rgb(200, 21, 78),rgb(191, 186, 10)); }',
    'js' => NULL,
  ),
  87 => 
  array (
    'name' => 'Abstract Blend 47',
    'slug' => 'classic-abstract-47',
    'preview_color' => 'linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.03))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-47 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 10% 8%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 87% 45%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 8%,transparent 8%, transparent 92%),radial-gradient(circle at 9% 67%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 31% 83%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 46% 54%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 16% 24%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 18% 9%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 6%,transparent 6%, transparent 94%),radial-gradient(circle at 85% 69%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 55% 7%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 69% 69%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),radial-gradient(circle at 68% 60%, rgba(255,255,255,0.04) 0%, rgba(255,255,255,0.04) 4%,transparent 4%, transparent 96%),linear-gradient(135deg, rgb(3, 7, 46),rgb(24, 44, 146)); }',
    'js' => NULL,
  ),
  88 => 
  array (
    'name' => 'Abstract Blend 48',
    'slug' => 'classic-abstract-48',
    'preview_color' => 'linear-gradient(135deg, rgba(255,255,255,0.02), rgba(255,255,255,0.02))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-48 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 73% 11%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 3%,transparent 3%, transparent 100%),radial-gradient(circle at 48% 73%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 3%,transparent 3%, transparent 100%),radial-gradient(circle at 99% 95%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 3%,transparent 3%, transparent 100%),radial-gradient(circle at 7% 76%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 3%,transparent 3%, transparent 100%),radial-gradient(circle at 56% 95%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 80% 39%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 17% 72%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 68% 73%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 71% 40%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 28% 50%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 0% 27%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 7% 45%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 34% 64%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 7%,transparent 7%, transparent 100%),radial-gradient(circle at 80% 24%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 15% 59%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 62% 67%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 73% 71%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 30% 40%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 73% 62%, rgba(255,255,255,0.03) 0%, rgba(255,255,255,0.03) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 43% 93%, rgba(255,255,255,0.01) 0%, rgba(255,255,255,0.01) 5%,transparent 5%, transparent 100%),radial-gradient(circle at 15% 64%, rgba(255,255,255,0.02) 0%, rgba(255,255,255,0.02) 5%,transparent 5%, transparent 100%),linear-gradient(135deg, rgb(38, 135, 57),rgb(56, 4, 65)); }',
    'js' => NULL,
  ),
  89 => 
  array (
    'name' => 'Abstract Blend 49',
    'slug' => 'classic-abstract-49',
    'preview_color' => 'linear-gradient(135deg, rgb(13, 141, 190), rgb(13, 141, 190))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-49 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top right, rgb(13, 141, 190) 0%, rgb(13, 141, 190) 46%,rgb(22, 153, 204) 46%, rgb(22, 153, 204) 49%,rgb(31, 166, 217) 49%, rgb(31, 166, 217) 52%,rgb(40, 178, 231) 52%, rgb(40, 178, 231) 54%,rgb(49, 190, 244) 54%, rgb(49, 190, 244) 100%); }',
    'js' => NULL,
  ),
  90 => 
  array (
    'name' => 'Abstract Blend 50',
    'slug' => 'classic-abstract-50',
    'preview_color' => 'linear-gradient(135deg, rgb(4, 20, 62), rgb(4, 20, 62))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-50 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top right, rgb(4, 20, 62) 0%, rgb(4, 20, 62) 28%,rgb(49, 29, 62) 28%, rgb(49, 29, 62) 45%,rgb(94, 38, 62) 45%, rgb(94, 38, 62) 63%,rgb(138, 47, 62) 63%, rgb(138, 47, 62) 100%); }',
    'js' => NULL,
  ),
  91 => 
  array (
    'name' => 'Abstract Blend 51',
    'slug' => 'classic-abstract-51',
    'preview_color' => 'linear-gradient(135deg, rgb(43, 36, 140), rgb(43, 36, 140))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-51 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center right, rgb(43, 36, 140) 0%, rgb(43, 36, 140) 64%,rgb(51, 30, 166) 64%, rgb(51, 30, 166) 80%,rgb(59, 23, 191) 80%, rgb(59, 23, 191) 86%,rgb(67, 17, 217) 86%, rgb(67, 17, 217) 92%,rgb(75, 10, 242) 92%, rgb(75, 10, 242) 100%); }',
    'js' => NULL,
  ),
  92 => 
  array (
    'name' => 'Abstract Blend 52',
    'slug' => 'classic-abstract-52',
    'preview_color' => 'linear-gradient(135deg, rgb(15, 54, 60), rgb(15, 54, 60))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-52 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top right, rgb(15, 54, 60) 0%, rgb(15, 54, 60) 3%,rgb(62, 41, 84) 3%, rgb(62, 41, 84) 50%,rgb(108, 28, 108) 50%, rgb(108, 28, 108) 60%,rgb(155, 14, 131) 60%, rgb(155, 14, 131) 63%,rgb(201, 1, 155) 63%, rgb(201, 1, 155) 100%); }',
    'js' => NULL,
  ),
  93 => 
  array (
    'name' => 'Abstract Blend 53',
    'slug' => 'classic-abstract-53',
    'preview_color' => 'linear-gradient(135deg, rgb(52, 33, 141), rgb(52, 33, 141))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-53 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom right, rgb(52, 33, 141) 0%, rgb(52, 33, 141) 20%,rgb(52, 50, 168) 20%, rgb(52, 50, 168) 40%,rgb(52, 68, 195) 40%, rgb(52, 68, 195) 60%,rgb(52, 85, 221) 60%, rgb(52, 85, 221) 80%,rgb(52, 102, 248) 80%, rgb(52, 102, 248) 100%); }',
    'js' => NULL,
  ),
  94 => 
  array (
    'name' => 'Abstract Blend 54',
    'slug' => 'classic-abstract-54',
    'preview_color' => 'linear-gradient(135deg, rgb(253, 97, 39), rgb(253, 97, 39))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-54 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center right, rgb(253, 97, 39) 0%, rgb(253, 97, 39) 14.286%,rgb(251, 108, 38) 14.286%, rgb(251, 108, 38) 28.572%,rgb(249, 118, 37) 28.572%, rgb(249, 118, 37) 42.858%,rgb(247, 129, 37) 42.858%, rgb(247, 129, 37) 57.144%,rgb(245, 140, 36) 57.144%, rgb(245, 140, 36) 71.43%,rgb(243, 150, 35) 71.43%, rgb(243, 150, 35) 85.716%,rgb(241, 161, 34) 85.716%, rgb(241, 161, 34) 100.002%); }',
    'js' => NULL,
  ),
  95 => 
  array (
    'name' => 'Abstract Blend 55',
    'slug' => 'classic-abstract-55',
    'preview_color' => 'linear-gradient(135deg, rgb(46, 46, 46), rgb(46, 46, 46))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-55 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center left, rgb(46, 46, 46) 0%, rgb(46, 46, 46) 6%,rgb(41, 41, 41) 6%, rgb(41, 41, 41) 27%,rgb(36, 36, 36) 27%, rgb(36, 36, 36) 42%,rgb(31, 31, 31) 42%, rgb(31, 31, 31) 63%,rgb(25, 25, 25) 63%, rgb(25, 25, 25) 64%,rgb(20, 20, 20) 64%, rgb(20, 20, 20) 71%,rgb(15, 15, 15) 71%, rgb(15, 15, 15) 100%); }',
    'js' => NULL,
  ),
  96 => 
  array (
    'name' => 'Abstract Blend 56',
    'slug' => 'classic-abstract-56',
    'preview_color' => 'linear-gradient(135deg, rgb(49, 157, 235), rgb(49, 157, 235))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-56 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top right, rgb(49, 157, 235) 0%, rgb(49, 157, 235) 13%,rgb(76, 166, 234) 13%, rgb(76, 166, 234) 23%,rgb(103, 176, 232) 23%, rgb(103, 176, 232) 33%,rgb(130, 185, 231) 33%, rgb(130, 185, 231) 46%,rgb(156, 194, 230) 46%, rgb(156, 194, 230) 48%,rgb(183, 203, 229) 48%, rgb(183, 203, 229) 63%,rgb(210, 213, 227) 63%, rgb(210, 213, 227) 83%,rgb(237, 222, 226) 83%, rgb(237, 222, 226) 100%); }',
    'js' => NULL,
  ),
  97 => 
  array (
    'name' => 'Abstract Blend 57',
    'slug' => 'classic-abstract-57',
    'preview_color' => 'linear-gradient(135deg, rgb(89, 6, 134), rgb(89, 6, 134))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-57 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgb(89, 6, 134) 0%, rgb(89, 6, 134) 3%,rgb(108, 25, 146) 3%, rgb(108, 25, 146) 9%,rgb(126, 45, 158) 9%, rgb(126, 45, 158) 12%,rgb(145, 64, 171) 12%, rgb(145, 64, 171) 20%,rgb(163, 83, 183) 20%, rgb(163, 83, 183) 30%,rgb(182, 103, 195) 30%, rgb(182, 103, 195) 32%,transparent 32%, transparent 100%),radial-gradient(circle at center left, rgb(89, 6, 134) 0%, rgb(89, 6, 134) 4%,rgb(108, 25, 146) 4%, rgb(108, 25, 146) 10%,rgb(126, 45, 158) 10%, rgb(126, 45, 158) 15%,rgb(145, 64, 171) 15%, rgb(145, 64, 171) 18%,rgb(163, 83, 183) 18%, rgb(163, 83, 183) 24%,rgb(182, 103, 195) 24%, rgb(182, 103, 195) 26%,transparent 26%, transparent 100%),radial-gradient(circle at center right, rgb(89, 6, 134) 0%, rgb(89, 6, 134) 3%,rgb(108, 25, 146) 3%, rgb(108, 25, 146) 9%,rgb(126, 45, 158) 9%, rgb(126, 45, 158) 12%,rgb(145, 64, 171) 12%, rgb(145, 64, 171) 15%,rgb(163, 83, 183) 15%, rgb(163, 83, 183) 21%,rgb(182, 103, 195) 21%, rgb(182, 103, 195) 24%,rgb(200, 122, 207) 24%, rgb(200, 122, 207) 100%); }',
    'js' => NULL,
  ),
  98 => 
  array (
    'name' => 'Abstract Blend 58',
    'slug' => 'classic-abstract-58',
    'preview_color' => 'linear-gradient(135deg, rgb(31, 165, 212), rgb(31, 165, 212))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-58 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom center, rgb(31, 165, 212) 0%, rgb(31, 165, 212) 4%,rgb(38, 152, 211) 4%, rgb(38, 152, 211) 8%,rgb(46, 139, 209) 8%, rgb(46, 139, 209) 12%,rgb(53, 127, 208) 12%, rgb(53, 127, 208) 16%,rgb(60, 114, 206) 16%, rgb(60, 114, 206) 20%,rgb(68, 101, 205) 20%, rgb(68, 101, 205) 24%,transparent 24%, transparent 100%),radial-gradient(circle at top center, rgb(31, 165, 212) 0%, rgb(31, 165, 212) 3%,rgb(38, 152, 211) 3%, rgb(38, 152, 211) 9%,rgb(46, 139, 209) 9%, rgb(46, 139, 209) 12%,rgb(53, 127, 208) 12%, rgb(53, 127, 208) 15%,rgb(60, 114, 206) 15%, rgb(60, 114, 206) 18%,rgb(68, 101, 205) 18%, rgb(68, 101, 205) 21%,transparent 21%, transparent 100%),radial-gradient(circle at center left, rgb(31, 165, 212) 0%, rgb(31, 165, 212) 3%,rgb(38, 152, 211) 3%, rgb(38, 152, 211) 9%,rgb(46, 139, 209) 9%, rgb(46, 139, 209) 12%,rgb(53, 127, 208) 12%, rgb(53, 127, 208) 15%,rgb(60, 114, 206) 15%, rgb(60, 114, 206) 18%,rgb(68, 101, 205) 18%, rgb(68, 101, 205) 21%,transparent 21%, transparent 100%),radial-gradient(circle at center right, rgb(31, 165, 212) 0%, rgb(31, 165, 212) 3%,rgb(38, 152, 211) 3%, rgb(38, 152, 211) 9%,rgb(46, 139, 209) 9%, rgb(46, 139, 209) 12%,rgb(53, 127, 208) 12%, rgb(53, 127, 208) 15%,rgb(60, 114, 206) 15%, rgb(60, 114, 206) 18%,rgb(68, 101, 205) 18%, rgb(68, 101, 205) 21%,rgb(75, 88, 203) 21%, rgb(75, 88, 203) 100%); }',
    'js' => NULL,
  ),
  99 => 
  array (
    'name' => 'Abstract Blend 59',
    'slug' => 'classic-abstract-59',
    'preview_color' => 'linear-gradient(135deg, rgb(220, 206, 83), rgb(220, 206, 83))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-59 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at top center, rgb(220, 206, 83) 0%, rgb(220, 206, 83) 5%,rgb(199, 202, 70) 5%, rgb(199, 202, 70) 10%,rgb(179, 198, 56) 10%, rgb(179, 198, 56) 15%,rgb(158, 194, 43) 15%, rgb(158, 194, 43) 20%,rgb(137, 190, 30) 20%, rgb(137, 190, 30) 25%,rgb(117, 186, 16) 25%, rgb(117, 186, 16) 30%,transparent 30%, transparent 100%),radial-gradient(circle at bottom right, rgb(220, 206, 83) 0%, rgb(220, 206, 83) 5%,rgb(199, 202, 70) 5%, rgb(199, 202, 70) 10%,rgb(179, 198, 56) 10%, rgb(179, 198, 56) 15%,rgb(158, 194, 43) 15%, rgb(158, 194, 43) 20%,rgb(137, 190, 30) 20%, rgb(137, 190, 30) 25%,rgb(117, 186, 16) 25%, rgb(117, 186, 16) 30%,transparent 30%, transparent 100%),radial-gradient(circle at bottom left, rgb(220, 206, 83) 0%, rgb(220, 206, 83) 5%,rgb(199, 202, 70) 5%, rgb(199, 202, 70) 10%,rgb(179, 198, 56) 10%, rgb(179, 198, 56) 15%,rgb(158, 194, 43) 15%, rgb(158, 194, 43) 20%,rgb(137, 190, 30) 20%, rgb(137, 190, 30) 25%,rgb(117, 186, 16) 25%, rgb(117, 186, 16) 30%,rgb(96, 182, 3) 30%, rgb(96, 182, 3) 100%); }',
    'js' => NULL,
  ),
  100 => 
  array (
    'name' => 'Abstract Blend 60',
    'slug' => 'classic-abstract-60',
    'preview_color' => 'linear-gradient(135deg, rgb(4, 128, 235), rgb(4, 128, 235))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-60 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom right, rgb(4, 128, 235) 0%, rgb(4, 128, 235) 4%,rgb(4, 108, 199) 4%, rgb(4, 108, 199) 8%,rgb(5, 89, 163) 8%, rgb(5, 89, 163) 12%,rgb(5, 69, 127) 12%, rgb(5, 69, 127) 16%,rgb(5, 49, 91) 16%, rgb(5, 49, 91) 20%,rgb(6, 30, 55) 20%, rgb(6, 30, 55) 24%,transparent 24%, transparent 100%),radial-gradient(circle at bottom left, rgb(4, 128, 235) 0%, rgb(4, 128, 235) 4%,rgb(4, 108, 199) 4%, rgb(4, 108, 199) 8%,rgb(5, 89, 163) 8%, rgb(5, 89, 163) 12%,rgb(5, 69, 127) 12%, rgb(5, 69, 127) 16%,rgb(5, 49, 91) 16%, rgb(5, 49, 91) 20%,rgb(6, 30, 55) 20%, rgb(6, 30, 55) 24%,rgb(6, 10, 19) 24%, rgb(6, 10, 19) 100%); }',
    'js' => NULL,
  ),
  101 => 
  array (
    'name' => 'Abstract Blend 61',
    'slug' => 'classic-abstract-61',
    'preview_color' => 'linear-gradient(135deg, rgb(5,67,140), rgb(5,67,140))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-61 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at bottom center, rgb(5,67,140) 0%, rgb(5,67,140) 32%,transparent 32%, transparent 40%,transparent 40%, transparent 100%),radial-gradient(circle at center center, rgb(5,67,140) 0%, rgb(5,67,140) 23%,transparent 23%, transparent 36%,transparent 36%, transparent 100%),linear-gradient(90deg, rgb(8,50,111),rgb(8,50,111)); background-size: 50px 50px; }',
    'js' => NULL,
  ),
  102 => 
  array (
    'name' => 'Abstract Blend 62',
    'slug' => 'classic-abstract-62',
    'preview_color' => 'linear-gradient(135deg, rgb(238, 229, 67), rgb(238, 229, 67))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-62 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgb(238, 229, 67) 0%, rgb(238, 229, 67) 19%,rgb(204,180,18) 19%, rgb(204,180,18) 26%,transparent 26%, transparent 100%),linear-gradient(90deg, hsl(254,15%,17%) 0%, hsl(254,15%,17%) 33.333%,hsl(254,15%,17%) 33.333%, hsl(254,15%,17%) 66.666%,hsl(254,15%,17%) 66.666%, hsl(254,15%,17%) 99.999%); background-size: 74px 74px; }',
    'js' => NULL,
  ),
  103 => 
  array (
    'name' => 'Abstract Blend 63',
    'slug' => 'classic-abstract-63',
    'preview_color' => 'linear-gradient(135deg, rgb(151,11,32), rgb(151,11,32))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-63 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 24% 76%, rgb(151,11,32) 0%, rgb(151,11,32) 10%,transparent 10%, transparent 100%),radial-gradient(circle at 76% 76%, rgb(151,11,32) 0%, rgb(151,11,32) 10%,transparent 10%, transparent 100%),radial-gradient(circle at 76% 24%, rgb(151,11,32) 0%, rgb(151,11,32) 10%,transparent 10%, transparent 100%),radial-gradient(circle at 24% 24%, rgb(151,11,32) 0%, rgb(151,11,32) 10%,transparent 10%, transparent 100%),radial-gradient(circle at center center, rgb(202,16,44) 0%, rgb(202,16,44) 71%,transparent 71%, transparent 100%),linear-gradient(90deg, rgb(151,11,32),rgb(151,11,32)); background-size: 22px 22px; }',
    'js' => NULL,
  ),
  104 => 
  array (
    'name' => 'Abstract Blend 64',
    'slug' => 'classic-abstract-64',
    'preview_color' => 'linear-gradient(135deg, hsl(112,49%,26%), hsl(112,49%,26%))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-64 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 24% 76%, hsl(112,49%,26%) 0%, hsl(112,49%,26%) 14%,transparent 14%, transparent 100%),radial-gradient(circle at 76% 76%, hsl(112,49%,26%) 0%, hsl(112,49%,26%) 14%,transparent 14%, transparent 100%),radial-gradient(circle at 76% 24%, hsl(112,49%,26%) 0%, hsl(112,49%,26%) 14%,transparent 14%, transparent 100%),radial-gradient(circle at 24% 24%, hsl(112,49%,26%) 0%, hsl(112,49%,26%) 14%,transparent 14%, transparent 100%),radial-gradient(circle at center center, rgb(1, 61, 23) 0%, rgb(1, 61, 23) 71%,transparent 71%, transparent 100%),linear-gradient(90deg, hsl(112,49%,26%),hsl(112,49%,26%)); background-size: 59px 59px; }',
    'js' => NULL,
  ),
  105 => 
  array (
    'name' => 'Abstract Blend 65',
    'slug' => 'classic-abstract-65',
    'preview_color' => 'linear-gradient(135deg, rgb(33,5,66), rgb(33,5,66))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-65 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 24% 76%, rgb(33,5,66) 0%, rgb(33,5,66) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 76% 76%, rgb(33,5,66) 0%, rgb(33,5,66) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 76% 24%, rgb(33,5,66) 0%, rgb(33,5,66) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 24% 24%, rgb(33,5,66) 0%, rgb(33,5,66) 20%,transparent 20%, transparent 100%),radial-gradient(circle at center center, rgb(54,3,88) 0%, rgb(54,3,88) 71%,transparent 71%, transparent 100%),linear-gradient(90deg, rgb(33,5,66),rgb(33,5,66)); background-size: 19px 19px; }',
    'js' => NULL,
  ),
  106 => 
  array (
    'name' => 'Abstract Blend 66',
    'slug' => 'classic-abstract-66',
    'preview_color' => 'linear-gradient(135deg, rgb(132,58,234), rgb(132,58,234))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-66 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 24% 76%, rgb(132,58,234) 0%, rgb(132,58,234) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 76% 76%, rgb(132,58,234) 0%, rgb(132,58,234) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 76% 24%, rgb(132,58,234) 0%, rgb(132,58,234) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 24% 24%, rgb(132,58,234) 0%, rgb(132,58,234) 20%,transparent 20%, transparent 100%),radial-gradient(circle at center center, rgb(99,20,187) 0%, rgb(99,20,187) 71%,transparent 71%, transparent 100%),linear-gradient(90deg, rgb(132,58,234),rgb(132,58,234)); background-size: 49px 49px; }',
    'js' => NULL,
  ),
  107 => 
  array (
    'name' => 'Abstract Blend 67',
    'slug' => 'classic-abstract-67',
    'preview_color' => 'linear-gradient(135deg, rgb(255,163,44), rgb(255,163,44))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-67 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at 25% 75%, rgb(255,163,44) 0%, rgb(255,163,44) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 75% 75%, rgb(255,163,44) 0%, rgb(255,163,44) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 75% 25%, rgb(255,163,44) 0%, rgb(255,163,44) 20%,transparent 20%, transparent 100%),radial-gradient(circle at 25% 25%, rgb(255,163,44) 0%, rgb(255,163,44) 20%,transparent 20%, transparent 100%),radial-gradient(circle at center center, rgb(235,127,52) 0%, rgb(235,127,52) 71%,transparent 71%, transparent 100%),linear-gradient(90deg, rgb(255,163,44),rgb(255,163,44)); background-size: 100px 100px; }',
    'js' => NULL,
  ),
  108 => 
  array (
    'name' => 'Abstract Blend 68',
    'slug' => 'classic-abstract-68',
    'preview_color' => 'linear-gradient(135deg, rgba(11, 138, 58,0.3), rgba(11, 138, 58,0.3))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-68 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle at center center, rgba(11, 138, 58,0.3) 0%, rgba(11, 138, 58,0.3) 71%,transparent 71%, transparent 100%),radial-gradient(circle at center center, rgba(126, 121, 4,0.3) 0%, rgba(126, 121, 4,0.3) 94%,transparent 94%, transparent 100%),radial-gradient(circle at center center, rgba(70, 57, 9,0.3) 0%, rgba(70, 57, 9,0.3) 11%,transparent 11%, transparent 100%),radial-gradient(circle at center center, rgba(189, 64, 190,0.3) 0%, rgba(189, 64, 190,0.3) 27%,transparent 27%, transparent 100%),linear-gradient(90deg, rgb(255,255,255),rgb(255,255,255)); background-size: 23px 23px; }',
    'js' => NULL,
  ),
  109 => 
  array (
    'name' => 'Abstract Blend 69',
    'slug' => 'classic-abstract-69',
    'preview_color' => 'linear-gradient(135deg, rgba(102, 184, 13,0.25), rgba(102, 184, 13,0.25))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-69 { position:fixed; inset:0; z-index:-1; background-image: repeating-radial-gradient(circle at center center, rgba(102, 184, 13,0.25) 0px, rgba(102, 184, 13,0.25) 3px,transparent 3px, transparent 10px,rgba(101, 156, 106,0.25) 10px, rgba(101, 156, 106,0.25) 17px,transparent 17px, transparent 21px,rgba(101, 163, 83,0.25) 21px, rgba(101, 163, 83,0.25) 22px),repeating-linear-gradient(67.5deg, rgb(0,0,0) 0px, rgb(0,0,0) 13px,rgb(0,0,0) 13px, rgb(0,0,0) 16px,rgb(0,0,0) 16px, rgb(0,0,0) 30px,rgb(0,0,0) 30px, rgb(0,0,0) 31px); background-size: 72px 72px; }',
    'js' => NULL,
  ),
  110 => 
  array (
    'name' => 'Abstract Blend 70',
    'slug' => 'classic-abstract-70',
    'preview_color' => 'linear-gradient(135deg, rgba(249, 92, 47,0.4), rgba(249, 92, 47,0.4))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-70 { position:fixed; inset:0; z-index:-1; background-image: repeating-radial-gradient(circle at center center, rgba(249, 92, 47,0.4) 0px, rgba(249, 92, 47,0.4) 3px,transparent 3px, transparent 7px,rgba(220, 87, 48,0.4) 7px, rgba(220, 87, 48,0.4) 19px,transparent 19px, transparent 23px,rgba(132, 72, 51,0.4) 23px, rgba(132, 72, 51,0.4) 33px),repeating-linear-gradient(0deg, rgb(0,0,0) 0px, rgb(0,0,0) 2px,rgb(0,0,0) 2px, rgb(0,0,0) 9px,rgb(0,0,0) 9px, rgb(0,0,0) 15px,rgb(0,0,0) 15px, rgb(0,0,0) 26px); background-size: 60px 60px; }',
    'js' => NULL,
  ),
  111 => 
  array (
    'name' => 'Abstract Blend 71',
    'slug' => 'classic-abstract-71',
    'preview_color' => 'linear-gradient(135deg, rgb(224, 240, 162), rgb(224, 240, 162))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-71 { position:fixed; inset:0; z-index:-1; background-image: repeating-radial-gradient(circle at center center, rgb(224, 240, 162) 0px, rgb(224, 240, 162) 44px,transparent 44px, transparent 81px,rgb(189, 147, 88) 81px, rgb(189, 147, 88) 113px,transparent 113px, transparent 135px,rgb(171, 101, 50) 135px, rgb(171, 101, 50) 175px),repeating-linear-gradient(135deg, rgb(85, 255, 156) 0px, rgb(85, 255, 156) 2px,rgb(138, 172, 182) 2px, rgb(138, 172, 182) 10px,rgb(192, 88, 208) 10px, rgb(192, 88, 208) 16px,rgb(245, 5, 234) 16px, rgb(245, 5, 234) 20px); background-size: 69px 69px; }',
    'js' => NULL,
  ),
  112 => 
  array (
    'name' => 'Abstract Blend 72',
    'slug' => 'classic-abstract-72',
    'preview_color' => 'linear-gradient(135deg, rgb(162, 97, 49), rgb(162, 97, 49))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-72 { position:fixed; inset:0; z-index:-1; background-image: repeating-radial-gradient(circle at center center, rgb(162, 97, 49) 0px, rgb(162, 97, 49) 5px,rgb(166, 101, 67) 5px, rgb(166, 101, 67) 11px,rgb(169, 105, 84) 11px, rgb(169, 105, 84) 18px); background-size: 57px 57px; }',
    'js' => NULL,
  ),
  113 => 
  array (
    'name' => 'Abstract Blend 73',
    'slug' => 'classic-abstract-73',
    'preview_color' => 'linear-gradient(135deg, rgba(22, 31, 43, 0.5), rgba(22, 31, 43, 0.5))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-73 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgba(22, 31, 43, 0.5) 0%, rgba(22, 31, 43, 0.5) 12.5%,rgba(53, 28, 54, 0.5) 12.5%, rgba(53, 28, 54, 0.5) 25%,rgba(83, 25, 65, 0.5) 25%, rgba(83, 25, 65, 0.5) 37.5%,rgba(114, 22, 76, 0.5) 37.5%, rgba(114, 22, 76, 0.5) 50%,rgba(144, 20, 86, 0.5) 50%, rgba(144, 20, 86, 0.5) 62.5%,rgba(175, 17, 97, 0.5) 62.5%, rgba(175, 17, 97, 0.5) 75%,rgba(205, 14, 108, 0.5) 75%, rgba(205, 14, 108, 0.5) 87.5%,rgba(236, 11, 119, 0.5) 87.5%, rgba(236, 11, 119, 0.5) 100%),linear-gradient(135deg, rgb(188, 0, 159) 0%, rgb(188, 0, 159) 12.5%,rgb(173, 4, 150) 12.5%, rgb(173, 4, 150) 25%,rgb(158, 7, 141) 25%, rgb(158, 7, 141) 37.5%,rgb(143, 11, 132) 37.5%, rgb(143, 11, 132) 50%,rgb(129, 15, 124) 50%, rgb(129, 15, 124) 62.5%,rgb(114, 19, 115) 62.5%, rgb(114, 19, 115) 75%,rgb(99, 22, 106) 75%, rgb(99, 22, 106) 87.5%,rgb(84, 26, 97) 87.5%, rgb(84, 26, 97) 100%); }',
    'js' => NULL,
  ),
  114 => 
  array (
    'name' => 'Abstract Blend 74',
    'slug' => 'classic-abstract-74',
    'preview_color' => 'linear-gradient(135deg, rgba(159, 159, 159, 0.46), rgba(159, 159, 159, 0.46))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-74 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, rgba(159, 159, 159, 0.46) 0%, rgba(159, 159, 159, 0.46) 14.286%,rgba(165, 165, 165, 0.46) 14.286%, rgba(165, 165, 165, 0.46) 28.572%,rgba(171, 171, 171, 0.46) 28.572%, rgba(171, 171, 171, 0.46) 42.858%,rgba(178, 178, 178, 0.46) 42.858%, rgba(178, 178, 178, 0.46) 57.144%,rgba(184, 184, 184, 0.46) 57.144%, rgba(184, 184, 184, 0.46) 71.43%,rgba(190, 190, 190, 0.46) 71.43%, rgba(190, 190, 190, 0.46) 85.716%,rgba(196, 196, 196, 0.46) 85.716%, rgba(196, 196, 196, 0.46) 100.002%),linear-gradient(45deg, rgb(252, 252, 252) 0%, rgb(252, 252, 252) 14.286%,rgb(246, 246, 246) 14.286%, rgb(246, 246, 246) 28.572%,rgb(241, 241, 241) 28.572%, rgb(241, 241, 241) 42.858%,rgb(235, 235, 235) 42.858%, rgb(235, 235, 235) 57.144%,rgb(229, 229, 229) 57.144%, rgb(229, 229, 229) 71.43%,rgb(224, 224, 224) 71.43%, rgb(224, 224, 224) 85.716%,rgb(218, 218, 218) 85.716%, rgb(218, 218, 218) 100.002%); }',
    'js' => NULL,
  ),
  115 => 
  array (
    'name' => 'Abstract Blend 75',
    'slug' => 'classic-abstract-75',
    'preview_color' => 'linear-gradient(135deg, rgba(254, 246, 210, 0.53), rgba(254, 246, 210, 0.53))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-75 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgba(254, 246, 210, 0.53) 0%, rgba(254, 246, 210, 0.53) 14.286%,rgba(221, 240, 216, 0.53) 14.286%, rgba(221, 240, 216, 0.53) 28.572%,rgba(188, 233, 223, 0.53) 28.572%, rgba(188, 233, 223, 0.53) 42.858%,rgba(156, 227, 229, 0.53) 42.858%, rgba(156, 227, 229, 0.53) 57.144%,rgba(123, 220, 235, 0.53) 57.144%, rgba(123, 220, 235, 0.53) 71.42999999999999%,rgba(90, 214, 242, 0.53) 71.43%, rgba(90, 214, 242, 0.53) 85.71600000000001%,rgba(57, 207, 248, 0.53) 85.716%, rgba(57, 207, 248, 0.53) 100.002%),linear-gradient(135deg, rgb(246, 99, 200) 0%, rgb(246, 99, 200) 12.5%,rgb(223, 98, 196) 12.5%, rgb(223, 98, 196) 25%,rgb(199, 97, 192) 25%, rgb(199, 97, 192) 37.5%,rgb(176, 96, 188) 37.5%, rgb(176, 96, 188) 50%,rgb(152, 95, 184) 50%, rgb(152, 95, 184) 62.5%,rgb(129, 94, 180) 62.5%, rgb(129, 94, 180) 75%,rgb(105, 93, 176) 75%, rgb(105, 93, 176) 87.5%,rgb(82, 92, 172) 87.5%, rgb(82, 92, 172) 100%); }',
    'js' => NULL,
  ),
  116 => 
  array (
    'name' => 'Abstract Blend 76',
    'slug' => 'classic-abstract-76',
    'preview_color' => 'linear-gradient(135deg, hsl(259,74%,69%), hsl(259,74%,69%))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-76 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, hsl(259,74%,69%) 0%, hsl(259,74%,69%) 12.5%,hsl(304,74%,69%) 12.5%, hsl(304,74%,69%) 25%,hsl(349,74%,69%) 25%, hsl(349,74%,69%) 37.5%,hsl(34,74%,69%) 37.5%, hsl(34,74%,69%) 50%,hsl(79,74%,69%) 50%, hsl(79,74%,69%) 62.5%,hsl(124,74%,69%) 62.5%, hsl(124,74%,69%) 75%,hsl(169,74%,69%) 75%, hsl(169,74%,69%) 87.5%,hsl(214,74%,69%) 87.5%, hsl(214,74%,69%) 100%); }',
    'js' => NULL,
  ),
  117 => 
  array (
    'name' => 'Abstract Blend 77',
    'slug' => 'classic-abstract-77',
    'preview_color' => 'linear-gradient(135deg, rgb(2, 6, 1), rgb(2, 6, 1))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-77 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(2, 6, 1) 0%, rgb(2, 6, 1) 14.286%,rgb(8, 29, 31) 14.286%, rgb(8, 29, 31) 28.572%,rgb(13, 52, 61) 28.572%, rgb(13, 52, 61) 42.858%,rgb(19, 75, 92) 42.858%, rgb(19, 75, 92) 57.144%,rgb(25, 98, 122) 57.144%, rgb(25, 98, 122) 71.43%,rgb(30, 121, 152) 71.43%, rgb(30, 121, 152) 85.716%,rgb(36, 144, 182) 85.716%, rgb(36, 144, 182) 100.002%); }',
    'js' => NULL,
  ),
  118 => 
  array (
    'name' => 'Abstract Blend 78',
    'slug' => 'classic-abstract-78',
    'preview_color' => 'linear-gradient(135deg, rgb(216, 179, 74), rgb(216, 179, 74))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-78 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(216, 179, 74) 0%, rgb(216, 179, 74) 25%,rgb(200, 132, 50) 25%, rgb(200, 132, 50) 50%,rgb(183, 86, 26) 50%, rgb(183, 86, 26) 75%,rgb(167, 39, 2) 75%, rgb(167, 39, 2) 100%); }',
    'js' => NULL,
  ),
  119 => 
  array (
    'name' => 'Abstract Blend 79',
    'slug' => 'classic-abstract-79',
    'preview_color' => 'linear-gradient(135deg, rgb(230, 206, 162), rgb(230, 206, 162))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-79 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(230, 206, 162) 0%, rgb(230, 206, 162) 14.3%,rgb(201, 197, 167) 14.3%, rgb(201, 197, 167) 28.6%,rgb(172, 188, 172) 28.6%, rgb(172, 188, 172) 42.900000000000006%,rgb(144, 179, 178) 42.900000000000006%, rgb(144, 179, 178) 57.2%,rgb(115, 170, 183) 57.2%, rgb(115, 170, 183) 71.5%,rgb(86, 161, 188) 71.5%, rgb(86, 161, 188) 85.8%,rgb(57, 152, 193) 85.8%, rgb(57, 152, 193) 100.1%); }',
    'js' => NULL,
  ),
  120 => 
  array (
    'name' => 'Abstract Blend 80',
    'slug' => 'classic-abstract-80',
    'preview_color' => 'linear-gradient(135deg, rgb(22, 84, 241), rgb(22, 84, 241))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-80 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(22, 84, 241) 0%, rgb(22, 84, 241) 25%,rgb(49, 120, 242) 25%, rgb(49, 120, 242) 50%,rgb(77, 155, 242) 50%, rgb(77, 155, 242) 75%,rgb(104, 191, 243) 75%, rgb(104, 191, 243) 100%); }',
    'js' => NULL,
  ),
  121 => 
  array (
    'name' => 'Abstract Blend 81',
    'slug' => 'classic-abstract-81',
    'preview_color' => 'linear-gradient(135deg, rgb(171, 83, 229), rgb(171, 83, 229))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-81 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0deg, rgb(171, 83, 229) 0%, rgb(171, 83, 229) 33.333333333333336%,rgb(197, 95, 212) 33.333333333333336%, rgb(197, 95, 212) 66.66666666666667%,rgb(222, 106, 194) 66.66666666666667%, rgb(222, 106, 194) 100%); }',
    'js' => NULL,
  ),
  122 => 
  array (
    'name' => 'Abstract Blend 82',
    'slug' => 'classic-abstract-82',
    'preview_color' => 'linear-gradient(135deg, rgb(9, 92, 76), rgb(9, 92, 76))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-82 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, rgb(9, 92, 76) 0%, rgb(9, 92, 76) 25%,rgb(76, 136, 82) 25%, rgb(76, 136, 82) 50%,rgb(142, 179, 89) 50%, rgb(142, 179, 89) 75%,rgb(209, 223, 95) 75%, rgb(209, 223, 95) 100%); }',
    'js' => NULL,
  ),
  123 => 
  array (
    'name' => 'Abstract Blend 83',
    'slug' => 'classic-abstract-83',
    'preview_color' => 'linear-gradient(135deg, rgba(234, 234, 234, 0.13), rgba(234, 234, 234, 0.13))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-83 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0, rgba(234, 234, 234, 0.13) 0%, rgba(234, 234, 234, 0.13) 25%,rgba(190, 190, 190, 0.13) 25%, rgba(190, 190, 190, 0.13) 50%,rgba(147, 147, 147, 0.13) 50%, rgba(147, 147, 147, 0.13) 75%,rgba(103, 103, 103, 0.13) 75%, rgba(103, 103, 103, 0.13) 100%),linear-gradient(135deg, rgb(13, 4, 178) 0%, rgb(13, 4, 178) 12.5%,rgb(17, 25, 182) 12.5%, rgb(17, 25, 182) 25%,rgb(20, 46, 185) 25%, rgb(20, 46, 185) 37.5%,rgb(24, 67, 189) 37.5%, rgb(24, 67, 189) 50%,rgb(28, 87, 192) 50%, rgb(28, 87, 192) 62.5%,rgb(32, 108, 196) 62.5%, rgb(32, 108, 196) 75%,rgb(35, 129, 199) 75%, rgb(35, 129, 199) 87.5%,rgb(39, 150, 203) 87.5%, rgb(39, 150, 203) 100%); }',
    'js' => NULL,
  ),
  124 => 
  array (
    'name' => 'Abstract Blend 84',
    'slug' => 'classic-abstract-84',
    'preview_color' => 'linear-gradient(135deg, rgba(133, 133, 133, 0.52), rgba(133, 133, 133, 0.52))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-84 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(0, rgba(133, 133, 133, 0.52) 0%, rgba(133, 133, 133, 0.52) 34%,rgba(96, 96, 96, 0.52) 34%, rgba(96, 96, 96, 0.52) 69%,rgba(59, 59, 59, 0.52) 69%, rgba(59, 59, 59, 0.52) 100%),linear-gradient(90deg, rgb(4, 222, 252) 0%, rgb(4, 222, 252) 12.5%,rgb(17, 194, 235) 12.5%, rgb(17, 194, 235) 25%,rgb(30, 165, 219) 25%, rgb(30, 165, 219) 37.5%,rgb(43, 137, 202) 37.5%, rgb(43, 137, 202) 50%,rgb(57, 108, 185) 50%, rgb(57, 108, 185) 62.5%,rgb(70, 80, 168) 62.5%, rgb(70, 80, 168) 75%,rgb(83, 51, 152) 75%, rgb(83, 51, 152) 87.5%,rgb(96, 23, 135) 87.5%, rgb(96, 23, 135) 100%); }',
    'js' => NULL,
  ),
  125 => 
  array (
    'name' => 'Abstract Blend 85',
    'slug' => 'classic-abstract-85',
    'preview_color' => 'linear-gradient(135deg, rgb(236, 204, 140), rgb(236, 204, 140))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-85 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(236, 204, 140) 0%, rgb(236, 204, 140) 11%,rgb(212, 161, 111) 11%, rgb(212, 161, 111) 24%,rgb(188, 117, 83) 24%, rgb(188, 117, 83) 29%,rgb(164, 74, 54) 29%, rgb(164, 74, 54) 33%,rgb(140, 30, 25) 33%, rgb(140, 30, 25) 100%); }',
    'js' => NULL,
  ),
  126 => 
  array (
    'name' => 'Abstract Blend 86',
    'slug' => 'classic-abstract-86',
    'preview_color' => 'linear-gradient(135deg, rgb(20, 59, 44), rgb(20, 59, 44))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-86 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(20, 59, 44) 0%, rgb(20, 59, 44) 62%,rgb(72, 77, 92) 62%, rgb(72, 77, 92) 69%,rgb(124, 94, 140) 69%, rgb(124, 94, 140) 76%,rgb(176, 112, 188) 76%, rgb(176, 112, 188) 88%,rgb(228, 129, 236) 88%, rgb(228, 129, 236) 100%); }',
    'js' => NULL,
  ),
  127 => 
  array (
    'name' => 'Abstract Blend 87',
    'slug' => 'classic-abstract-87',
    'preview_color' => 'linear-gradient(135deg, rgb(86, 95, 151), rgb(86, 95, 151))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-87 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(86, 95, 151) 0%, rgb(86, 95, 151) 63%,rgb(105, 118, 165) 63%, rgb(105, 118, 165) 75%,rgb(125, 141, 179) 75%, rgb(125, 141, 179) 81%,rgb(144, 165, 193) 81%, rgb(144, 165, 193) 85%,rgb(164, 188, 207) 85%, rgb(164, 188, 207) 90%,rgb(183, 211, 221) 90%, rgb(183, 211, 221) 100%); }',
    'js' => NULL,
  ),
  128 => 
  array (
    'name' => 'Abstract Blend 88',
    'slug' => 'classic-abstract-88',
    'preview_color' => 'linear-gradient(135deg, rgb(254, 101, 156), rgb(254, 101, 156))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-88 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(254, 101, 156) 0%, rgb(254, 101, 156) 10%,rgb(204, 87, 133) 10%, rgb(204, 87, 133) 45%,rgb(155, 73, 110) 45%, rgb(155, 73, 110) 70%,rgb(105, 59, 88) 70%, rgb(105, 59, 88) 81%,rgb(56, 45, 65) 81%, rgb(56, 45, 65) 88%,rgb(6, 31, 42) 88%, rgb(6, 31, 42) 100%); }',
    'js' => NULL,
  ),
  129 => 
  array (
    'name' => 'Abstract Blend 89',
    'slug' => 'classic-abstract-89',
    'preview_color' => 'linear-gradient(135deg, rgb(59, 72, 175), rgb(59, 72, 175))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-89 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, rgb(59, 72, 175) 0%, rgb(59, 72, 175) 31%,rgb(55, 66, 150) 31%, rgb(55, 66, 150) 46%,rgb(51, 60, 126) 46%, rgb(51, 60, 126) 56%,rgb(48, 54, 101) 56%, rgb(48, 54, 101) 83%,rgb(44, 48, 77) 83%, rgb(44, 48, 77) 93%,rgb(40, 42, 52) 93%, rgb(40, 42, 52) 100%); }',
    'js' => NULL,
  ),
  130 => 
  array (
    'name' => 'Abstract Blend 90',
    'slug' => 'classic-abstract-90',
    'preview_color' => 'linear-gradient(135deg, rgb(155, 35, 75), rgb(155, 35, 75))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-90 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(155, 35, 75) 0%, rgb(155, 35, 75) 15%,rgb(96, 20, 34) 15%, rgb(96, 20, 34) 24%,rgb(67, 12, 13) 24%, rgb(67, 12, 13) 59%,rgb(214, 50, 116) 59%, rgb(214, 50, 116) 69%,rgb(185, 42, 95) 69%, rgb(185, 42, 95) 99%,rgb(126, 27, 54) 99%, rgb(126, 27, 54) 100%); }',
    'js' => NULL,
  ),
  131 => 
  array (
    'name' => 'Abstract Blend 91',
    'slug' => 'classic-abstract-91',
    'preview_color' => 'linear-gradient(135deg, rgb(238, 137, 17), rgb(238, 137, 17))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-91 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(238, 137, 17) 0%, rgb(238, 137, 17) 33%,rgb(212, 115, 15) 33%, rgb(212, 115, 15) 34%,rgb(187, 93, 13) 34%, rgb(187, 93, 13) 42%,rgb(161, 71, 11) 42%, rgb(161, 71, 11) 56%,rgb(135, 49, 9) 56%, rgb(135, 49, 9) 67%,rgb(110, 27, 7) 67%, rgb(110, 27, 7) 99%,rgb(84, 5, 5) 99%, rgb(84, 5, 5) 100%); }',
    'js' => NULL,
  ),
  132 => 
  array (
    'name' => 'Abstract Blend 92',
    'slug' => 'classic-abstract-92',
    'preview_color' => 'linear-gradient(135deg, rgb(191, 233, 193), rgb(191, 233, 193))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-92 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(135deg, rgb(191, 233, 193) 0%, rgb(191, 233, 193) 1%,rgb(194, 206, 183) 1%, rgb(194, 206, 183) 56%,rgb(197, 180, 173) 56%, rgb(197, 180, 173) 75%,rgb(201, 153, 163) 75%, rgb(201, 153, 163) 80%,rgb(204, 126, 152) 80%, rgb(204, 126, 152) 87%,rgb(207, 100, 142) 87%, rgb(207, 100, 142) 97%,rgb(210, 73, 132) 97%, rgb(210, 73, 132) 100%); }',
    'js' => NULL,
  ),
  133 => 
  array (
    'name' => 'Abstract Blend 93',
    'slug' => 'classic-abstract-93',
    'preview_color' => 'linear-gradient(135deg, rgb(113, 91, 62), rgb(113, 91, 62))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-93 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(113, 91, 62) 0%, rgb(113, 91, 62) 70%,rgb(212, 178, 123) 70%, rgb(212, 178, 123) 71%,rgb(149, 113, 71) 71%, rgb(149, 113, 71) 77%,rgb(187, 138, 45) 77%, rgb(187, 138, 45) 87%,rgb(198, 166, 110) 87%, rgb(198, 166, 110) 96%,rgb(139, 103, 46) 96%, rgb(139, 103, 46) 100%); }',
    'js' => NULL,
  ),
  134 => 
  array (
    'name' => 'Abstract Blend 94',
    'slug' => 'classic-abstract-94',
    'preview_color' => 'linear-gradient(135deg, rgb(64, 140, 190), rgb(64, 140, 190))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-94 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(45deg, rgb(64, 140, 190) 0%, rgb(64, 140, 190) 7%,rgb(62, 107, 145) 7%, rgb(62, 107, 145) 9%,rgb(49, 99, 131) 9%, rgb(49, 99, 131) 11%,rgb(116, 172, 211) 11%, rgb(116, 172, 211) 26%,rgb(125, 182, 214) 26%, rgb(125, 182, 214) 34%,rgb(40, 90, 136) 34%, rgb(40, 90, 136) 41%,rgb(39, 123, 190) 41%, rgb(39, 123, 190) 100%); }',
    'js' => NULL,
  ),
  135 => 
  array (
    'name' => 'Abstract Blend 95',
    'slug' => 'classic-abstract-95',
    'preview_color' => 'linear-gradient(135deg, hsla(175,74%,62%, 1), hsla(175,74%,62%, 1))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-95 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, hsla(175,74%,62%, 1) 0%, hsla(175,74%,62%, 1) 17%,hsla(220,74%,62%, 1) 17%, hsla(220,74%,62%, 1) 27%,hsla(265,74%,62%, 1) 27%, hsla(265,74%,62%, 1) 47%,hsla(310,74%,62%, 1) 47%, hsla(310,74%,62%, 1) 51%,hsla(355,74%,62%, 1) 51%, hsla(355,74%,62%, 1) 64%,hsla(40,74%,62%, 1) 64%, hsla(40,74%,62%, 1) 82%,hsla(85,74%,62%, 1) 82%, hsla(85,74%,62%, 1) 96%,hsla(130,74%,62%, 1) 96%, hsla(130,74%,62%, 1) 100%); }',
    'js' => NULL,
  ),
  136 => 
  array (
    'name' => 'Abstract Blend 96',
    'slug' => 'classic-abstract-96',
    'preview_color' => 'linear-gradient(135deg, rgba(178, 178, 178, 0.07), rgba(178, 178, 178, 0.07))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-96 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(90deg, rgba(178, 178, 178, 0.07) 0px, rgba(178, 178, 178, 0.07) 29px,rgba(5, 5, 5, 0.07) 29px, rgba(5, 5, 5, 0.07) 69px,rgba(146, 146, 146, 0.07) 69px, rgba(146, 146, 146, 0.07) 111px,rgba(141, 141, 141, 0.07) 111px, rgba(141, 141, 141, 0.07) 122px,rgba(86, 86, 86, 0.07) 122px, rgba(86, 86, 86, 0.07) 145px),repeating-linear-gradient(90deg, rgba(57, 57, 57, 0.07) 0px, rgba(57, 57, 57, 0.07) 32px,rgba(249, 249, 249, 0.07) 32px, rgba(249, 249, 249, 0.07) 80px,rgba(47, 47, 47, 0.07) 80px, rgba(47, 47, 47, 0.07) 95px,rgba(95, 95, 95, 0.07) 95px, rgba(95, 95, 95, 0.07) 133px,rgba(34, 34, 34, 0.07) 133px, rgba(34, 34, 34, 0.07) 168px),repeating-linear-gradient(90deg, rgba(22, 22, 22, 0.1) 0px, rgba(22, 22, 22, 0.1) 147px,rgba(12, 12, 12, 0.1) 147px, rgba(12, 12, 12, 0.1) 244px,rgba(22, 22, 22, 0.1) 244px, rgba(22, 22, 22, 0.1) 325px,rgba(46, 46, 46, 0.1) 325px, rgba(46, 46, 46, 0.1) 429px,rgba(179, 179, 179, 0.1) 429px, rgba(179, 179, 179, 0.1) 572px),repeating-linear-gradient(90deg, rgba(126, 126, 126, 0.1) 0px, rgba(126, 126, 126, 0.1) 82px,rgba(22, 22, 22, 0.1) 82px, rgba(22, 22, 22, 0.1) 150px,rgba(0, 0, 0, 0.1) 150px, rgba(0, 0, 0, 0.1) 240px,rgba(124, 124, 124, 0.1) 240px, rgba(124, 124, 124, 0.1) 374px,rgba(2, 2, 2, 0.1) 374px, rgba(2, 2, 2, 0.1) 435px),linear-gradient(90deg, rgb(191, 34, 216),rgb(34, 13, 81)); }',
    'js' => NULL,
  ),
  137 => 
  array (
    'name' => 'Abstract Blend 97',
    'slug' => 'classic-abstract-97',
    'preview_color' => 'linear-gradient(135deg, rgba(118, 118, 118, 0.05), rgba(118, 118, 118, 0.05))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-97 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(45deg, rgba(118, 118, 118, 0.05) 0px, rgba(118, 118, 118, 0.05) 19px,rgba(59, 59, 59, 0.05) 19px, rgba(59, 59, 59, 0.05) 67px,rgba(195, 195, 195, 0.05) 67px, rgba(195, 195, 195, 0.05) 87px,rgba(121, 121, 121, 0.05) 87px, rgba(121, 121, 121, 0.05) 133px,rgba(250, 250, 250, 0.05) 133px, rgba(250, 250, 250, 0.05) 172px,rgba(106, 106, 106, 0.05) 172px, rgba(106, 106, 106, 0.05) 197px,rgba(151, 151, 151, 0.05) 197px, rgba(151, 151, 151, 0.05) 226px,rgba(219, 219, 219, 0.05) 226px, rgba(219, 219, 219, 0.05) 260px),repeating-linear-gradient(45deg, rgba(70, 70, 70, 0.05) 0px, rgba(70, 70, 70, 0.05) 40px,rgba(220, 220, 220, 0.05) 40px, rgba(220, 220, 220, 0.05) 79px,rgba(95, 95, 95, 0.05) 79px, rgba(95, 95, 95, 0.05) 103px,rgba(15, 15, 15, 0.05) 103px, rgba(15, 15, 15, 0.05) 148px,rgba(51, 51, 51, 0.05) 148px, rgba(51, 51, 51, 0.05) 186px,rgba(225, 225, 225, 0.05) 186px, rgba(225, 225, 225, 0.05) 202px,rgba(60, 60, 60, 0.05) 202px, rgba(60, 60, 60, 0.05) 239px,rgba(67, 67, 67, 0.05) 239px, rgba(67, 67, 67, 0.05) 259px),repeating-linear-gradient(45deg, rgba(146, 146, 146, 0.05) 0px, rgba(146, 146, 146, 0.05) 40px,rgba(166, 166, 166, 0.05) 40px, rgba(166, 166, 166, 0.05) 54px,rgba(156, 156, 156, 0.05) 54px, rgba(156, 156, 156, 0.05) 71px,rgba(134, 134, 134, 0.05) 71px, rgba(134, 134, 134, 0.05) 95px,rgba(77, 77, 77, 0.05) 95px, rgba(77, 77, 77, 0.05) 111px,rgba(26, 26, 26, 0.05) 111px, rgba(26, 26, 26, 0.05) 153px,rgba(46, 46, 46, 0.05) 153px, rgba(46, 46, 46, 0.05) 202px,rgba(197, 197, 197, 0.05) 202px, rgba(197, 197, 197, 0.05) 216px),linear-gradient(90deg, rgb(30, 178, 248),rgb(46, 36, 197)); }',
    'js' => NULL,
  ),
  138 => 
  array (
    'name' => 'Abstract Blend 98',
    'slug' => 'classic-abstract-98',
    'preview_color' => 'linear-gradient(135deg, rgba(178, 178, 178, 0.07), rgba(178, 178, 178, 0.07))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-98 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(90deg, rgba(178, 178, 178, 0.07) 0px, rgba(178, 178, 178, 0.07) 29px,rgba(5, 5, 5, 0.07) 29px, rgba(5, 5, 5, 0.07) 69px,rgba(146, 146, 146, 0.07) 69px, rgba(146, 146, 146, 0.07) 111px,rgba(141, 141, 141, 0.07) 111px, rgba(141, 141, 141, 0.07) 122px,rgba(86, 86, 86, 0.07) 122px, rgba(86, 86, 86, 0.07) 145px),repeating-linear-gradient(90deg, rgba(57, 57, 57, 0.07) 0px, rgba(57, 57, 57, 0.07) 32px,rgba(249, 249, 249, 0.07) 32px, rgba(249, 249, 249, 0.07) 80px,rgba(47, 47, 47, 0.07) 80px, rgba(47, 47, 47, 0.07) 95px,rgba(95, 95, 95, 0.07) 95px, rgba(95, 95, 95, 0.07) 133px,rgba(34, 34, 34, 0.07) 133px, rgba(34, 34, 34, 0.07) 168px),repeating-linear-gradient(90deg, rgba(22, 22, 22, 0.1) 0px, rgba(22, 22, 22, 0.1) 147px,rgba(12, 12, 12, 0.1) 147px, rgba(12, 12, 12, 0.1) 244px,rgba(22, 22, 22, 0.1) 244px, rgba(22, 22, 22, 0.1) 325px,rgba(46, 46, 46, 0.1) 325px, rgba(46, 46, 46, 0.1) 429px,rgba(179, 179, 179, 0.1) 429px, rgba(179, 179, 179, 0.1) 572px),repeating-linear-gradient(90deg, rgba(126, 126, 126, 0.1) 0px, rgba(126, 126, 126, 0.1) 82px,rgba(22, 22, 22, 0.1) 82px, rgba(22, 22, 22, 0.1) 150px,rgba(0, 0, 0, 0.1) 150px, rgba(0, 0, 0, 0.1) 240px,rgba(124, 124, 124, 0.1) 240px, rgba(124, 124, 124, 0.1) 374px,rgba(2, 2, 2, 0.1) 374px, rgba(2, 2, 2, 0.1) 435px),linear-gradient(90deg, rgb(191, 34, 216),rgb(34, 13, 81)); }',
    'js' => NULL,
  ),
  139 => 
  array (
    'name' => 'Abstract Blend 99',
    'slug' => 'classic-abstract-99',
    'preview_color' => 'linear-gradient(135deg, hsla(264,0%,88%,0.03), hsla(264,0%,88%,0.03))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-99 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(135deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(45deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(67.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(135deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(45deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(112.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(112.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(45deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(22.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(45deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(22.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(135deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(157.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(67.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(67.5deg, hsla(264,0%,88%,0.03) 0px, hsla(264,0%,88%,0.03) 1px,transparent 1px, transparent 12px),linear-gradient(90deg, rgb(151, 26, 210),rgb(57, 199, 205)); }',
    'js' => NULL,
  ),
  140 => 
  array (
    'name' => 'Abstract Blend 100',
    'slug' => 'classic-abstract-100',
    'preview_color' => 'linear-gradient(135deg, hsla(253,0%,98%,0.03), hsla(253,0%,98%,0.03))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-100 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(45deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(112.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(22.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(67.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(45deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(157.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(112.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(90deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(90deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(135deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(67.5deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(135deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),repeating-linear-gradient(90deg, hsla(253,0%,98%,0.03) 0px, hsla(253,0%,98%,0.03) 1px,transparent 1px, transparent 12px),linear-gradient(90deg, rgb(13, 8, 66),rgb(230, 168, 209)); }',
    'js' => NULL,
  ),
  141 => 
  array (
    'name' => 'Abstract Blend 101',
    'slug' => 'classic-abstract-101',
    'preview_color' => 'linear-gradient(135deg, #EA858A, #EA858A)',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-101 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, transparent 0%, transparent 10%, #EA858A 10%, #EA858A calc(10% + 3px), transparent calc(10% + 3px), transparent 100%), linear-gradient(180deg, white 10%, transparent 10%), repeating-linear-gradient(180deg, #91C1E1 0px, #91C1E1 2px, transparent 2px, transparent 38px), linear-gradient(white,white); }',
    'js' => NULL,
  ),
  142 => 
  array (
    'name' => 'Abstract Blend 102',
    'slug' => 'classic-abstract-102',
    'preview_color' => 'linear-gradient(135deg, rgb(243,197,147), rgb(243,197,147))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-102 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(90deg, transparent 0%, transparent 12%, rgb(243,197,147) 12%, rgb(243,197,147) calc(12% + 2px), transparent calc(12% + 2px), transparent calc(12% + 6px), rgb(243,197,147) calc(12% + 6px), rgb(243,197,147) calc(12% + 8px), transparent calc(12% + 8px), transparent 100%), linear-gradient(180deg, rgb(251,238,159) 12%, transparent 12%), repeating-linear-gradient(180deg, rgb(201,215,152) 0px, rgb(201,215,152) 2px, transparent 2px, transparent 32px), linear-gradient(rgb(251,238,159),rgb(251,238,159)); }',
    'js' => NULL,
  ),
  143 => 
  array (
    'name' => 'Abstract Blend 103',
    'slug' => 'classic-abstract-103',
    'preview_color' => 'linear-gradient(135deg, rgba(37,37,37,0.2), rgba(37,37,37,0.2))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-103 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(296deg, rgba(37,37,37,0.2) 0%,transparent 15%),linear-gradient(353deg, rgba(37,37,37,0.2) 0%,transparent 29%),linear-gradient(62deg, rgba(37,37,37,0.2) 0%,transparent 24%),linear-gradient(194deg, rgba(37,37,37,0.2) 0%,transparent 42%),linear-gradient(270deg, rgba(60,60,60,0.95) 0%,transparent 1%),linear-gradient(90deg, rgba(56,56,56,0.95) 0%,transparent 1%),repeating-linear-gradient(220deg, rgba(140,140,140,0.1) 0px,transparent 4px),repeating-linear-gradient(298deg, rgba(140,140,140,0.1) 0px,transparent 4px),repeating-linear-gradient(312deg, rgba(140,140,140,0.1) 0px,transparent 4px),linear-gradient(90deg, rgb(241,190,105),rgb(241,190,105)); }',
    'js' => NULL,
  ),
  144 => 
  array (
    'name' => 'Abstract Blend 104',
    'slug' => 'classic-abstract-104',
    'preview_color' => 'linear-gradient(135deg, rgb(243,241,236), rgb(138,193,207))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-104 { position:fixed; inset:0; z-index:-1; background-image: linear-gradient(180deg, rgb(243,241,236) 13%, transparent 13%), repeating-linear-gradient(180deg, rgb(138,193,207) 0px, rgb(138,193,207) 2px, transparent 2px, transparent 38px), linear-gradient(rgb(243,241,236),rgb(243,241,236)); }',
    'js' => NULL,
  ),
  145 => 
  array (
    'name' => 'Abstract Blend 105',
    'slug' => 'classic-abstract-105',
    'preview_color' => 'linear-gradient(135deg, rgb(153,197,206), rgb(153,197,206))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-105 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(0deg, rgb(153,197,206) 0px, rgb(153,197,206) 1px, transparent 1px, transparent 30px), repeating-linear-gradient(0deg, rgb(153,197,206) 0px, rgb(153,197,206) 2px, transparent 2px, transparent 150px), repeating-linear-gradient(90deg, rgb(153,197,206) 0px, rgb(153,197,206) 1px, transparent 1px, transparent 30px),repeating-linear-gradient(90deg, rgb(153,197,206) 0px, rgb(153,197,206) 2px, transparent 2px, transparent 150px), linear-gradient(white, white); }',
    'js' => NULL,
  ),
  146 => 
  array (
    'name' => 'Abstract Blend 106',
    'slug' => 'classic-abstract-106',
    'preview_color' => 'linear-gradient(135deg, rgb(56,56,56), rgb(56,56,56))',
    'category' => 'mesh',
    'css' => '.bg-template-classic-abstract-106 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle 7px at 23% 62%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 7px at 25% 58%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 7px at 57% 71%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 5px at 65% 94%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 5px at 22% 15%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 5px at 22% 76%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 5px at 66% 78%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 5px at 12% 17%, rgb(56,56,56) 0%, rgb(56,56,56) 50%,transparent 50%, transparent 100%),radial-gradient(circle 12px at 73% 76%, rgb(43,43,43) 0%, rgb(43,43,43) 50%,transparent 50%, transparent 100%),radial-gradient(circle 12px at 84% 91%, rgb(43,43,43) 0%, rgb(43,43,43) 50%,transparent 50%, transparent 100%),radial-gradient(circle at bottom left, rgb(35,35,35) 0%, rgb(35,35,35) 10%,transparent 10%, transparent 90%),linear-gradient(135deg, rgb(28, 37, 233),rgb(61, 215, 224)); }',
    'js' => NULL,
  ),
  147 => 
  array (
    'name' => 'Abstract Blend 107',
    'slug' => 'classic-abstract-107',
    'preview_color' => 'linear-gradient(135deg, rgb(255,250,85), rgb(201,242,255))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-107 { position:fixed; inset:0; z-index:-1; background-image: radial-gradient(circle 8px at 97% 98%, rgb(255,250,85) 0%,transparent 19%),radial-gradient(circle 2px at 96% 79%, rgb(201,242,255) 0%,transparent 77%),radial-gradient(circle 2px at 97% 4%, rgb(201,242,255) 0%,transparent 3%),radial-gradient(circle 2px at 6% 95%, rgb(201,242,255) 0%,transparent 82%),radial-gradient(circle 2px at 17% 63%, rgb(201,242,255) 0%,transparent 49%),radial-gradient(circle 2px at 93% 22%, rgb(201,242,255) 0%,transparent 77%),radial-gradient(circle 5px at 31% 29%, rgb(253,250,147) 0%,transparent 61%),radial-gradient(circle 5px at 40% 87%, rgb(253,250,147) 0%,transparent 38%),radial-gradient(circle 4px at 43% 44%, rgb(255,249,27) 0%,transparent 47%),radial-gradient(circle 4px at 60% 58%, rgb(255,249,27) 0%,transparent 9%),radial-gradient(circle 4px at 84% 48%, rgb(255,249,27) 0%,transparent 22%),radial-gradient(circle 4px at 19% 54%, rgb(255,249,27) 0%,transparent 63%),radial-gradient(circle 4px at 73% 35%, rgb(255,249,27) 0%,transparent 5%),linear-gradient(90deg, rgb(35,35,35) 0%, rgb(35,35,35) 50%,rgb(35,35,35) 50%, rgb(35,35,35) 100%); background-size: 350px 350px; }',
    'js' => NULL,
  ),
  148 => 
  array (
    'name' => 'Abstract Blend 108',
    'slug' => 'classic-abstract-108',
    'preview_color' => 'linear-gradient(135deg, rgb(198, 6, 79), rgb(210, 240, 47))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-108 { position:fixed; inset:0; z-index:-1; background-image: repeating-radial-gradient(circle at 28% 17%, rgb(198, 6, 79),rgb(210, 240, 47) 2px); }',
    'js' => NULL,
  ),
  149 => 
  array (
    'name' => 'Abstract Blend 109',
    'slug' => 'classic-abstract-109',
    'preview_color' => 'linear-gradient(135deg, rgba(0,0,0,0.03), rgba(0,0,0,0.03))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-109 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(148deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 12px,transparent 12px, transparent 13px,rgba(0,0,0,0.03) 13px, rgba(0,0,0,0.03) 18px,transparent 18px, transparent 26px),repeating-linear-gradient(83deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 6px,transparent 6px, transparent 14px,rgba(0,0,0,0.03) 14px, rgba(0,0,0,0.03) 26px,transparent 26px, transparent 38px),repeating-linear-gradient(325deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 12px,transparent 12px, transparent 15px,rgba(0,0,0,0.03) 15px, rgba(0,0,0,0.03) 20px,transparent 20px, transparent 30px),repeating-linear-gradient(148deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 5px,transparent 5px, transparent 7px,rgba(0,0,0,0.03) 7px, rgba(0,0,0,0.03) 12px,transparent 12px, transparent 23px),repeating-linear-gradient(330deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 6px,transparent 6px, transparent 17px,rgba(0,0,0,0.03) 17px, rgba(0,0,0,0.03) 28px,transparent 28px, transparent 29px),repeating-linear-gradient(142deg, rgba(0,0,0,0.03) 0px, rgba(0,0,0,0.03) 6px,transparent 6px, transparent 7px,rgba(0,0,0,0.03) 7px, rgba(0,0,0,0.03) 12px,transparent 12px, transparent 21px),linear-gradient(90deg, hsl(124,0%,91%),hsl(124,0%,91%)); }',
    'js' => NULL,
  ),
  150 => 
  array (
    'name' => 'Abstract Blend 110',
    'slug' => 'classic-abstract-110',
    'preview_color' => 'linear-gradient(135deg, rgba(13, 13, 13,0.09), rgba(13, 13, 13,0.09))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-110 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(90deg, rgba(13, 13, 13,0.09) 0px, rgba(13, 13, 13,0.09) 36px,rgba(229, 229, 229,0.09) 36px, rgba(229, 229, 229,0.09) 72px,transparent 72px, transparent 108px,rgba(163, 163, 163,0.09) 108px, rgba(163, 163, 163,0.09) 144px,rgba(21, 21, 21,0.09) 144px, rgba(21, 21, 21,0.09) 180px),repeating-linear-gradient(90deg, rgba(0,0,0,0.08) 0px, rgba(0,0,0,0.08) 14px,transparent 14px, transparent 28px,rgba(0,0,0,0.08) 28px, rgba(0,0,0,0.08) 42px,transparent 42px, transparent 56px,rgba(0,0,0,0.08) 56px, rgba(0,0,0,0.08) 70px),repeating-linear-gradient(90deg, rgba(0,0,0,0.08) 0px, rgba(0,0,0,0.08) 23px,transparent 23px, transparent 46px,rgba(0,0,0,0.08) 46px, rgba(0,0,0,0.08) 69px,transparent 69px, transparent 92px,rgba(0,0,0,0.08) 92px, rgba(0,0,0,0.08) 115px),repeating-linear-gradient(90deg, rgba(0,0,0,0.04) 0px, rgba(0,0,0,0.04) 6px,transparent 6px, transparent 12px,rgba(0,0,0,0.04) 12px, rgba(0,0,0,0.04) 18px,transparent 18px, transparent 24px,rgba(0,0,0,0.04) 24px, rgba(0,0,0,0.04) 30px),linear-gradient(90deg, rgb(212, 109, 30),rgb(176, 117, 193)); }',
    'js' => NULL,
  ),
  151 => 
  array (
    'name' => 'Abstract Blend 111',
    'slug' => 'classic-abstract-111',
    'preview_color' => 'linear-gradient(135deg, rgb(255,255,255), rgb(255,255,255))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-111 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(105deg, transparent 0px, transparent 3px,rgb(255,255,255) 3px, rgb(255,255,255) 28px),repeating-linear-gradient(333deg, transparent 0px, transparent 3px,rgb(255,255,255) 3px, rgb(255,255,255) 28px),linear-gradient(90deg, hsl(300,76%,69%),hsl(351.429,76%,69%),hsl(42.857,76%,69%),hsl(94.286,76%,69%),hsl(145.714,76%,69%),hsl(197.143,76%,69%),hsl(248.571,76%,69%)); }',
    'js' => NULL,
  ),
  152 => 
  array (
    'name' => 'Abstract Blend 112',
    'slug' => 'classic-abstract-112',
    'preview_color' => 'linear-gradient(135deg, rgb(255,255,255), rgb(255,255,255))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-112 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(277deg, rgb(255,255,255) 0px, rgb(255,255,255) 25px,transparent 25px, transparent 28px),repeating-linear-gradient(337deg, rgb(255,255,255) 0px, rgb(255,255,255) 25px,transparent 25px, transparent 28px),repeating-linear-gradient(16deg, rgb(255,255,255) 0px, rgb(255,255,255) 25px,transparent 25px, transparent 28px),linear-gradient(90deg, rgb(254, 60, 95),rgb(167, 18, 174)); }',
    'js' => NULL,
  ),
  153 => 
  array (
    'name' => 'Abstract Blend 113',
    'slug' => 'classic-abstract-113',
    'preview_color' => 'linear-gradient(135deg, rgb(0,0,0), rgb(0,0,0))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-113 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(157.5deg, rgb(0,0,0) 0px, rgb(0,0,0) 20px,transparent 20px, transparent 22px),repeating-linear-gradient(90deg, rgb(0,0,0) 0px, rgb(0,0,0) 20px,transparent 20px, transparent 22px),linear-gradient(90deg, hsl(333,93%,55%),hsl(45,93%,55%),hsl(117,93%,55%),hsl(189,93%,55%),hsl(261,93%,55%)); }',
    'js' => NULL,
  ),
  154 => 
  array (
    'name' => 'Abstract Blend 114',
    'slug' => 'classic-abstract-114',
    'preview_color' => 'linear-gradient(135deg, rgba(0,0,0,0.06), rgba(0,0,0,0.06))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-114 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(45deg, rgba(0,0,0,0.06) 0px, rgba(0,0,0,0.06) 19px,transparent 19px, transparent 38px,rgba(0,0,0,0.06) 38px, rgba(0,0,0,0.06) 57px,rgba(0,0,0,0.25) 57px, rgba(0,0,0,0.25) 76px,rgba(0,0,0,0.12) 76px, rgba(0,0,0,0.12) 95px,rgba(0,0,0,0.03) 95px, rgba(0,0,0,0.03) 114px,rgba(0,0,0,0.26) 114px, rgba(0,0,0,0.26) 133px,rgba(0,0,0,0.23) 133px, rgba(0,0,0,0.23) 152px,transparent 152px, transparent 171px,rgba(0,0,0,0.25) 171px, rgba(0,0,0,0.25) 190px,transparent 190px, transparent 209px,rgba(0,0,0,0.03) 209px, rgba(0,0,0,0.03) 228px,rgba(0,0,0,0.23) 228px, rgba(0,0,0,0.23) 247px,rgba(0,0,0,0.03) 247px, rgba(0,0,0,0.03) 266px),repeating-linear-gradient(135deg, transparent 0px, transparent 3px,rgba(0,0,0,0.09) 3px, rgba(0,0,0,0.09) 6px,rgba(0,0,0,0.03) 6px, rgba(0,0,0,0.03) 9px,rgba(0,0,0,0.09) 9px, rgba(0,0,0,0.09) 12px,rgba(0,0,0,0.09) 12px, rgba(0,0,0,0.09) 15px,rgba(0,0,0,0.06) 15px, rgba(0,0,0,0.06) 18px,rgba(0,0,0,0.01) 18px, rgba(0,0,0,0.01) 21px,rgba(0,0,0,0.02) 21px, rgba(0,0,0,0.02) 24px,transparent 24px, transparent 27px,rgba(0,0,0,0.02) 27px, rgba(0,0,0,0.02) 30px,transparent 30px, transparent 33px,rgba(0,0,0,0.02) 33px, rgba(0,0,0,0.02) 36px,rgba(0,0,0,0.06) 36px, rgba(0,0,0,0.06) 39px,rgba(0,0,0,0.07) 39px, rgba(0,0,0,0.07) 42px,rgba(0,0,0,0.1) 42px, rgba(0,0,0,0.1) 45px,rgba(0,0,0,0.01) 45px, rgba(0,0,0,0.01) 48px,rgba(0,0,0,0.01) 48px, rgba(0,0,0,0.01) 51px,rgba(0,0,0,0.1) 51px, rgba(0,0,0,0.1) 54px),repeating-linear-gradient(90deg, rgba(0,0,0,0.11) 0px, rgba(0,0,0,0.11) 19px,transparent 19px, transparent 38px,rgba(0,0,0,0.16) 38px, rgba(0,0,0,0.16) 57px,rgba(0,0,0,0.17) 57px, rgba(0,0,0,0.17) 76px,rgba(0,0,0,0.29) 76px, rgba(0,0,0,0.29) 95px,rgba(0,0,0,0.26) 95px, rgba(0,0,0,0.26) 114px,rgba(0,0,0,0.28) 114px, rgba(0,0,0,0.28) 133px,rgba(0,0,0,0.22) 133px, rgba(0,0,0,0.22) 152px,transparent 152px, transparent 171px,rgba(0,0,0,0.19) 171px, rgba(0,0,0,0.19) 190px,transparent 190px, transparent 209px,rgba(0,0,0,0.29) 209px, rgba(0,0,0,0.29) 228px,rgba(0,0,0,0.29) 228px, rgba(0,0,0,0.29) 247px),repeating-linear-gradient(0deg, rgba(0,0,0,0.29) 0px, rgba(0,0,0,0.29) 19px,transparent 19px, transparent 38px,rgba(0,0,0,0.2) 38px, rgba(0,0,0,0.2) 57px,rgba(0,0,0,0.03) 57px, rgba(0,0,0,0.03) 76px,rgba(0,0,0,0.26) 76px, rgba(0,0,0,0.26) 95px,rgba(0,0,0,0.06) 95px, rgba(0,0,0,0.06) 114px,rgba(0,0,0,0.29) 114px, rgba(0,0,0,0.29) 133px,rgba(0,0,0,0.19) 133px, rgba(0,0,0,0.19) 152px,transparent 152px, transparent 171px,rgba(0,0,0,0.11) 171px, rgba(0,0,0,0.11) 190px,transparent 190px, transparent 209px,rgba(0,0,0,0.1) 209px, rgba(0,0,0,0.1) 228px,rgba(0,0,0,0.04) 228px, rgba(0,0,0,0.04) 247px),linear-gradient(0deg, rgb(162, 223, 27),rgb(6, 172, 66)); }',
    'js' => NULL,
  ),
  155 => 
  array (
    'name' => 'Abstract Blend 115',
    'slug' => 'classic-abstract-115',
    'preview_color' => 'linear-gradient(135deg, rgba(0,0,0,0.3), rgba(0,0,0,0.3))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-115 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(45deg, rgba(0,0,0,0.3) 0px, rgba(0,0,0,0.3) 16px,transparent 16px, transparent 32px,rgba(0,0,0,0.08) 32px, rgba(0,0,0,0.08) 48px,rgba(0,0,0,0.14) 48px, rgba(0,0,0,0.14) 64px,rgba(0,0,0,0.05) 64px, rgba(0,0,0,0.05) 80px,transparent 80px, transparent 96px,rgba(0,0,0,0.21) 96px, rgba(0,0,0,0.21) 112px,rgba(0,0,0,0.18) 112px, rgba(0,0,0,0.18) 128px,rgba(0,0,0,0.21) 128px, rgba(0,0,0,0.21) 144px,rgba(0,0,0,0.29) 144px, rgba(0,0,0,0.29) 160px,rgba(0,0,0,0.08) 160px, rgba(0,0,0,0.08) 176px,rgba(0,0,0,0.3) 176px, rgba(0,0,0,0.3) 192px,rgba(0,0,0,0.23) 192px, rgba(0,0,0,0.23) 208px),repeating-linear-gradient(135deg, transparent 0px, transparent 3px,rgba(0,0,0,0.1) 3px, rgba(0,0,0,0.1) 6px,rgba(0,0,0,0.03) 6px, rgba(0,0,0,0.03) 9px,rgba(0,0,0,0.09) 9px, rgba(0,0,0,0.09) 12px,rgba(0,0,0,0.08) 12px, rgba(0,0,0,0.08) 15px,rgba(0,0,0,0.1) 15px, rgba(0,0,0,0.1) 18px,rgba(0,0,0,0.1) 18px, rgba(0,0,0,0.1) 21px,rgba(0,0,0,0.04) 21px, rgba(0,0,0,0.04) 24px,transparent 24px, transparent 27px,rgba(0,0,0,0.03) 27px, rgba(0,0,0,0.03) 30px,rgba(0,0,0,0.03) 30px, rgba(0,0,0,0.03) 33px,rgba(0,0,0,0.01) 33px, rgba(0,0,0,0.01) 36px,rgba(0,0,0,0.1) 36px, rgba(0,0,0,0.1) 39px,rgba(0,0,0,0.06) 39px, rgba(0,0,0,0.06) 42px,transparent 42px, transparent 45px,rgba(0,0,0,0.03) 45px, rgba(0,0,0,0.03) 48px,rgba(0,0,0,0.05) 48px, rgba(0,0,0,0.05) 51px,rgba(0,0,0,0.03) 51px, rgba(0,0,0,0.03) 54px),repeating-linear-gradient(90deg, rgba(0,0,0,0.18) 0px, rgba(0,0,0,0.18) 14px,transparent 14px, transparent 28px,rgba(0,0,0,0.3) 28px, rgba(0,0,0,0.3) 42px,rgba(0,0,0,0.25) 42px, rgba(0,0,0,0.25) 56px,rgba(0,0,0,0.07) 56px, rgba(0,0,0,0.07) 70px,rgba(0,0,0,0.23) 70px, rgba(0,0,0,0.23) 84px,rgba(0,0,0,0.02) 84px, rgba(0,0,0,0.02) 98px,rgba(0,0,0,0.04) 98px, rgba(0,0,0,0.04) 112px,rgba(0,0,0,0.07) 112px, rgba(0,0,0,0.07) 126px,rgba(0,0,0,0.21) 126px, rgba(0,0,0,0.21) 140px,rgba(0,0,0,0.15) 140px, rgba(0,0,0,0.15) 154px,transparent 154px, transparent 168px,rgba(0,0,0,0.12) 168px, rgba(0,0,0,0.12) 182px,rgba(0,0,0,0.13) 182px, rgba(0,0,0,0.13) 196px,rgba(0,0,0,0.27) 196px, rgba(0,0,0,0.27) 210px),repeating-linear-gradient(0deg, rgba(0,0,0,0.17) 0px, rgba(0,0,0,0.17) 14px,rgba(0,0,0,0.26) 14px, rgba(0,0,0,0.26) 28px,rgba(0,0,0,0.06) 28px, rgba(0,0,0,0.06) 42px,rgba(0,0,0,0.14) 42px, rgba(0,0,0,0.14) 56px,transparent 56px, transparent 70px,rgba(0,0,0,0.22) 70px, rgba(0,0,0,0.22) 84px,rgba(0,0,0,0.1) 84px, rgba(0,0,0,0.1) 98px,transparent 98px, transparent 112px,rgba(0,0,0,0.15) 112px, rgba(0,0,0,0.15) 126px,transparent 126px, transparent 140px,rgba(0,0,0,0.03) 140px, rgba(0,0,0,0.03) 154px,rgba(0,0,0,0.03) 154px, rgba(0,0,0,0.03) 168px,rgba(0,0,0,0.06) 168px, rgba(0,0,0,0.06) 182px,rgba(0,0,0,0.17) 182px, rgba(0,0,0,0.17) 196px,rgba(0,0,0,0.2) 196px, rgba(0,0,0,0.2) 210px),linear-gradient(135deg, rgb(252, 16, 76),rgb(244, 3, 176)); }',
    'js' => NULL,
  ),
  156 => 
  array (
    'name' => 'Abstract Blend 116',
    'slug' => 'classic-abstract-116',
    'preview_color' => 'linear-gradient(135deg, rgba(0, 0, 0, 0.11), rgba(0, 0, 0, 0.11))',
    'category' => 'pattern',
    'css' => '.bg-template-classic-abstract-116 { position:fixed; inset:0; z-index:-1; background-image: repeating-linear-gradient(0deg, rgba(0, 0, 0, 0.11) 0px, rgba(0, 0, 0, 0.11) 12px, rgba(1, 1, 1, 0.16) 12px, rgba(1, 1, 1, 0.16) 24px, rgba(0, 0, 0, 0.14) 24px, rgba(0, 0, 0, 0.14) 36px, rgba(0, 0, 0, 0.23) 36px, rgba(0, 0, 0, 0.23) 48px, rgba(0, 0, 0, 0.12) 48px, rgba(0, 0, 0, 0.12) 60px, rgba(1, 1, 1, 0.07) 60px, rgba(1, 1, 1, 0.07) 72px, rgba(0, 0, 0, 0.21) 72px, rgba(0, 0, 0, 0.21) 84px, rgba(0, 0, 0, 0.24) 84px, rgba(0, 0, 0, 0.24) 96px, rgba(1, 1, 1, 0.23) 96px, rgba(1, 1, 1, 0.23) 108px, rgba(1, 1, 1, 0.07) 108px, rgba(1, 1, 1, 0.07) 120px, rgba(0, 0, 0, 0.01) 120px, rgba(0, 0, 0, 0.01) 132px, rgba(1, 1, 1, 0.22) 132px, rgba(1, 1, 1, 0.22) 144px, rgba(1, 1, 1, 0.24) 144px, rgba(1, 1, 1, 0.24) 156px, rgba(0, 0, 0, 0) 156px, rgba(0, 0, 0, 0) 168px, rgba(0, 0, 0, 0.12) 168px, rgba(0, 0, 0, 0.12) 180px), repeating-linear-gradient(90deg, rgba(1, 1, 1, 0.01) 0px, rgba(1, 1, 1, 0.01) 12px, rgba(1, 1, 1, 0.15) 12px, rgba(1, 1, 1, 0.15) 24px, rgba(0, 0, 0, 0.09) 24px, rgba(0, 0, 0, 0.09) 36px, rgba(0, 0, 0, 0.02) 36px, rgba(0, 0, 0, 0.02) 48px, rgba(0, 0, 0, 0.1) 48px, rgba(0, 0, 0, 0.1) 60px, rgba(1, 1, 1, 0.07) 60px, rgba(1, 1, 1, 0.07) 72px, rgba(1, 1, 1, 0.15) 72px, rgba(1, 1, 1, 0.15) 84px, rgba(0, 0, 0, 0.18) 84px, rgba(0, 0, 0, 0.18) 96px, rgba(1, 1, 1, 0.15) 96px, rgba(1, 1, 1, 0.15) 108px, rgba(1, 1, 1, 0.09) 108px, rgba(1, 1, 1, 0.09) 120px, rgba(1, 1, 1, 0.07) 120px, rgba(1, 1, 1, 0.07) 132px, rgba(1, 1, 1, 0.05) 132px, rgba(1, 1, 1, 0.05) 144px, rgba(0, 0, 0, 0.1) 144px, rgba(0, 0, 0, 0.1) 156px, rgba(1, 1, 1, 0.18) 156px, rgba(1, 1, 1, 0.18) 168px), repeating-linear-gradient(45deg, rgba(0, 0, 0, 0.24) 0px, rgba(0, 0, 0, 0.24) 16px, rgba(1, 1, 1, 0.06) 16px, rgba(1, 1, 1, 0.06) 32px, rgba(0, 0, 0, 0.16) 32px, rgba(0, 0, 0, 0.16) 48px, rgba(1, 1, 1, 0) 48px, rgba(1, 1, 1, 0) 64px, rgba(1, 1, 1, 0.12) 64px, rgba(1, 1, 1, 0.12) 80px, rgba(1, 1, 1, 0.22) 80px, rgba(1, 1, 1, 0.22) 96px, rgba(0, 0, 0, 0.24) 96px, rgba(0, 0, 0, 0.24) 112px, rgba(0, 0, 0, 0.25) 112px, rgba(0, 0, 0, 0.25) 128px, rgba(1, 1, 1, 0.12) 128px, rgba(1, 1, 1, 0.12) 144px, rgba(0, 0, 0, 0.18) 144px, rgba(0, 0, 0, 0.18) 160px, rgba(1, 1, 1, 0.03) 160px, rgba(1, 1, 1, 0.03) 176px, rgba(1, 1, 1, 0.1) 176px, rgba(1, 1, 1, 0.1) 192px), repeating-linear-gradient(135deg, rgba(1, 1, 1, 0.18) 0px, rgba(1, 1, 1, 0.18) 3px, rgba(0, 0, 0, 0.09) 3px, rgba(0, 0, 0, 0.09) 6px, rgba(0, 0, 0, 0.08) 6px, rgba(0, 0, 0, 0.08) 9px, rgba(1, 1, 1, 0.05) 9px, rgba(1, 1, 1, 0.05) 12px, rgba(0, 0, 0, 0.01) 12px, rgba(0, 0, 0, 0.01) 15px, rgba(1, 1, 1, 0.12) 15px, rgba(1, 1, 1, 0.12) 18px, rgba(0, 0, 0, 0.05) 18px, rgba(0, 0, 0, 0.05) 21px, rgba(1, 1, 1, 0.16) 21px, rgba(1, 1, 1, 0.16) 24px, rgba(1, 1, 1, 0.07) 24px, rgba(1, 1, 1, 0.07) 27px, rgba(1, 1, 1, 0.23) 27px, rgba(1, 1, 1, 0.23) 30px, rgba(0, 0, 0, 0.2) 30px, rgba(0, 0, 0, 0.2) 33px, rgba(0, 0, 0, 0.18) 33px, rgba(0, 0, 0, 0.18) 36px, rgba(1, 1, 1, 0.12) 36px, rgba(1, 1, 1, 0.12) 39px, rgba(1, 1, 1, 0.13) 39px, rgba(1, 1, 1, 0.13) 42px, rgba(1, 1, 1, 0.2) 42px, rgba(1, 1, 1, 0.2) 45px, rgba(1, 1, 1, 0.18) 45px, rgba(1, 1, 1, 0.18) 48px, rgba(0, 0, 0, 0.2) 48px, rgba(0, 0, 0, 0.2) 51px, rgba(1, 1, 1, 0) 51px, rgba(1, 1, 1, 0) 54px, rgba(0, 0, 0, 0.03) 54px, rgba(0, 0, 0, 0.03) 57px, rgba(1, 1, 1, 0.06) 57px, rgba(1, 1, 1, 0.06) 60px, rgba(1, 1, 1, 0) 60px, rgba(1, 1, 1, 0) 63px, rgba(0, 0, 0, 0.1) 63px, rgba(0, 0, 0, 0.1) 66px, rgba(1, 1, 1, 0.19) 66px, rgba(1, 1, 1, 0.19) 69px), linear-gradient(90deg, rgb(239, 53, 115), rgb(79, 2, 93)); }',
    'js' => NULL,
  ),
);
    }
}
