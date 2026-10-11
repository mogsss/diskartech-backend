<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Support\PlainTextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        if (is_string($request->query('search'))) $request->merge(['search' => PlainTextInput::normalize($request->query('search'))]);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (PlainTextInput::containsMarkup($value)) $fail('Search must contain plain text only.');
            }],
            'rating' => ['nullable', Rule::in(['all', 'low', '1', '2', '3', '4', '5'])],
            'role' => ['nullable', Rule::in(['all', 'student', 'employer', 'household'])],
        ]);
        $search = $filters['search'] ?? '';
        $rating = $filters['rating'] ?? 'all';
        $role = $filters['role'] ?? 'all';
        $totalReviews = Review::count();
        $averageRating = $totalReviews ? round((float) Review::avg('rating'), 1) : null;
        $lowRatingCount = Review::where('rating', '<=', 2)->count();
        $distribution = Review::selectRaw('rating, COUNT(*) AS total')->groupBy('rating')->pluck('total', 'rating');
        $query = Review::query();
        if ($rating === 'low') $query->where('rating', '<=', 2);
        elseif ($rating !== 'all') $query->where('rating', (int) $rating);
        // Household reviews use the employer reviewer_role in the mobile API; use the actual recipient account role.
        if ($role !== 'all') $query->whereHas('reviewee', fn (Builder $user) => $user->where('role', $role));
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(function (Builder $query) use ($pattern) {
                $query->whereRaw("feedback LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('application.job', fn (Builder $job) => $job->whereRaw("title LIKE ? ESCAPE '!'", [$pattern]));
                foreach (['reviewer', 'reviewee'] as $party) {
                    $query->orWhereHas($party, function (Builder $user) use ($pattern) {
                        $user->whereRaw("email LIKE ? ESCAPE '!'", [$pattern]);
                        foreach (['student' => ['studentProfile', 'student_name'], 'employer' => ['employerProfile', 'hirer_name', 'employer_name'], 'household' => ['householdProfile', 'household_name']] as $type => $fields) {
                            $relation = array_shift($fields);
                            $user->orWhere(function (Builder $user) use ($type, $relation, $fields, $pattern) {
                                $user->where('role', $type)->whereHas($relation, function (Builder $profile) use ($fields, $pattern) {
                                    $profile->where(function (Builder $profile) use ($fields, $pattern) {
                                        foreach ($fields as $field) $profile->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$pattern]);
                                    });
                                });
                            });
                        }
                    });
                }
            });
        }
        $reviews = $query->with(['application.job', 'reviewer.studentProfile', 'reviewer.employerProfile', 'reviewer.householdProfile', 'reviewee.studentProfile', 'reviewee.employerProfile', 'reviewee.householdProfile'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(12)->withQueryString();
        return view('admin.reviews', compact('reviews', 'totalReviews', 'averageRating', 'lowRatingCount', 'distribution', 'search', 'rating', 'role'));
    }
}
