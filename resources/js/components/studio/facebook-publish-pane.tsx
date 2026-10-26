import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export type FacebookPublishState = {
    ready: boolean;
    page_name: string | null;
    publication: {
        id: number;
        state: string;
        scheduled_for: string | null;
        published_at: string | null;
        permalink: string | null;
        error: string | null;
        comment_error: string | null;
    } | null;
};

type Props = {
    postId: number;
    facebook: FacebookPublishState;
    approvalState: string;
    timezone: string;
};

function formatWhen(iso: string | null, timezone: string): string {
    if (!iso) {
        return '';
    }

    return new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function FacebookPublishPane({
    postId,
    facebook,
    approvalState,
    timezone,
}: Props) {
    const [when, setWhen] = useState('');
    const [busy, setBusy] = useState(false);
    const publication = facebook.publication;
    const state = publication?.state ?? null;
    const sending = state === 'queued' || state === 'publishing';
    const base = `/posts/${postId}/facebook`;

    // Publishing happens in the queue; refresh until Facebook answers.
    useEffect(() => {
        if (!sending) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['post'] }),
            4000,
        );

        return () => window.clearInterval(timer);
    }, [sending]);

    function post(url: string, data: Record<string, string> = {}) {
        setBusy(true);
        router.post(url, data, {
            preserveScroll: true,
            onError: (errors) => {
                const message = Object.values(errors)[0];

                if (message) {
                    window.alert(message);
                }
            },
            onFinish: () => setBusy(false),
        });
    }

    if (!facebook.ready && publication === null) {
        return null;
    }

    return (
        <section className="pane">
            <div className="pane-head">
                <span className="k">
                    Facebook
                    {facebook.page_name ? ` · ${facebook.page_name}` : ''}
                </span>
            </div>

            {state === 'published' && (
                <div className="grid gap-1 text-sm">
                    <span className="badge">
                        Published{' '}
                        {formatWhen(
                            publication?.published_at ?? null,
                            timezone,
                        )}
                    </span>
                    {publication?.permalink && (
                        <a
                            href={publication.permalink}
                            target="_blank"
                            rel="noreferrer"
                            className="underline"
                        >
                            View on Facebook
                        </a>
                    )}
                    {publication?.comment_error && (
                        <span className="text-muted-foreground">
                            First comment failed: {publication.comment_error}
                        </span>
                    )}
                </div>
            )}

            {state === 'scheduled' && publication && (
                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <span className="badge">
                        Scheduled for{' '}
                        {formatWhen(publication.scheduled_for, timezone)}
                    </span>
                    <button
                        type="button"
                        className="advance"
                        disabled={busy}
                        onClick={() => post(`${base}/${publication.id}/cancel`)}
                    >
                        Cancel
                    </button>
                </div>
            )}

            {sending && <span className="badge">Publishing to Facebook…</span>}

            {state === 'failed' && publication && (
                <div className="grid gap-2 text-sm">
                    <span className="text-destructive">
                        {publication.error}
                    </span>
                    <button
                        type="button"
                        className="advance justify-self-start"
                        disabled={busy}
                        onClick={() => post(`${base}/${publication.id}/retry`)}
                    >
                        Retry
                    </button>
                </div>
            )}

            {state === 'uncertain' && publication && (
                <div className="grid gap-2 text-sm">
                    <span className="text-destructive">
                        {publication.error}
                    </span>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            className="advance"
                            disabled={busy}
                            onClick={() =>
                                post(`${base}/${publication.id}/mark-published`)
                            }
                        >
                            It is on the Page
                        </button>
                        <button
                            type="button"
                            className="advance"
                            disabled={busy}
                            onClick={() => {
                                if (
                                    window.confirm(
                                        'Only retry if the post is NOT on your Facebook Page, or it will appear twice.',
                                    )
                                ) {
                                    post(`${base}/${publication.id}/retry`, {
                                        confirmed_not_on_page: '1',
                                    });
                                }
                            }}
                        >
                            Not there, retry
                        </button>
                    </div>
                </div>
            )}

            {state === null && facebook.ready && (
                <div className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                    {approvalState !== 'approved' ? (
                        <span className="text-muted-foreground">
                            Approve the post to publish it.
                        </span>
                    ) : (
                        <>
                            <button
                                type="button"
                                className="advance"
                                disabled={busy}
                                onClick={() => post(base)}
                            >
                                Publish now
                            </button>
                            <input
                                type="datetime-local"
                                aria-label="Schedule time"
                                value={when}
                                onChange={(event) =>
                                    setWhen(event.target.value)
                                }
                                className="h-9 rounded-md border border-input bg-transparent px-2 text-sm"
                            />
                            <button
                                type="button"
                                className="advance"
                                disabled={busy || when === ''}
                                onClick={() => post(base, { when })}
                            >
                                Schedule ({timezone})
                            </button>
                        </>
                    )}
                </div>
            )}
        </section>
    );
}
