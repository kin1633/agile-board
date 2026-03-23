import { Head, usePage } from '@inertiajs/react';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { repositories } from '@/routes/settings';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: '設定',
        href: repositories(),
    },
];

export default function Profile() {
    const { auth } = usePage().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="アカウント設定" />

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="GitHubアカウント"
                        description="GitHubでログインしているアカウント情報です"
                    />

                    <div className="space-y-4">
                        <div className="grid gap-1">
                            <p className="text-sm font-medium">名前</p>
                            <p className="text-sm text-muted-foreground">
                                {auth.user.name}
                            </p>
                        </div>
                        <div className="grid gap-1">
                            <p className="text-sm font-medium">
                                GitHubログイン
                            </p>
                            <p className="text-sm text-muted-foreground">
                                @{(auth.user as { github_login?: string }).github_login}
                            </p>
                        </div>
                    </div>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
