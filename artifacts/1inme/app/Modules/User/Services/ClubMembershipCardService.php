<?php
namespace App\Modules\User\Services;
use App\Modules\User\Models\{Link,ClubMembership,UserFile};
use Illuminate\Support\Facades\{Storage,URL};
class ClubMembershipCardService {
    public const SIZES=['cr80'=>[85.60,53.98],'id2'=>[105,74],'id3'=>[125,88],'badge'=>[70,100]];
    public function design(Link $link): array {
        $s=data_get($link->settings,'membership',[]);
        [$w,$h]=self::SIZES[$s['card_size']??'cr80']??[(float)($s['card_width']??85.6),(float)($s['card_height']??53.98)];
        if((($s['card_layout']??'horizontal')==='vertical' && $w>$h) || (($s['card_layout']??'horizontal')==='horizontal' && $w<$h)) [$w,$h]=[$h,$w];
        $elements=array_replace_recursive($this->defaults(),$s['card_elements']??[]);foreach($elements as &$element) $element['show']=(bool)$element['show'];unset($element);
        return [...$s,'width_mm'=>$w,'height_mm'=>$h,'elements'=>$elements];
    }
    public function defaults(): array {
        return [
            'logo'=>['x'=>5,'y'=>5,'w'=>16,'h'=>13,'font'=>24,'show'=>true],
            'photo'=>['x'=>5,'y'=>25,'w'=>22,'h'=>44,'font'=>40,'show'=>true],
            'title'=>['x'=>31,'y'=>9,'w'=>62,'h'=>8,'font'=>24,'show'=>true],
            'name'=>['x'=>31,'y'=>27,'w'=>62,'h'=>10,'font'=>38,'show'=>true],
            'club'=>['x'=>31,'y'=>40,'w'=>62,'h'=>8,'font'=>23,'show'=>true],
            'number'=>['x'=>31,'y'=>52,'w'=>40,'h'=>8,'font'=>21,'show'=>true],
            'expiry'=>['x'=>31,'y'=>64,'w'=>40,'h'=>8,'font'=>21,'show'=>true],
            'fields'=>['x'=>5,'y'=>77,'w'=>62,'h'=>15,'font'=>18,'show'=>true],
            'footer'=>['x'=>5,'y'=>93,'w'=>88,'h'=>5,'font'=>16,'show'=>true],
            'qr'=>['x'=>75,'y'=>55,'w'=>20,'h'=>34,'font'=>20,'show'=>true],
        ];
    }
    public function image(?int $id, Link $link): string {
        if(!$id) return '';
        $file=UserFile::withoutGlobalScope('workspace')->where('user_id',$link->user_id)->find($id);
        if(!$file || !$file->isScanClean() || !in_array($file->mime_type,['image/jpeg','image/png','image/webp'],true) || $file->size_bytes>8388608) return '';
        try {$bytes=Storage::disk($file->disk)->get($file->path);} catch(\Throwable $e) {return '';}
        return $bytes ? 'data:'.$file->mime_type.';base64,'.base64_encode($bytes) : '';
    }
    public function svg(Link $link, ClubMembership $member): string {
        $d=$this->design($link);$height=1000*$d['height_mm']/$d['width_mm'];$escape=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
        $color=preg_match('/^#[a-f0-9]{6}$/i',$d['card_color']??'')?$d['card_color']:'#4255bc';
        $ink=preg_match('/^#[a-f0-9]{6}$/i',$d['card_text_color']??'')?$d['card_text_color']:'#ffffff';
        $svg='<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="1000" height="'.$height.'" viewBox="0 0 1000 '.$height.'"><rect width="1000" height="'.$height.'" fill="'.$color.'"/>';
        $background=$this->image($d['card_background_file_id']??null,$link);
        if($background) $svg.='<image x="0" y="0" width="1000" height="'.$height.'" preserveAspectRatio="xMidYMid slice" xlink:href="'.$background.'"/><rect width="1000" height="'.$height.'" fill="'.$color.'" opacity="'.((int)($d['card_overlay']??30)/100).'"/>';
        $text=['title'=>$d['card_title']??'Membership card','name'=>$member->name,'club'=>$link->title?:$link->alias,'number'=>'#'.$member->id.' · '.($member->isExpired()?'Expired':ucfirst($member->status)),'expiry'=>'Valid until '.($member->expires_at?->format('d M Y')??'No expiry'),'footer'=>$d['card_footer']??''];
        $custom=[];foreach($d['card_fields']??[] as $key) {$v=data_get($member->custom_fields,$key);$custom[]=$key.': '.(is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE):$v);}$text['fields']=implode(' · ',$custom);
        foreach($d['elements'] as $key=>$e) {
            if(empty($e['show'])) continue;
            $x=$e['x']*10;$y=$e['y']*$height/100;$w=$e['w']*10;$h=$e['h']*$height/100;
            if(in_array($key,['logo','photo'],true)) {
                $src=$this->image($key==='photo'?$member->profile_photo_file_id:($d['card_logo_file_id']??null),$link);
                if($src) $svg.='<image aria-label="'.($key==='photo'?'Member profile picture':'Card logo').'" x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" preserveAspectRatio="'.($key==='logo'?'xMidYMid meet':'xMidYMid slice').'" xlink:href="'.$src.'"/>';
                elseif($key==='photo') $svg.='<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" rx="12" fill="'.$ink.'" opacity=".15"/><text x="'.($x+12).'" y="'.($y+50).'" font-size="40" fill="'.$ink.'">'.$escape(mb_substr($member->name,0,1)).'</text>';
            } elseif($key==='qr') {
                $url=URL::signedRoute('user.links.memberships.pass',['link'=>$link->id,'member'=>$member->id]);
                $qr=(string)\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->encoding('UTF-8')->size(300)->margin(2)->generate($url);
                $qr=preg_replace('/<\?xml.*?\?>/s','',$qr);
                $qr=preg_replace('/^\s*<svg[^>]*>|<\/svg>\s*$/','',$qr);
                $size=min($w,$h);$svg.='<g transform="translate('.$x.' '.$y.') scale('.($size/300).')">'.$qr.'</g>';
            } else {
                $font=(int)$e['font'];$limit=max(1,(int)floor($w/($font*.58)));$value=mb_substr($text[$key]??'',0,$limit);
                $svg.='<text x="'.$x.'" y="'.($y+$font).'" font-family="'.$escape($d['card_font']??'Arial').'" font-size="'.$font.'" font-weight="'.($key==='name'?'700':'400').'" fill="'.$ink.'">'.$escape($value).'</text>';
            }
        }
        return $svg.'</svg>';
    }
}
