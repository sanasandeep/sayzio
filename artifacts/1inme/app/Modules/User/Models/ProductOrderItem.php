<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line in a ProductOrder. Name/price/type/file are snapshotted
 * at purchase time so the order stays correct even if the creator later
 * edits or deletes the originating biolink Product block.
 */
class ProductOrderItem extends Model
{
    public const TYPE_DIGITAL  = 'digital';
    public const TYPE_PHYSICAL = 'physical';

    protected $fillable = [
        'order_id', 'link_id', 'block_id',
        'name', 'unit_price_cents', 'quantity', 'currency',
        'product_type', 'digital_file_url', 'image_url', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'quantity'         => 'integer',
            'metadata'         => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'order_id');
    }

    public function lineTotalCents(): int
    {
        return $this->unit_price_cents * max(1, $this->quantity);
    }

    public function isDigital(): bool
    {
        return $this->product_type === self::TYPE_DIGITAL;
    }
}
