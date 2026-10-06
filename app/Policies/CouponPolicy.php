<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    /**
     * Coupon management is admin-only; short-circuit every ability below.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return false;
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return false;
    }
}
