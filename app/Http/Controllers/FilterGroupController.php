<?php

namespace App\Http\Controllers;

use App\Helpers\DataTable;
use App\Models\FilterGroup;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FilterGroupController extends Controller
{
    public function index()
    {
        return Inertia::render('FilterGroups/Index');
    }

    public function json(Request $request)
    {
        $search = $request->input('search.value', '');
        $query = FilterGroup::query()
            ->where('user_id', Auth::id())
            ->withCount('filters');

        $columns = ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at'];

        $recordsTotalCallback = null;
        if ($search) {
            $recordsTotalCallback = function () {
                return FilterGroup::where('user_id', Auth::id())->count();
            };
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order')) {
            $orderColumn = $columns[$request->order[0]['column']] ?? 'id';
            $query->orderBy($orderColumn, $request->order[0]['dir']);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $data = DataTable::paginate($query, $request, $recordsTotalCallback);

        $data['data'] = collect($data['data'])->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_active' => $group->is_active,
                'applies_to_platforms' => $group->applies_to_platforms,
                'filters_count' => $group->filters_count,
                'created_at' => $group->created_at->toDateTimeString(),
                'updated_at' => $group->updated_at->toDateTimeString(),
            ];
        });

        return response()->json($data);
    }

    public function create()
    {
        $platforms = Platform::where('is_active', true)->get();

        return Inertia::render('FilterGroups/Form', [
            'platforms' => $platforms,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'applies_to_platforms' => 'nullable|array',
            'applies_to_platforms.*' => 'string|exists:platforms,name',
        ]);

        FilterGroup::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'applies_to_platforms' => $request->applies_to_platforms,
        ]);

        return redirect()->route('filter-groups.index')
            ->with('success', 'Filter group created successfully.');
    }

    public function edit($id)
    {
        $filterGroup = FilterGroup::where('user_id', Auth::id())->findOrFail($id);
        $platforms = Platform::where('is_active', true)->get();

        return Inertia::render('FilterGroups/Form', [
            'filterGroup' => $filterGroup,
            'platforms' => $platforms,
        ]);
    }

    public function update(Request $request, $id)
    {
        $filterGroup = FilterGroup::where('user_id', Auth::id())->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'applies_to_platforms' => 'nullable|array',
            'applies_to_platforms.*' => 'string|exists:platforms,name',
        ]);

        $filterGroup->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'applies_to_platforms' => $request->applies_to_platforms,
        ]);

        return redirect()->route('filter-groups.index')
            ->with('success', 'Filter group updated successfully.');
    }

    public function destroy($id)
    {
        $filterGroup = FilterGroup::where('user_id', Auth::id())->findOrFail($id);
        $filterGroup->delete();

        return redirect()->route('filter-groups.index')
            ->with('success', 'Filter group deleted successfully.');
    }
}
