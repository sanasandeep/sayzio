<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class VcfData extends Model
{
    protected $table = 'vcf_data';

    protected $fillable = [
        'link_id', 'first_name', 'last_name', 'organization', 'title',
        'email', 'phone', 'phone_work', 'website',
        'street', 'city', 'state', 'zip', 'country', 'note',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function toVcf(): string
    {
        $vcf = "BEGIN:VCARD\r\n";
        $vcf .= "VERSION:3.0\r\n";
        $vcf .= "N:{$this->last_name};{$this->first_name};;;\r\n";
        $vcf .= "FN:{$this->first_name} {$this->last_name}\r\n";

        if ($this->organization) $vcf .= "ORG:{$this->organization}\r\n";
        if ($this->title) $vcf .= "TITLE:{$this->title}\r\n";
        if ($this->email) $vcf .= "EMAIL;TYPE=INTERNET:{$this->email}\r\n";
        if ($this->phone) $vcf .= "TEL;TYPE=CELL:{$this->phone}\r\n";
        if ($this->phone_work) $vcf .= "TEL;TYPE=WORK:{$this->phone_work}\r\n";
        if ($this->website) $vcf .= "URL:{$this->website}\r\n";

        if ($this->street || $this->city || $this->state || $this->zip || $this->country) {
            $vcf .= "ADR;TYPE=WORK:;;{$this->street};{$this->city};{$this->state};{$this->zip};{$this->country}\r\n";
        }

        if ($this->note) $vcf .= "NOTE:{$this->note}\r\n";

        $vcf .= "PRODID:-//1INME//Link Manager//EN\r\n";
        $vcf .= "END:VCARD\r\n";

        return $vcf;
    }
}
