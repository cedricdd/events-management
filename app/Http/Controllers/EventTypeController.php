<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventTypeCollection;
use App\Models\EventType;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    public function index(): EventTypeCollection
    {
        $types = EventType::orderby('name', 'asc')->get();

        return new EventTypeCollection($types);
    }
    public function store() {

    }

    public function update($id) {

    }

    public function destroy($id) {
    }
}
