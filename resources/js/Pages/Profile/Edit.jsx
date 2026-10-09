import PageHeader from '@/Components/PageHeader';
import ShopLayout from '@/Layouts/ShopLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <div className="bg-canvas text-ink">
            <div className="mx-auto max-w-3xl px-6 py-8">
                <Head title="Profile" />

                <PageHeader title="Profile" description="Your details, password and account." />

                <div className="space-y-6">
                    <div className="rounded-lg border border-stroke bg-surface p-5 sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-xl"
                        />
                    </div>

                    <div className="rounded-lg border border-stroke bg-surface p-5 sm:p-8">
                        <UpdatePasswordForm className="max-w-xl" />
                    </div>

                    <div className="rounded-lg border border-danger bg-surface p-5 sm:p-8">
                        <DeleteUserForm className="max-w-xl" />
                    </div>
                </div>
            </div>
        </div>
    );
}

Edit.layout = (page) => <ShopLayout>{page}</ShopLayout>;
