<?php

namespace App\Http\Controllers;

use App\Models\Filter;
use App\Models\FilterGroup;
use App\Models\PresetFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PresetFilterController extends Controller
{
    public function index()
    {
        $presets = PresetFilter::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Group by category for UI
        $groupedPresets = $presets->groupBy('category');

        return Inertia::render('PresetFilters/Index', [
            'presets' => $presets,
            'groupedPresets' => $groupedPresets,
            'categories' => [
                'spam' => 'Spam & Judi',
                'self_promotion' => 'Self Promotion',
                'scam' => 'Scam & Phishing',
                'hate_speech' => 'Hate Speech',
                'other' => 'Other',
            ],
        ]);
    }

    public function show($id)
    {
        $preset = PresetFilter::findOrFail($id);

        return Inertia::render('PresetFilters/Show', [
            'preset' => $preset,
        ]);
    }

    public function import(Request $request, $id)
    {
        $preset = PresetFilter::findOrFail($id);

        $request->validate([
            'filter_group_id' => 'nullable|exists:filter_groups,id',
            'new_group_name' => 'required_without:filter_group_id|nullable|string|max:100',
        ]);

        // Verify user owns the filter group if provided
        if ($request->filter_group_id) {
            $filterGroup = FilterGroup::where('user_id', Auth::id())
                ->findOrFail($request->filter_group_id);
        }

        DB::beginTransaction();

        try {
            // Create new group if needed
            if (! $request->filter_group_id) {
                $filterGroup = FilterGroup::create([
                    'user_id' => Auth::id(),
                    'name' => $request->new_group_name ?? $preset->name,
                    'description' => "Imported from preset: {$preset->name}",
                    'is_active' => true,
                ]);
            }

            // Import filters from preset
            $filtersData = $preset->filters_data;
            $imported = 0;

            foreach ($filtersData as $filterData) {
                Filter::create([
                    'filter_group_id' => $filterGroup->id,
                    'type' => $filterData['type'] ?? 'keyword',
                    'pattern' => $filterData['pattern'],
                    'match_type' => $filterData['match_type'] ?? 'contains',
                    'case_sensitive' => $filterData['case_sensitive'] ?? false,
                    'action' => $filterData['action'] ?? 'delete',
                    'priority' => $filterData['priority'] ?? 0,
                    'is_active' => true,
                ]);
                $imported++;
            }

            DB::commit();

            return redirect()->route('filters.index', ['group' => $filterGroup->id])
                ->with('success', "Successfully imported {$imported} filters from \"{$preset->name}\" preset.");
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);

            return back()->withErrors(['error' => 'Failed to import preset filters.']);
        }
    }

    public function getUserFilterGroups()
    {
        $groups = FilterGroup::where('user_id', Auth::id())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($groups);
    }
}
