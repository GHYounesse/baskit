import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ order }) {
  return (
    <AuthenticatedLayout header={<h2 className="text-xl font-semibold">Order #{order.id}</h2>}>
      <div className="max-w-2xl mx-auto p-6 bg-white rounded-lg border">
        <div className="flex justify-between mb-6">
          <div>
            <div className="text-sm text-gray-500">Status</div>
            <div className="font-semibold capitalize">{order.status}</div>
          </div>
          <div>
            <div className="text-sm text-gray-500">Placed on</div>
            <div className="font-semibold">{new Date(order.created_at).toLocaleDateString()}</div>
          </div>
        </div>

        <table className="w-full text-sm mb-6">
          <thead>
            <tr className="text-left border-b">
              <th className="py-2">Product</th>
              <th className="py-2">Qty</th>
              <th className="py-2 text-right">Price</th>
            </tr>
          </thead>
          <tbody>
            {order.items.map((item) => (
              <tr key={item.id} className="border-b">
                <td className="py-2">{item.product_name}</td>
                <td className="py-2">{item.quantity}</td>
                <td className="py-2 text-right">${((item.unit_price_cents * item.quantity) / 100).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="text-right font-bold text-lg mb-6">
          Total: ${(order.total_cents / 100).toFixed(2)}
        </div>

        {order.shipping_address && (
          <div>
            <div className="text-sm text-gray-500 mb-1">Shipping address</div>
            <div className="text-sm">
              {order.shipping_address.line1}, {order.shipping_address.city}, {order.shipping_address.country}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
}
