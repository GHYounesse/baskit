import { Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  paid: 'bg-green-100 text-green-800',
  shipped: 'bg-blue-100 text-blue-800',
  cancelled: 'bg-red-100 text-red-800',
};

export default function Index({ orders }) {
  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">My Orders</h2>}>
      <div className="max-w-4xl mx-auto p-6">
        {orders.length === 0 && <p className="text-gray-500">You haven't placed any orders yet.</p>}

        <div className="space-y-3">
          {orders.map((order) => (
            <Link
              key={order.id}
              href={`/orders/${order.id}`}
              className="flex items-center justify-between border rounded-lg p-4 hover:shadow-md transition bg-white"
            >
              <div>
                <div className="font-semibold">Order #{order.id}</div>
                <div className="text-sm text-gray-500">
                  {new Date(order.created_at).toLocaleDateString()} · {order.items.length} item(s)
                </div>
              </div>
              <div className="flex items-center gap-4">
                <span className={`text-xs px-2 py-1 rounded-full capitalize ${statusColors[order.status]}`}>
                  {order.status}
                </span>
                <span className="font-semibold">${(order.total_cents / 100).toFixed(2)}</span>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
