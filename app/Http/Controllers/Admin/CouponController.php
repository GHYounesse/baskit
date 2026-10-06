<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCouponRequest;
use App\Models\Coupon;
use Inertia\Inertia;

class CouponController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Coupon::class);

        return Inertia::render('Admin/Coupons/Index', [
            'coupons' => Coupon::latest()->paginate(15),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Coupon::class);

        return Inertia::render('Admin/Coupons/Create');
    }

    public function store(SaveCouponRequest $request)
    {
        Coupon::create($request->validated());

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon)
    {
        $this->authorize('update', $coupon);

        return Inertia::render('Admin/Coupons/Edit', ['coupon' => $coupon]);
    }

    public function update(SaveCouponRequest $request, Coupon $coupon)
    {
        $coupon->update($request->validated());

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    /**
     * Orders keep the code they used (a snapshot), so deleting is safe.
     */
    public function destroy(Coupon $coupon)
    {
        $this->authorize('delete', $coupon);

        $coupon->delete();

        return back()->with('success', 'Coupon deleted.');
    }
}
