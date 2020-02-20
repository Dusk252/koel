<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Interaction  $song
 * @property int   $id
 */
class PlayInteraction extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['id', 'interaction_id', 'created_at', 'updated_at'];

    public function interaction(): BelongsTo
    {
        return $this->belongsTo(Interaction::class);
    }
}
