<?php

namespace App\Http\Controllers;

use App\Helpers\DataTable;
use App\Http\Controllers\Controller;
use App\Models\Filter;
use App\Models\FilterGroup;
use App\Services\FilterMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FilterController extends Controller
{
    public function __construct(
        protected FilterMatcher $filterMatcher
    ) {}

    public function index(Request $request)
    {
        $filterGroups = FilterGroup::where('user_id', Auth::id())
            ->where('is_active', true)
            ->get();

        return Inertia::render('Filters/Index', [
            'filterGroups' => $filterGroups,
            'selectedGroupId' => $request->query('group'),
        ]);
    }

    public function json(Request $request)
    {
        $search = $request->input('search.value', '');
        $groupId = $request->input('group_id');

        // Get user's filter group IDs
        $userGroupIds = FilterGroup::where('user_id', Auth::id())->pluck('id');

        $query = Filter::query()
            ->whereIn('filter_group_id', $userGroupIds)
            ->with('filterGroup:id,name');

        if ($groupId) {
            $query->where('filter_group_id', $groupId);
        }

        $columns = ['id', 'type', 'pattern', 'match_type', 'action', 'is_active', 'hit_count', 'created_at'];

        $recordsTotalCallback = null;
        if ($search) {
            $recordsTotalCallback = function () use ($userGroupIds, $groupId) {
                $q = Filter::whereIn('filter_group_id', $userGroupIds);
                if ($groupId) {
                    $q->where('filter_group_id', $groupId);
                }

                return $q->count();
            };
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('pattern', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        if ($request->filled('order')) {
            $orderColumn = $columns[$request->order[0]['column']] ?? 'id';
            $query->orderBy($orderColumn, $request->order[0]['dir']);
        } else {
            $query->orderBy('priority', 'desc')->orderBy('created_at', 'desc');
        }

        $data = DataTable::paginate($query, $request, $recordsTotalCallback);

        $data['data'] = collect($data['data'])->map(function ($filter) {
            return [
                'id' => $filter->id,
                'filter_group_id' => $filter->filter_group_id,
                'filter_group_name' => $filter->filterGroup?->name,
                'type' => $filter->type,
                'pattern' => $filter->pattern,
                'match_type' => $filter->match_type,
                'case_sensitive' => $filter->case_sensitive,
                'action' => $filter->action,
                'priority' => $filter->priority,
                'hit_count' => $filter->hit_count,
                'is_active' => $filter->is_active,
                'created_at' => $filter->created_at->toDateTimeString(),
            ];
        });

        return response()->json($data);
    }

    public function create(Request $request)
    {
        $filterGroups = FilterGroup::where('user_id', Auth::id())->get();

        return Inertia::render('Filters/Form', [
            'filterGroups' => $filterGroups,
            'selectedGroupId' => $request->query('group'),
        ]);
    }

    public function store(Request $request)
    {
        $userGroupIds = FilterGroup::where('user_id', Auth::id())->pluck('id');

        $request->validate([
            'filter_group_id' => ['required', Rule::in($userGroupIds)],
            'type' => ['required', Rule::in(Filter::TYPES)],
            'pattern' => 'required|string|max:500',
            'match_type' => ['required', Rule::in(Filter::MATCH_TYPES)],
            'case_sensitive' => 'boolean',
            'action' => ['required', Rule::in(Filter::ACTIONS)],
            'priority' => 'integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        // Validate regex pattern if type is regex
        if ($request->type === Filter::TYPE_REGEX && ! $this->filterMatcher->isValidRegex($request->pattern)) {
            return back()->withErrors(['pattern' => 'Invalid regex pattern.']);
        }

        Filter::create([
            'filter_group_id' => $request->filter_group_id,
            'type' => $request->type,
            'pattern' => $request->pattern,
            'match_type' => $request->match_type,
            'case_sensitive' => $request->case_sensitive ?? false,
            'action' => $request->action,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('filters.index', ['group' => $request->filter_group_id])
            ->with('success', 'Filter created successfully.');
    }

    public function edit($id)
    {
        $userGroupIds = FilterGroup::where('user_id', Auth::id())->pluck('id');
        $filter = Filter::whereIn('filter_group_id', $userGroupIds)->findOrFail($id);
        $filterGroups = FilterGroup::where('user_id', Auth::id())->get();

        return Inertia::render('Filters/Form', [
            'filter' => $filter,
            'filterGroups' => $filterGroups,
        ]);
    }

    public function update(Request $request, $id)
    {
        $userGroupIds = FilterGroup::where('user_id', Auth::id())->pluck('id');
        $filter = Filter::whereIn('filter_group_id', $userGroupIds)->findOrFail($id);

        $request->validate([
            'filter_group_id' => ['required', Rule::in($userGroupIds)],
            'type' => ['required', Rule::in(Filter::TYPES)],
            'pattern' => 'required|string|max:500',
            'match_type' => ['required', Rule::in(Filter::MATCH_TYPES)],
            'case_sensitive' => 'boolean',
            'action' => ['required', Rule::in(Filter::ACTIONS)],
            'priority' => 'integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        // Validate regex pattern if type is regex
        if ($request->type === Filter::TYPE_REGEX && ! $this->filterMatcher->isValidRegex($request->pattern)) {
            return back()->withErrors(['pattern' => 'Invalid regex pattern.']);
        }

        $filter->update([
            'filter_group_id' => $request->filter_group_id,
            'type' => $request->type,
            'pattern' => $request->pattern,
            'match_type' => $request->match_type,
            'case_sensitive' => $request->case_sensitive ?? false,
            'action' => $request->action,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return redirect()->route('filters.index', ['group' => $filter->filter_group_id])
            ->with('success', 'Filter updated successfully.');
    }

    public function destroy($id)
    {
        $userGroupIds = FilterGroup::where('user_id', Auth::id())->pluck('id');
        $filter = Filter::whereIn('filter_group_id', $userGroupIds)->findOrFail($id);
        $groupId = $filter->filter_group_id;
        $filter->delete();

        return redirect()->route('filters.index', ['group' => $groupId])
            ->with('success', 'Filter deleted successfully.');
    }

    public function testPattern(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(Filter::TYPES)],
            'pattern' => 'required|string|max:500',
            'match_type' => ['required', Rule::in(Filter::MATCH_TYPES)],
            'case_sensitive' => 'boolean',
            'test_text' => 'required|string|max:1000',
        ]);

        $matches = $this->filterMatcher->testPattern(
            $request->type,
            $request->pattern,
            $request->match_type,
            $request->case_sensitive ?? false,
            $request->test_text
        );

        return response()->json(['matches' => $matches]);
    }
}
