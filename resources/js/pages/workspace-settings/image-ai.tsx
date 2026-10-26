import { Form, Head } from '@inertiajs/react';
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
    cloudflareAccountId: string;
    cloudflareConfigured: boolean;
    pollinationsEnabled: boolean;
};

export default function ImageAiSettings({
    cloudflareAccountId,
    cloudflareConfigured,
    pollinationsEnabled,
}: PageProps) {
    return (
        <>
            <Head title="Image AI" />
            <SettingsShell>
                <Heading
                    variant="small"
                    title="Image AI"
                    description="Free image generators for photo-card backgrounds. Cloudflare is tried first, then Pollinations."
                />
                <div className="max-w-3xl space-y-6">
                    <Badge
                        variant={cloudflareConfigured ? 'default' : 'outline'}
                    >
                        {cloudflareConfigured
                            ? 'Cloudflare Workers AI configured'
                            : 'Cloudflare not configured'}
                    </Badge>
                    <Form
                        action="/settings/image-ai"
                        method="post"
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="cloudflare_account_id">
                                        Cloudflare account ID
                                    </Label>
                                    <Input
                                        id="cloudflare_account_id"
                                        name="cloudflare_account_id"
                                        defaultValue={cloudflareAccountId}
                                        placeholder="32 characters, from the Cloudflare dashboard"
                                    />
                                    <InputError
                                        message={errors.cloudflare_account_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="cloudflare_api_token">
                                        Cloudflare API token (Workers AI)
                                    </Label>
                                    <Input
                                        id="cloudflare_api_token"
                                        name="cloudflare_api_token"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={
                                            cloudflareConfigured
                                                ? 'Leave empty to keep the saved token'
                                                : 'Paste a token with Workers AI read permission'
                                        }
                                    />
                                    <InputError
                                        message={errors.cloudflare_api_token}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        The token is encrypted in workspace
                                        settings and never sent back to the
                                        browser. Clear the account ID to stop
                                        using Cloudflare.
                                    </p>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="pollinations_enabled"
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name="pollinations_enabled"
                                        value="1"
                                        defaultChecked={pollinationsEnabled}
                                    />
                                    Use Pollinations.ai as a free fallback (no
                                    key; slower and rate limited)
                                </label>
                                <Button disabled={processing} type="submit">
                                    Save
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsShell>
        </>
    );
}

ImageAiSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: home() },
        { title: 'Settings', href: settingsIndex() },
        { title: 'Image AI', href: '/settings/image-ai' },
    ],
};
