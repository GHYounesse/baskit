import DangerButton from '@/Components/DangerButton';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';

/**
 * Styled replacement for window.confirm(). The confirm button names the
 * action ("Delete product"), and both buttons lock while the request runs.
 */
export default function ConfirmDialog({
    show,
    title,
    children,
    confirmLabel = 'Delete',
    cancelLabel = 'Cancel',
    processingLabel = 'Deleting…',
    processing = false,
    onConfirm,
    onCancel,
}) {
    return (
        <Modal show={show} onClose={onCancel} closeable={!processing} maxWidth="md">
            <div className="p-6">
                <h2 className="font-display text-lg font-bold text-ink">{title}</h2>
                <div className="mt-2 text-sm text-ink-muted">{children}</div>

                <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton onClick={onCancel} disabled={processing}>
                        {cancelLabel}
                    </SecondaryButton>
                    <DangerButton onClick={onConfirm} disabled={processing}>
                        {processing ? processingLabel : confirmLabel}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
