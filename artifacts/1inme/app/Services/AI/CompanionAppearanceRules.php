<?php
namespace App\Services\AI;
class CompanionAppearanceRules
{
    public static function rules(): array
    {
        $rules=[];
        foreach(['accent','chat','user','assistant'] as $part){
            $rules['config.'.$part.'_mode']='nullable|in:default,solid,gradient,image';
            foreach(['color','color_end','text'] as $field)$rules['config.'.$part.'_'.$field]=['nullable','regex:/^#[0-9a-fA-F]{6}$/'];
            $rules['config.'.$part.'_image']='nullable|url:http,https|max:2048';
        }
        return $rules;
    }
}
