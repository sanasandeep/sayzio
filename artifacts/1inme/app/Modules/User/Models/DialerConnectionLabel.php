<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A dialer-side "Brand vs Personal" label the viewer puts on a Sayzio
 * connection (someone they follow or who follows them). Stored per
 * (viewer, other user) so the label survives regardless of which
 * direction the follow row points.
 */
class DialerConnectionLabel extends Model
{
    protected $fillable = ['user_id', 'other_user_id', 'category'];
}
