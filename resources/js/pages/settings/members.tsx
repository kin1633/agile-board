import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { members as membersRoute } from '@/routes/settings';
import { update as memberUpdate } from '@/routes/settings/members';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'メンバー設定', href: membersRoute().url },
];

interface MemberRow {
    id: number;
    github_login: string;
    display_name: string;
    daily_hours: number;
}

interface Props {
    members: MemberRow[];
}

export default function MembersSettings({ members }: Props) {
    const [editingMember, setEditingMember] = useState<MemberRow | null>(null);

    const { data, setData, patch, processing, errors } = useForm({
        display_name: '',
        daily_hours: 6,
    });

    const openEdit = (member: MemberRow) => {
        setData({
            display_name: member.display_name,
            daily_hours: member.daily_hours,
        });
        setEditingMember(member);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingMember) {
            return;
        }
        patch(memberUpdate({ member: editingMember.id }).url, {
            onSuccess: () => setEditingMember(null),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="メンバー設定" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">メンバー管理</h1>
                        {/* メンバーはGitHub OAuth認証時に自動作成されるため、手動追加・削除は不要 */}
                        <p className="mt-1 text-sm text-muted-foreground">
                            メンバーはGitHub OAuthログイン時に自動登録されます
                        </p>
                    </div>
                </div>

                {editingMember && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">
                            メンバーを編集
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        表示名
                                    </label>
                                    <input
                                        type="text"
                                        value={data.display_name}
                                        onChange={(e) =>
                                            setData(
                                                'display_name',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.display_name && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.display_name}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        1日の稼働時間
                                    </label>
                                    <input
                                        type="number"
                                        value={data.daily_hours}
                                        onChange={(e) =>
                                            setData(
                                                'daily_hours',
                                                Number(e.target.value),
                                            )
                                        }
                                        min={0}
                                        max={24}
                                        step={0.5}
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.daily_hours && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.daily_hours}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    更新
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setEditingMember(null)}
                                    className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                                >
                                    キャンセル
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {members.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {members.map((member) => (
                                <li
                                    key={member.id}
                                    className="flex items-center justify-between px-6 py-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {member.display_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            @{member.github_login}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <span className="text-sm text-muted-foreground">
                                            {member.daily_hours} 時間/日
                                        </span>
                                        <button
                                            onClick={() => openEdit(member)}
                                            className="rounded px-2 py-1 text-xs hover:bg-muted/50"
                                        >
                                            編集
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            メンバーが登録されていません
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
