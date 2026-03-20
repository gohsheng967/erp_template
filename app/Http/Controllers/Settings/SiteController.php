<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SiteController extends Controller
{
    public function index()
    {
        $sites = Site::query()
            ->orderBy('site_name')
            ->get()
            ->map(function (Site $site) {
                return [
                    'id' => $site->id,
                    'uuid' => $site->uuid,
                    'site_name' => $site->site_name,
                    'address' => $site->address,
                    'longitude' => (float) $site->longitude,
                    'latitude' => (float) $site->latitude,
                    'image_path' => $site->image_path,
                    'image_url' => $site->image_path ? Storage::disk('public')->url($site->image_path) : null,
                ];
            });

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'image' => ['required', 'image', 'max:10240'],
        ]);

        $imagePath = $request->file('image')
            ? $request->file('image')->store('sites', 'public')
            : null;

        Site::create([
            'site_name' => $validated['site_name'],
            'address' => $validated['address'],
            'longitude' => $validated['longitude'],
            'latitude' => $validated['latitude'],
            'image_path' => $imagePath,
        ]);

        return back()->with('success', 'Site created successfully.');
    }

    public function update(Request $request, Site $site)
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'image' => ['nullable', 'image', 'max:10240'],
        ]);

        $data = [
            'site_name' => $validated['site_name'],
            'address' => $validated['address'],
            'longitude' => $validated['longitude'],
            'latitude' => $validated['latitude'],
        ];

        if ($request->hasFile('image')) {
            if ($site->image_path) {
                Storage::disk('public')->delete($site->image_path);
            }

            $data['image_path'] = $request->file('image')->store('sites', 'public');
        }

        $site->update($data);

        return back()->with('success', 'Site updated successfully.');
    }

    public function destroy(Site $site)
    {
        $site->delete();

        return back()->with('success', 'Site deleted successfully.');
    }
}
