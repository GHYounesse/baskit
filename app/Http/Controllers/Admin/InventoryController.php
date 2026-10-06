<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\InventoryAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryAudit $audit)
    {
        $this->authorize('viewAny', Product::class);

        $reason = $request->query('reason');
        $reason = array_key_exists((string) $reason, StockMovement::REASONS) ? $reason : null;
        $productId = (int) $request->query('product') ?: null;
        $search = trim((string) $request->query('q', ''));

        $movements = StockMovement::query()
            ->with('user:id,name')
            ->when($reason, fn ($query, $r) => $query->where('reason', $r))
            ->when($productId, fn ($query, $id) => $query->where('product_id', $id))
            ->when($search !== '', fn ($query) => $query->where('product_name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $issues = $audit->issues();

        return Inertia::render('Admin/Inventory/Index', [
            'movements' => $movements,
            'filters' => ['reason' => $reason, 'product' => $productId, 'q' => $search],
            'reasons' => StockMovement::REASONS,
            'product' => $productId ? Product::find($productId)?->only(['id', 'name']) : null,
            'integrity' => [
                'ok' => $issues === [],
                'count' => count($issues),
                'issues' => array_slice($issues, 0, 10),
            ],
        ]);
    }
}
