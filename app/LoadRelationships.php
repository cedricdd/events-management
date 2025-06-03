<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

trait LoadRelationships
{
    protected function loadRelationships(Model|Collection|LengthAwarePaginator $input, array $allowed = [])
    {
        // Nothing can be loaded
        if (count($allowed) === 0)
            return $input;

        $loadedRelationships = [];
        $relationships = request()->query('with', '');

        foreach (explode(',', $relationships) as $relationship) {
            $relationship = strtolower(trim($relationship));

            // Skip empty relationships
            if (empty($relationship))
                continue;

            // Skip relationships that are not formatted correctly
            if (preg_match('/[^a-z_.]/', $relationship))
                continue;

            // Skip relationships that are not in the allowed list
            if (!in_array($relationship, $allowed))
                continue;

            // Skip relationships that are already loaded
            if ($input instanceof Model && $input->relationLoaded($relationship))
                continue;

            $input->load($relationship);

            $loadedRelationships[] = $relationship;
        }

        if ($input instanceof LengthAwarePaginator) {
            $input->appends(['with' => implode(',', $loadedRelationships)]);
        }

        return $input;
    }
}
