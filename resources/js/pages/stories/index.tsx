import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { ExternalLink, ChevronDown, ChevronRight } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { index as storiesIndex, update as issueUpdate } from '@/routes/issues';
import { index as workLogsIndex } from '@/routes/work-logs';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ストーリー・タスク', href: storiesIndex().url },
];

interface TaskRow {
    id: number;
    github_issue_number: number;
    title: string;
    state: 'open' | 'closed';
    assignee_login: string | null;
    estimated_hours: number | null;
    actual_hours: number | null;
    completion_rate: number | null;
    repository: { full_name: string };
}

interface LabelItem {
    id: number;
    name: string;
    color: string;
}

interface StoryRow {
    id: number;
    github_issue_number: number;
    title: string;
    state: 'open' | 'closed';
    assignee_login: string | null;
    story_points: number | null;
    epic_id: number | null;
    sub_issues: TaskRow[];
    labels: LabelItem[];
    repository: { full_name: string };
}

interface EpicOption {
    id: number;
    title: string;
}

interface Props {
    stories: StoryRow[];
    epics: EpicOption[];
}

/** GitHub Issue へのリンクを生成する */
function githubUrl(fullName: string, issueNumber: number): string {
    return `https://github.com/${fullName}/issues/${issueNumber}`;
}

/** タスクの予定工数を blur 時に PATCH 送信する（実績はワークログ経由） */
function handleEstimatedHoursBlur(taskId: number, value: string) {
    const parsed = value === '' ? null : parseFloat(value);
    if (parsed !== null && (isNaN(parsed) || parsed < 0)) {
        return;
    }
    router.patch(
        issueUpdate({ issue: taskId }).url,
        { estimated_hours: parsed },
        { preserveScroll: true },
    );
}

