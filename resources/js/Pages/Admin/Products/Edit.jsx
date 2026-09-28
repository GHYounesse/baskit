import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ProductFormFields from '@/Components/Admin/ProductFormFields';

export default function Edit({ product, categories }) {
  const { data, setData, put, processing, errors } = useForm({
    name: product.name,
    category_id: product.category_id ?? '',
    description: product.description ?? '',
    price_cents: product.price_cents,
    stock: product.stock,
    is_active: product.is_active,
    image: null,
    remove_image: false,
  });
  const [preview, setPreview] = useState(product.image_url);

  const submit = (e) => {
    e.preventDefault();
    put(`/admin/products/${product.id}`);
  };

  const onImageChange = (e) => {
    const file = e.target.files[0] ?? null;
    setData((prev) => ({ ...prev, image: file, remove_image: false }));
    setPreview(file ? URL.createObjectURL(file) : product.image_url);
  };

  const removeImage = () => {
    setData((prev) => ({ ...prev, image: null, remove_image: true }));
    setPreview(null);
  };

  const destroy = () => {
    if (confirm('Delete this product?')) {
      router.delete(`/admin/products/${product.id}`);
    }
  };

  return (
    <AuthenticatedLayout header={<h2 className="font-display text-2xl font-bold text-ink">Edit product</h2>}>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-lg px-6 py-8">
          <Head title="Edit product" />

          <form onSubmit={submit} className="space-y-6 rounded-lg border border-stroke bg-surface p-5">
            <ProductFormFields
              data={data}
              setData={setData}
              errors={errors}
              categories={categories}
              preview={preview}
              onImageChange={onImageChange}
              onRemoveImage={removeImage}
            />

            <div className="flex gap-3">
              <button
                disabled={processing}
                className="flex-1 rounded-lg bg-primary py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover disabled:opacity-50"
              >
                Save changes
              </button>
              <button
                type="button"
                onClick={destroy}
                className="rounded-lg border border-red-600 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 dark:hover:bg-red-950"
              >
                Delete
              </button>
            </div>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
