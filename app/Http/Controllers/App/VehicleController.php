<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

class VehicleController extends Controller
{
    public function show(Listing $listing): RedirectResponse
    {
        return redirect()->route('vehicles.show', $listing);
    }
}
