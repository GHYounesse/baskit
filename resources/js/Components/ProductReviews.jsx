import ConfirmDialog from '@/Components/ConfirmDialog';
import Pagination from '@/Components/Pagination';
import { StarInput, StarRating } from '@/Components/StarRating';
import { formatDate } from '@/utils/date';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const textareaClass =
  'w-full rounded-lg border-stroke bg-surface px-3 py-2.5 text-base text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary sm:py-2 sm:text-sm';

function RatingSummary({ ratings }) {
  return (
    <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:gap-8">
      <div className="shrink-0">
        <div className="font-display text-5xl font-bold leading-none text-ink">{ratings.average.toFixed(1)}</div>
        <div className="mt-2">
          <StarRating value={ratings.average} size="h-5 w-5" />
        </div>
        <div className="mt-1 text-sm text-ink-muted">
          {ratings.count} {ratings.count === 1 ? 'review' : 'reviews'}
        </div>
      </div>

      <div className="min-w-0 flex-1 space-y-1.5">
        {[5, 4, 3, 2, 1].map((stars) => {
          const count = ratings.distribution[stars];
          const percent = ratings.count ? (count / ratings.count) * 100 : 0;

          return (
            <div key={stars} className="flex items-center gap-3 text-sm">
              <span className="w-12 shrink-0 text-ink-muted">{stars} stars</span>
              <div className="h-2 flex-1 overflow-hidden rounded-full bg-stroke">
                <div className="h-full rounded-full bg-amber-500" style={{ width: `${percent}%` }} />
              </div>
              <span className="w-6 shrink-0 text-right text-ink-muted">{count}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function ReviewForm({ product, review, onDone }) {
  const editing = Boolean(review);
  const { data, setData, post, patch, processing, errors, reset } = useForm({
    rating: review?.rating ?? null,
    body: review?.body ?? '',
  });

  const submit = (e) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        onDone?.();
      },
    };

    editing ? patch(`/reviews/${review.id}`, options) : post(`/products/${product.slug}/reviews`, options);
  };

  return (
    <form onSubmit={submit} className="rounded-lg border border-stroke bg-surface p-4 sm:p-5">
      <h3 className="font-display text-lg font-bold text-ink">{editing ? 'Edit your review' : 'Write a review'}</h3>

      <div className="mt-3">
        <StarInput value={data.rating} onChange={(n) => setData('rating', n)} />
        {errors.rating && <p className="mt-1 text-sm text-red-600">{errors.rating}</p>}
      </div>

      <div className="mt-3">
        <label htmlFor="review-body" className="mb-1 block text-sm font-medium text-ink">
          Your thoughts (optional)
        </label>
        <textarea
          id="review-body"
          rows={4}
          value={data.body}
          onChange={(e) => setData('body', e.target.value)}
          placeholder="What did you like or dislike? How are you using it?"
          className={textareaClass}
        />
        {errors.body && <p className="mt-1 text-sm text-red-600">{errors.body}</p>}
      </div>

      <div className="mt-4 flex gap-3">
        <button
          type="submit"
          disabled={processing}
          className="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-60"
        >
          {processing ? 'Saving…' : editing ? 'Save changes' : 'Post review'}
        </button>
        {editing && (
          <button
            type="button"
            onClick={onDone}
            className="rounded-lg border border-stroke px-5 py-2.5 text-sm font-semibold text-ink transition hover:bg-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}

function ReviewItem({ review, mine = false, onEdit, onDelete }) {
  const { auth } = usePage().props;
  const canDelete = mine || auth.user?.is_admin;

  return (
    <article className={`py-5 ${mine ? 'rounded-lg bg-primary-light/50 px-4' : ''}`}>
      <div className="flex items-center gap-3">
        <StarRating value={review.rating} />
        <span className="text-sm font-medium text-ink">{mine ? 'Your review' : review.author}</span>
        <span className="text-sm text-ink-muted">{formatDate(review.created_at)}</span>
      </div>

      {review.body && <p className="mt-2 max-w-prose whitespace-pre-line leading-relaxed text-ink">{review.body}</p>}

      {(mine || canDelete) && (
        <div className="mt-2 flex gap-4 text-sm">
          {mine && (
            <button
              type="button"
              onClick={onEdit}
              className="-ml-2 rounded px-2 py-2 text-primary hover:text-primary-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              Edit
            </button>
          )}
          {canDelete && (
            <button
              type="button"
              onClick={onDelete}
              className="-ml-2 rounded px-2 py-2 text-ink-muted hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
              {mine ? 'Delete' : 'Delete (admin)'}
            </button>
          )}
        </div>
      )}
    </article>
  );
}

/**
 * Rating summary, the signed-in customer's own review (or the form to write
 * one) and everyone else's reviews.
 */
export default function ProductReviews({ product, ratings, reviews, myReview, canReview }) {
  const { auth } = usePage().props;
  const [editing, setEditing] = useState(false);
  const [pendingDelete, setPendingDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const confirmDelete = () => {
    setDeleting(true);
    router.delete(`/reviews/${pendingDelete.id}`, {
      preserveScroll: true,
      onFinish: () => {
        setDeleting(false);
        setPendingDelete(null);
      },
    });
  };

  let action = null;
  if (myReview) {
    action = editing ? (
      <ReviewForm product={product} review={myReview} onDone={() => setEditing(false)} />
    ) : null;
  } else if (canReview) {
    action = <ReviewForm product={product} />;
  } else if (!auth.user) {
    action = (
      <p className="text-sm text-ink-muted">
        <Link href="/login" className="font-medium text-primary hover:text-primary-hover">
          Log in
        </Link>{' '}
        to review products you've bought.
      </p>
    );
  } else {
    action = <p className="text-sm text-ink-muted">Only customers who have bought this product can review it.</p>;
  }

  const others = reviews.data;
  const nothingYet = ratings.count === 0;

  return (
    <section id="reviews" className="mt-16 scroll-mt-6" aria-labelledby="reviews-heading">
      <h2 id="reviews-heading" className="font-display text-xl font-bold text-ink">
        Customer reviews
      </h2>

      <div className="mt-4 grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] lg:gap-12">
        <div className="space-y-6">
          {nothingYet ? (
            <p className="text-ink-muted">No reviews yet. Be the first to share what you think.</p>
          ) : (
            <RatingSummary ratings={ratings} />
          )}
          {action}
        </div>

        <div>
          {myReview && !editing && (
            <ReviewItem
              review={myReview}
              mine
              onEdit={() => setEditing(true)}
              onDelete={() => setPendingDelete(myReview)}
            />
          )}

          {others.length > 0 && (
            <div className="divide-y divide-stroke">
              {others.map((review) => (
                <ReviewItem key={review.id} review={review} onDelete={() => setPendingDelete(review)} />
              ))}
            </div>
          )}

          {!nothingYet && !myReview && others.length === 0 && (
            <p className="text-ink-muted">No written reviews yet.</p>
          )}

          <Pagination links={reviews.links} />
        </div>
      </div>

      <ConfirmDialog
        show={pendingDelete !== null}
        title="Delete this review?"
        confirmLabel="Delete review"
        processing={deleting}
        onConfirm={confirmDelete}
        onCancel={() => setPendingDelete(null)}
      >
        {pendingDelete?.user_id === auth.user?.id
          ? "Your rating and comment will be removed. You can write a new review afterwards."
          : `This removes ${pendingDelete?.author}'s review for everyone.`}
      </ConfirmDialog>
    </section>
  );
}
