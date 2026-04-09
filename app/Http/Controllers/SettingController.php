<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::orderBy('tag')->get();
        return view('admin.settings.index', compact('settings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tag' => 'required|unique:settings,tag|alpha_dash',
            'type' => 'required|in:text,number,boolean,file',
            'text_value' => 'required_if:type,text',
            'number_value' => 'required_if:type,number',
            'boolean_value' => 'nullable',
            'file_value' => 'required_if:type,file|file',
            'description' => 'nullable'
        ]);

        $value = null;
        $isFile = false;

        switch ($request->type) {
            case 'text':
                $value = $request->text_value;
                break;
            case 'number':
                $value = $request->number_value;
                break;
            case 'boolean':
                $value = $request->boolean_value ? '1' : '0';
                break;
            case 'file':
                // $path = $request->file('file_value')->store('settings');

                if ($request->hasFile('file_value')) {
                    $file = $request->file('file_value');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('settings'), $filename);
                    $path = 'settings/' . $filename;

                    $value = $path;
                    $isFile = true;
                    break;
                }
        }

        Setting::create([
            'tag' => $request->tag,
            'value' => $value,
            'is_file' => $isFile,
            'description' => $request->description
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Paramètre créé avec succès');
    }


    public function update(Request $request, Setting $setting)
    {
        $rules = [
            'description' => 'nullable',
            'file_value' => 'nullable|file'
        ];

        if (!$setting->is_file) {
            $rules['text_value'] = 'required';
        }

        $request->validate($rules);

        $data = ['description' => $request->description];

        if ($setting->is_file && $request->hasFile('file_value')) {

            $oldPath = public_path($setting->value);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }

            $file = $request->file('file_value');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->move(public_path('settings'), $filename);
            $path = 'settings/' . $filename;

            $data['value'] = $path;
        } else if (!$setting->is_file) {
            $data['value'] = $request->text_value;
        }

        $setting->update($data);

        return redirect()->route('settings.index')
            ->with('success', 'Paramètre mis à jour avec succès');
    }

    public function destroy(Setting $setting)
    {
        if ($setting->is_file && Storage::exists($setting->value)) {
            Storage::delete($setting->value);
        }
        $setting->delete();

        return redirect()->route('settings.index')
            ->with('success', 'Paramètre supprimé avec succès');
    }
}
