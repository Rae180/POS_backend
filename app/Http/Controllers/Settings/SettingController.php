<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|JsonResponse
    {
        $settings = Setting::all();

        if ($request->wantsJson()) {
            return response()->json($settings);
        }

        return view('settings.edit', ['settings' => $settings]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->except('_token');

        foreach ($data as $key => $value) {
            $setting = Setting::firstOrCreate(['key' => $key]);
            $setting->value = $value;
            $setting->save();
        }

        if ($request->wantsJson()) {
            return response()->json(Setting::all());
        }

        return redirect()->route('settings.index');
    }
}