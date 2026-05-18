<?php

namespace App\Http\Controllers;

use App\Models\InventoryLog;

class InventoryLogController extends Controller
{
    public function index()
    {
        $logs = InventoryLog::latest()->paginate(15);

        return view('inventory.index', compact('logs'));
    }
}
