import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProductFormFields from '@/Components/Admin/ProductFormFields';

export default function Create({ categories }) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    category_id: '',
    description: '',
    price_cents: 0,
    stock: 0,
    is_active: true,
    image: null,
  });
  const [preview, setPreview] = useState(null);

  const submit = (e) => {
    e.preventDefault();
    post('/admin/products');
  };

  const onImageChange = (e) => {
    const file = e.target.files[0] ?? null;
    setData('image', file);
    setPreview(file ? URL.createObjectURL(file) : null);
  };

  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">New product</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-lg px-6 py-8">
          <Head title="New product" />

          <form onSubmit={submit} className="space-y-6 rounded-lg border border-stroke bg-surface p-5">
            <ProductFormFields
              data={data}
              setData={setData}
              errors={errors}
              categories={categories}
              preview={preview}
              onImageChange={onImageChange}
            />

            <button
              disabled={processing}
              className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
            >
              Create product
            </button>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
