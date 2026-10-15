<?php
namespace App\Services\AI;
class CompanionAppearance
{
    public static function variables(array $c): array
    {
        $out=[];
        foreach (['accent','chat','user','assistant'] as $part) {
            $mode=$c[$part.'_mode'] ?? 'default';
            $color=($part==='accent' && $mode==='default') ? ($c['accent'] ?? '#7c3aed') : ($c[$part.'_color'] ?? '');
            if (!preg_match('/^#[0-9a-f]{6}$/i',(string)$color)) continue;
            $background=$color;
            if ($mode==='gradient' && preg_match('/^#[0-9a-f]{6}$/i',(string)($c[$part.'_color_end']??''))) {
                $background='linear-gradient(135deg,'.$color.','.$c[$part.'_color_end'].')';
            } elseif ($mode==='image') {
                $url=(string)($c[$part.'_image']??'');
                if (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true)) continue;
                $url=str_replace(['"',"'",'\\',"\n","\r",'<','>'],['%22','%27','%5C','','','%3C','%3E'],$url);
                $background=$color.' url("'.$url.'") center/cover';
            } elseif ($mode==='default' && $part!=='accent') continue;
            $out['--cmp-'.$part.'-bg']=$background;
            if (preg_match('/^#[0-9a-f]{6}$/i',(string)($c[$part.'_text']??''))) $out['--cmp-'.$part.'-text']=$c[$part.'_text'];
        }
        $out['--cmp-radius']=max(4,min(28,(int)($c['bubble_radius']??14))).'px';
        return $out;
    }
}
