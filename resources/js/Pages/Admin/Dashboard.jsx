import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/utils/money';

function StatCard({ label, value }) {
  return (
    <div className="bg-white border rounded-lg p-4">
      <div className="text-sm text-gray-500">{label}</div>
      <div className="text-2xl font-bold mt-1">{value}</div>
    </div>
  );
}

export default function Dashboard({ stats, lowStockProducts, recentOrders }) {
  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Admin Dashboard</h2>}>
      <div className="max-w-5xl mx-auto p-6 space-y-8">
        <div className="flex gap-3">
          <Link href="/admin/products" className="text-sm text-blue-600 hover:underline">Manage products</Link>
          <span className="text-gray-300">•</span>
          <Link href="/admin/orders" className="text-sm text-blue-600 hover:underline">Manage orders</Link>
        </div>

        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <StatCard label="Revenue" value={formatMoney(stats.revenue_cents)} />
          <StatCard label="Orders" value={stats.order_count} />
          <StatCard label="Products" value={stats.product_count} />
          <StatCard label="Low stock" value={stats.low_stock_count} />
        </div>

        <div>
          <h3 className="font-semibold mb-3">Low stock products</h3>
          {lowStockProducts.length === 0 ? (
            <p className="text-sm text-gray-500">Nothing is running low.</p>
          ) : (
            <table className="w-full bg-white border rounded-lg text-sm">
              <thead>
                <tr className="text-left border-b bg-gray-50">
                  <th className="p-3">Product</th>
                  <th className="p-3">Stock left</th>
                  <th className="p-3"></th>
                </tr>
              </thead>
              <tbody>
                {lowStockProducts.map((product) => (
                  <tr key={product.id} className="border-b">
                    <td className="p-3">{product.name}</td>
                    <td className={`p-3 ${product.stock === 0 ? 'text-red-600 font-semibold' : ''}`}>
                      {product.stock === 0 ? 'Out of stock' : product.stock}
                    </td>
                    <td className="p-3 text-right">
                      <Link href={`/admin/products/${product.id}/edit`} className="text-blue-600">Edit</Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div>
          <h3 className="font-semibold mb-3">Recent orders</h3>
          {recentOrders.length === 0 ? (
            <p className="text-sm text-gray-500">No orders yet.</p>
          ) : (
            <table className="w-full bg-white border rounded-lg text-sm">
              <thead>
                <tr className="text-left border-b bg-gray-50">
                  <th className="p-3">Order</th>
                  <th className="p-3">Customer</th>
                  <th className="p-3">Total</th>
                  <th className="p-3">Status</th>
                  <th className="p-3">Placed</th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.map((order) => (
                  <tr key={order.id} className="border-b">
                    <td className="p-3">#{order.id}</td>
                    <td className="p-3">{order.user?.name ?? '—'}</td>
                    <td className="p-3">{formatMoney(order.total_cents)}</td>
                    <td className="p-3 capitalize">{order.status}</td>
                    <td className="p-3">{new Date(order.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
