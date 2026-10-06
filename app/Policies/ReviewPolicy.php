<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Only customers who bought the product (and kept the order) can review it.
     */
    public function create(User $user, Product $product): bool
    {
        return $product->is_active && $product->wasPurchasedBy($user);
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    /**
     * Authors can remove their own review; admins can remove any (moderation).
     */
    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->isAdmin();
    }
}
