import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsShell } from '@/components/workspace-settings/settings-shell';
import { home } from '@/routes/dashboard';
import { index as settingsIndex } from '@/routes/settings';

type CategoryStyle = {
    key: string;
    label: string;
    label_bn: string;
    theme: string;
    accent: string;
};

type PageProps = {
    brand: {
        name: string;
        website: string;
        primary_color: string;
        font: string;
        image_label: string;
        card_language: string;
        has_logo: boolean;
        has_dark_logo: boolean;
    };
    placeholders: { name: string; image_label: string };
    fonts: Array<{ key: string; label: string }>;
    categories: CategoryStyle[];
    previewVersion: string;
};

const SELECT_CLASS =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none';

export default function BrandSettings({
    brand,
    placeholders,
    fonts,
    categories,
    previewVersion,
}: PageProps) {
    const [previewCategory, setPreviewCategory] = useState('');
    const [previewFailed, setPreviewFailed] = useState(false);
    const previewUrl = `/settings/brand/preview?v=${previewVersion}${
        previewCategory ? `&category=${previewCategory}` : ''
    }`;

    return (
        <>
            <Head title="Brand" />
            <SettingsShell>
                <Heading
                    variant="small"
                    title="Brand"
                    description="Name, logo, colours, and font used on every photo card."
                />
                <div className="grid max-w-5xl gap-8 lg:grid-cols-[1fr_360px]">
                    <Form
                        action="/settings/brand"
                        method="post"
                        className="space-y-6"
                        onSuccess={() => setPreviewFailed(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Brand name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            defaultValue={brand.name}
                                            placeholder={placeholders.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="website">
                                            Website or handle
                                        </Label>
                                        <Input
                                            id="website"
                                            name="website"
                                            defaultValue={brand.website}
                                            placeholder="yourpage.com"
                                        />
                                        <InputError message={errors.website} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="primary_color">
                                            Brand colour
                                        </Label>
                                        <Input
                                            id="primary_color"
                                            name="primary_color"
                                            type="color"
                                            defaultValue={brand.primary_color}
                                            className="h-9 w-24 p-1"
                                        />
                                        <InputError
                                            message={errors.primary_color}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="font">Font</Label>
                                        <select
                                            id="font"
                                            name="font"
                                            defaultValue={brand.font}
                                            className={SELECT_CLASS}
                                        >
                                            {fonts.map((font) => (
                                                <option
                                                    key={font.key}
                                                    value={font.key}
                                                >
                                                    {font.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.font} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="card_language">
                                            Card language
                                        </Label>
                                        <select
                                            id="card_language"
                                            name="card_language"
                                            defaultValue={brand.card_language}
                                            className={SELECT_CLASS}
                                        >
                                            <option value="bn">Bangla</option>
                                            <option value="en">English</option>
                                        </select>
                                        <p className="text-sm text-muted-foreground">
                                            The AI writes card headlines in this
                                            language. Saying "in English" in a
                                            Telegram message overrides it for
                                            that post.
                                        </p>
                                        <InputError
                                            message={errors.card_language}
                                        />
                                    </div>
                                    <div className="grid gap-2 sm:col-span-2">
                                        <Label htmlFor="image_label">
                                            Image label
                                        </Label>
                                        <Input
                                            id="image_label"
                                            name="image_label"
                                            defaultValue={brand.image_label}
                                            placeholder={
                                                placeholders.image_label
                                            }
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Shown in the corner of cards with a
                                            photo, followed by the brand name.
                                        </p>
                                        <InputError
                                            message={errors.image_label}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <LogoField
                                        name="logo"
                                        label="Logo (light cards)"
                                        hasLogo={brand.has_logo}
                                        error={errors.logo}
                                    />
                                    <LogoField
                                        name="logo_dark"
                                        label="Logo (dark cards, optional)"
                                        hasLogo={brand.has_dark_logo}
                                        error={errors.logo_dark}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <h3 className="text-sm font-medium">
                                        Category styles
                                    </h3>
                                    <div className="divide-y rounded-md border">
                                        {categories.map((category) => (
                                            <div
                                                key={category.key}
                                                className="flex flex-wrap items-center gap-3 p-3"
                                            >
                                                <span className="min-w-36 text-sm">
                                                    {category.label} ·{' '}
                                                    {category.label_bn}
                                                </span>
                                                <select
                                                    aria-label={`${category.label} theme`}
                                                    name={`categories[${category.key}][theme]`}
                                                    defaultValue={
                                                        category.theme
                                                    }
                                                    className={`${SELECT_CLASS} w-28`}
                                                >
                                                    <option value="light">
                                                        Light
                                                    </option>
                                                    <option value="dark">
                                                        Dark
                                                    </option>
                                                </select>
                                                <Input
                                                    aria-label={`${category.label} accent colour`}
                                                    name={`categories[${category.key}][accent]`}
                                                    type="color"
                                                    defaultValue={
                                                        category.accent
                                                    }
                                                    className="h-9 w-16 p-1"
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    <InputError message={errors.categories} />
                                </div>

                                <Button disabled={processing} type="submit">
                                    Save brand
                                </Button>
                            </>
                        )}
                    </Form>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <h3 className="text-sm font-medium">Preview</h3>
                            <select
                                aria-label="Preview category"
                                value={previewCategory}
                                onChange={(event) => {
                                    setPreviewFailed(false);
                                    setPreviewCategory(event.target.value);
                                }}
                                className={`${SELECT_CLASS} w-40`}
                            >
                                <option value="">No category</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.key}
                                        value={category.key}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {previewFailed ? (
                            <p className="rounded-md border p-4 text-sm text-muted-foreground">
                                The preview could not be rendered. The server
                                needs python3 and Pillow with libraqm (Ubuntu:
                                apt install python3-pil libraqm0).
                            </p>
                        ) : (
                            <img
                                key={previewUrl}
                                src={previewUrl}
                                alt="Sample photo card"
                                className="w-full rounded-md border"
                                onError={() => setPreviewFailed(true)}
                            />
                        )}
                        <p className="text-xs text-muted-foreground">
                            Save to update the preview.
                        </p>
                    </div>
                </div>
            </SettingsShell>
        </>
    );
}

function LogoField({
    name,
    label,
    hasLogo,
    error,
}: {
    name: string;
    label: string;
    hasLogo: boolean;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type="file"
                accept="image/png,image/jpeg,image/webp"
            />
            {hasLogo && (
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input type="checkbox" name={`remove_${name}`} value="1" />
                    Remove the current logo
                </label>
            )}
            <InputError message={error} />
        </div>
    );
}

BrandSettings.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: home() },
        { title: 'Settings', href: settingsIndex() },
        { title: 'Brand', href: '/settings/brand' },
    ],
};
