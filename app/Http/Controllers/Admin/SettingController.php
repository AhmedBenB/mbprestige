<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'registrationCode' => Setting::get('registration_code', 'MBP95'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'registration_code' => ['required', 'string', 'min:3', 'max:50', 'alpha_num'],
        ]);

        Setting::set('registration_code', strtoupper($data['registration_code']));

        return back()->with('success', 'Code de parrainage mis a jour.');
    }
}
