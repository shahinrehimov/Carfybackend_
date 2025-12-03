<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphTo;

trait ActionUser
{
    public function creatable(): MorphTo
    {
        return $this->morphTo('creatable');
    }

    public function updatable(): MorphTo
    {
        return $this->morphTo('updatable');
    }

    public function deletable(): MorphTo
    {
        return $this->morphTo('deletable');
    }
}
