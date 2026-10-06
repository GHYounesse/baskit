<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveReviewRequest;
use App\Models\Product;
use App\Models\Review;

class ReviewController extends Controller
{
    public function store(SaveReviewRequest $request, Product $product)
    {
        $this->authorize('create', [Review::class, $product]);

        $user = $request->user();

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['rating' => "You've already reviewed this product. Edit your review instead."]);
        }

        $product->reviews()->create([
            ...$request->validated(),
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Thanks for your review.');
    }

    public function update(SaveReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);

        $review->update($request->validated());

        return back()->with('success', 'Review updated.');
    }

    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return back()->with('success', 'Review deleted.');
    }
}
