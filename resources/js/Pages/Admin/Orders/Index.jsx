import { router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatMoney } from '@/utils/money';

const STATUSES = ['pending', 'paid', 'shipped', 'cancelled'];

export default function Index({ orders }) {
  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Manage Orders</h2>}>
      <div className="max-w-5xl mx-auto p-6">
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
            {orders.data.map((order) => (
              <tr key={order.id} className="border-b">
                <td className="p-3">#{order.id}</td>
                <td className="p-3">{order.user.name} ({order.user.email})</td>
                <td className="p-3">{formatMoney(order.total_cents)}</td>
                <td className="p-3">
                  <select
                    value={order.status}
                    onChange={(e) => router.patch(`/admin/orders/${order.id}`, { status: e.target.value })}
                    className="border rounded px-2 py-1"
                  >
                    {STATUSES.map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                </td>
                <td className="p-3">{new Date(order.created_at).toLocaleDateString()}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AuthenticatedLayout>
  );
}
