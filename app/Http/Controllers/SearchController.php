<?php

namespace App\Http\Controllers;

use App\Models\Backlog;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $term = trim((string) $request->query('q'));
        $user = $request->user();

        if ($term === '') {
            return view('search', ['term' => '', 'results' => collect()]);
        }

        $projectIds = Project::query()->visibleTo($user)->pluck('id');

        $exact = Backlog::whereIn('project_id', $projectIds)->where('code', strtoupper($term))->first();
        if ($exact) {
            return redirect()->route('backlogs.show', $exact);
        }

        $results = Backlog::query()
            ->whereIn('project_id', $projectIds)
            ->where(fn ($q) => $q->where('code', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%"))
            ->with(['project', 'status', 'programmer', 'qa'])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return view('search', ['term' => $term, 'results' => $results]);
    }
}
