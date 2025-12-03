<?php

namespace App\Traits;

use Illuminate\Http\Exceptions\HttpResponseException;

trait SoftDeleteAcceptable
{
    protected static function bootSoftDeleteAcceptable(): void
    {
        static::deleting(static function ($model) {
            $model->runSoftDeletingAcceptableRelations();
        });
    }

    /**
     * Run the cascading soft delete for this model.
     *
     * @return void
     */
    protected function runSoftDeletingAcceptableRelations(): void
    {
        foreach ($this->getActiveSoftDeletingAcceptableRelations() as $relationship) {
            $this->softDeletingAcceptable($relationship);
        }
    }

    /**
     * Cascade delete the given relationship on the given mode.
     *
     * @param string $relationship
     */
    protected function softDeletingAcceptable(string $relationship): void
    {
        if ($this->{$relationship}()->exists()) {
            throw new HttpResponseException(response()->json([
                'code' => 406,
                'message' => 'This item cannot be deleted!',
            ], 406));
        }
    }

    /**
     * Fetch the defined cascading soft deletes for this model.
     *
     * @return array
     */
    protected function getSoftDeletingAcceptableRelations(): array
    {
        return $this->softDeleteAcceptableRelations ?? [];
    }

    /**
     * For the cascading deletes defined on the model, return only those that are not null.
     *
     * @return array
     */
    protected function getActiveSoftDeletingAcceptableRelations(): array
    {
        return array_filter($this->getSoftDeletingAcceptableRelations(), function ($relationship) {
            return ! is_null($this->{$relationship});
        });
    }
}
