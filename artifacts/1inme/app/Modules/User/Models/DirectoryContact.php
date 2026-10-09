<?php
namespace App\Modules\User\Models;
use Illuminate\Database\Eloquent\Model;
class DirectoryContact extends Model {
    protected $fillable = ['directory_id', 'category_id', 'name', 'details', 'is_active', 'is_pinned', 'sort_order'];
    protected function casts(): array { return ['details' => 'array', 'is_active' => 'boolean', 'is_pinned' => 'boolean']; }
    public function availability(string $timezone): ?bool {
        $d = $this->details ?? [];
        if (empty($d['opens']) || empty($d['closes']) || empty($d['days'])) return null;
        $now = now($timezone);
        if (in_array($now->format('Y-m-d'), preg_split('/[\s,]+/', $d['holidays'] ?? ''), true)) return false;
        $time = $now->format('H:i');
        $days = array_map('intval', explode(',', $d['days']));
        if ($d['opens'] <= $d['closes']) return in_array($now->dayOfWeek, $days, true) && $time >= $d['opens'] && $time < $d['closes'];
        return (in_array($now->dayOfWeek, $days, true) && $time >= $d['opens']) || (in_array($now->copy()->subDay()->dayOfWeek, $days, true) && $time < $d['closes']);
    }
}
