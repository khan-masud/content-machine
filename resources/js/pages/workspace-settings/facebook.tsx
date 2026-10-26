import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsShell } from '@/components/workspace-settings/settings-shell';
import { home } from '@/routes/dashboard';
import { index as settingsIndex } from '@/routes/settings';

type PageProps = {
    pageId: string;
    pageName: string | null;
    connected: boolean;
    publishEnabled: boolean;
    captionLanguage: string;
};

export default function FacebookSettings({
    pageId,
    pageName,
    connected,
    publishEnabled,
    captionLanguage,
}: PageProps) {
    return (
        <>
            <Head title="Facebook" />
            <SettingsShell>
                <Heading
                    variant="small"
                    title="Facebook Page"
                    description="Publish and schedule approved posts, with their photo cards, straight to your Facebook Page. Free: uses Facebook's own Graph API."
                />
                <div className="max-w-3xl space-y-6">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={connected ? 'default' : 'outline'}>
                            {connected
                                ? `Connected: ${pageName ?? pageId}`
                                : 'Not connected'}
                        </Badge>
                        {connected && (
                            <Badge
                                variant={publishEnabled ? 'default' : 'outline'}
                            >
                                {publishEnabled
                                    ? 'Publishing on'
                                    : 'Publishing off'}
                            </Badge>
                        )}
                    </div>
                    <Form
                        action="/settings/facebook"
                        method="post"
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="page_id">Page ID</Label>
                                    <Input
                                        id="page_id"
                                        name="page_id"
                                        defaultValue={pageId}
                                        placeholder="Numbers only, from the Page's About section"
                                    />
                                    <InputError message={errors.page_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="page_access_token">
                                        Page access token
                                    </Label>
                                    <Input
                                        id="page_access_token"
                                        name="page_access_token"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={
                                            connected
                                                ? 'Leave empty to keep the saved token'
                                                : 'A long-lived Page token with pages_manage_posts'
                                        }
                                    />
                                    <InputError
                                        message={errors.page_access_token}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Checked with Facebook before saving,
                                        then encrypted. See
                                        docs/guides/facebook-page.md for how to
                                        get a token that does not expire.
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="caption_language">
                                        Caption language
                                    </Label>
                                    <select
                                        id="caption_language"
                                        name="caption_language"
                                        defaultValue={captionLanguage}
                                        className="flex h-9 w-48 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none"
                                    >
                                        <option value="bn">Bangla</option>
                                        <option value="en">English</option>
                                    </select>
                                    <InputError
                                        message={errors.caption_language}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="publish_enabled"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="publish_enabled"
                                        value="1"
                                        defaultChecked={publishEnabled}
                                    />
                                    Publish to this Page (Telegram /post_now and
                                    /schedule use Facebook instead of
                                    PostSyncer)
                                </label>
                                <div className="flex gap-2">
                                    <Button disabled={processing} type="submit">
                                        {connected ? 'Save' : 'Connect Page'}
                                    </Button>
                                    {connected && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                router.delete(
                                                    '/settings/facebook',
                                                )
                                            }
                                        >
                                            Disconnect
                                        </Button>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsShell>
        </>
    );
}

FacebookSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: home() },
        { title: 'Settings', href: settingsIndex() },
        { title: 'Facebook', href: '/settings/facebook' },
    ],
};