/** ストーリー行コンポーネント */
function StoryItem({ story, epics }: { story: StoryRow; epics: EpicOption[] }) {
    const [expanded, setExpanded] = useState(false);

    /** エピック紐付けを更新する */
    const handleEpicChange = (epicId: string) => {
        router.patch(
            issueUpdate({ issue: story.id }).url,
            { epic_id: epicId === '' ? null : Number(epicId) },
            { preserveScroll: true },
        );
    };

    return (
        <li>
            {/* ストーリー行 */}
            <div className="flex items-center justify-between px-6 py-3">
                <div className="flex min-w-0 items-center gap-3">
                    {/* 展開トグル（タスクがある場合のみ表示） */}
                    {story.sub_issues.length > 0 ? (
                        <button
                            onClick={() => setExpanded((v) => !v)}
                            className="shrink-0 text-muted-foreground hover:text-foreground"
                            aria-label={expanded ? '折り畳む' : '展開する'}
                        >
                            {expanded ? (
                                <ChevronDown size={14} />
                            ) : (
                                <ChevronRight size={14} />
                            )}
                        </button>
                    ) : (
                        <span className="w-[14px] shrink-0" />
                    )}

                    {/* ステータスドット */}
                    <span
                        className={`h-2 w-2 shrink-0 rounded-full ${story.state === 'open' ? 'bg-green-500' : 'bg-muted-foreground'}`}
                    />

                    {/* Issue 番号 + タイトル */}
                    <span className="text-xs text-muted-foreground">
                        #{story.github_issue_number}
                    </span>
                    <span className="truncate text-sm">{story.title}</span>

                    {/* エピック選択ドロップダウン */}
                    <select
                        value={story.epic_id ?? ''}
                        onChange={(e) => handleEpicChange(e.target.value)}
                        className="shrink-0 rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs text-purple-700 focus:outline-none"
                    >
                        <option value="">案件なし</option>
                        {epics.map((epic) => (
                            <option key={epic.id} value={epic.id}>
                                {epic.title}
                            </option>
                        ))}
                    </select>

                    {/* ラベル */}
                    {story.labels.map((label) => (
                        <span
                            key={label.id}
                            className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs"
                        >
                            {label.name}
                        </span>
                    ))}
                </div>

                <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                    {story.assignee_login && (
                        <span>@{story.assignee_login}</span>
                    )}
                    {story.story_points != null && (
                        <span className="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">
                            {story.story_points} pt
                        </span>
                    )}
                    {/* GitHub リンク */}
                    <a
                        href={githubUrl(
                            story.repository.full_name,
                            story.github_issue_number,
                        )}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-muted-foreground hover:text-foreground"
                        aria-label="GitHub で開く"
                    >
                        <ExternalLink size={14} />
                    </a>
                </div>
            </div>

            {/* タスク一覧（展開時） */}
            {expanded && story.sub_issues.length > 0 && (
                <ul className="border-t border-sidebar-border/30 bg-muted/20">
                    {story.sub_issues.map((task) => (
                        <li
                            key={task.id}
                            className="flex items-center justify-between py-2 pr-6 pl-14"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <span
                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${task.state === 'open' ? 'bg-green-400' : 'bg-muted-foreground'}`}
                                />
                                <span className="text-xs text-muted-foreground">
                                    #{task.github_issue_number}
                                </span>
                                <span className="truncate text-xs">
                                    {task.title}
                                </span>
                            </div>

                            <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                                {task.assignee_login && (
                                    <span>@{task.assignee_login}</span>
                                )}
                                {/* 予定工数（編集可） */}
                                <label className="flex items-center gap-1">
                                    <span className="text-muted-foreground">
                                        予定
                                    </span>
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.25"
                                        defaultValue={
                                            task.estimated_hours ?? ''
                                        }
                                        onBlur={(e) =>
                                            handleEstimatedHoursBlur(
                                                task.id,
                                                e.target.value,
                                            )
                                        }
                                        placeholder="—"
                                        className="w-16 rounded border border-sidebar-border/50 bg-background px-1.5 py-0.5 text-right text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                    />
                                    <span>h</span>
                                </label>
                                {/* 実績工数はワークログから集計（読み取り専用） */}
                                <span className="flex items-center gap-1">
                                    <span className="text-muted-foreground">
                                        実績
                                    </span>
                                    <span className="tabular-nums">
                                        {task.actual_hours ?? '—'}
                                    </span>
                                    <span>h</span>
                                    {task.completion_rate !== null && (
                                        <span
                                            className={`rounded-full px-1.5 py-0.5 text-xs font-medium ${
                                                task.completion_rate >= 100
                                                    ? 'bg-green-100 text-green-700'
                                                    : task.completion_rate >= 80
                                                      ? 'bg-yellow-100 text-yellow-700'
                                                      : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {task.completion_rate}%
                                        </span>
                                    )}
                                </span>
                                {/* 実績入力ページへのリンク */}
                                <a
                                    href={workLogsIndex().url}
                                    className="text-xs text-blue-500 hover:underline"
                                    title="実績を入力する"
                                >
                                    記録
                                </a>
                                {/* GitHub リンク */}
                                <a
                                    href={githubUrl(
                                        task.repository.full_name,
                                        task.github_issue_number,
                                    )}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-muted-foreground hover:text-foreground"
                                    aria-label="GitHub で開く"
                                >
                                    <ExternalLink size={12} />
                                </a>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function StoriesIndex({ stories, epics }: Props) {
    const openCount = stories.filter((s) => s.state === 'open').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ストーリー・タスク" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            ストーリー・タスク
                        </h1>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {openCount} / {stories.length} オープン
                        </p>
                    </div>
                </div>

                {/* ストーリー一覧 */}
                {stories.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        ストーリーがありません。GitHub から同期してください。
                    </p>
                ) : (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        <ul className="divide-y divide-sidebar-border/50">
                            {stories.map((story) => (
                                <StoryItem
                                    key={story.id}
                                    story={story}
                                    epics={epics}
                                />
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
