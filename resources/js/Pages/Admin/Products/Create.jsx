import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PageHeader from '@/Components/PageHeader';
import ProductFormFields from '@/Components/Admin/ProductFormFields';

export default function Create({ categories, aiEnabled = false }) {
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
  const [keywords, setKeywords] = useState('');
  const [drafting, setDrafting] = useState(false);
  const [draftError, setDraftError] = useState(null);

  // Ask the AI for a name, description and category. It only fills the form;
  // nothing is saved until the admin reviews it and presses Create.
  const draftWithAi = async () => {
    setDraftError(null);
    setDrafting(true);
    try {
      const body = new FormData();
      if (keywords.trim()) body.append('keywords', keywords.trim());
      if (data.image) body.append('image', data.image);
      const { data: draft } = await window.axios.post('/admin/products/draft', body);
      setData((current) => ({
        ...current,
        name: draft.name,
        description: draft.description,
        category_id: draft.category_id ?? current.category_id,
      }));
    } catch (error) {
      const response = error.response?.data;
      setDraftError(
        response?.message ?? Object.values(response?.errors ?? {})[0]?.[0] ?? 'Could not draft this product. Fill the form by hand.',
      );
    } finally {
      setDrafting(false);
    }
  };

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
    <AuthenticatedLayout>
      <div className="bg-canvas text-ink">
        <div className="mx-auto max-w-lg px-6 py-8">
          <Head title="New product" />

          <PageHeader title="New product" />

          {aiEnabled && (
            <div className="mb-4 space-y-2 rounded-lg border border-stroke bg-surface p-5">
              <label className="block text-sm font-medium text-ink" htmlFor="ai-keywords">
                Draft with AI
              </label>
              <input
                id="ai-keywords"
                value={keywords}
                onChange={(e) => setKeywords(e.target.value)}
                maxLength={300}
                placeholder="A few keywords, e.g. blue ceramic mug, 350 ml (and/or choose a photo below)"
                className="w-full rounded-lg border-stroke bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
              />
              <button
                type="button"
                onClick={draftWithAi}
                disabled={drafting || (!keywords.trim() && !data.image)}
                className="rounded-lg border border-primary px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary-light disabled:opacity-50"
              >
                {drafting ? 'Drafting…' : 'Fill name, description and category'}
              </button>
              <p className="text-xs text-ink-muted">Review what it wrote before you create the product.</p>
              {draftError && <p role="alert" className="text-sm text-danger">{draftError}</p>}
            </div>
          )}

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
              className="w-full rounded-lg bg-primary py-2.5 text-sm font-semibold text-primary-on transition hover:bg-primary-hover disabled:opacity-50"
            >
              Create product
            </button>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
