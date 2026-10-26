import { Form, Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SettingsShell } from '@/components/workspace-settings/settings-shell';
import { home } from '@/routes/dashboard';
import { index as settingsIndex } from '@/routes/settings';

type Person = {
    id: number;
    name: string;
    aliases: string[];
    photo_url: string;
};

type PageProps = {
    people: Person[];
};

export default function CardPeople({ people }: PageProps) {
    return (
        <>
            <Head title="Card people" />
            <SettingsShell>
                <Heading
                    variant="small"
                    title="Card people"
                    description="Photos of people your posts often mention. When a card headline names one of them, their photo appears as a round avatar on the card."
                />
                <div className="max-w-3xl space-y-8">
                    <Form
                        action="/settings/card-people"
                        method="post"
                        className="space-y-4"
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            placeholder="পরীমনি"
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="aliases">
                                            Other spellings
                                        </Label>
                                        <Input
                                            id="aliases"
                                            name="aliases"
                                            placeholder="পরিমনি, Porimoni, Pori Moni"
                                        />
                                        <InputError message={errors.aliases} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="photo">Photo</Label>
                                    <Input
                                        id="photo"
                                        name="photo"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        A clear photo with the face near the
                                        top. Saving a name that is already here
                                        replaces its photo. Use photos you have
                                        the right to publish.
                                    </p>
                                    <InputError message={errors.photo} />
                                </div>
                                <Button disabled={processing} type="submit">
                                    Add person
                                </Button>
                            </>
                        )}
                    </Form>

                    {people.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No people yet.
                        </p>
                    ) : (
                        <ul className="grid gap-3 sm:grid-cols-2">
                            {people.map((person) => (
                                <li
                                    key={person.id}
                                    className="flex items-center gap-3 rounded-lg border p-3"
                                >
                                    <img
                                        src={person.photo_url}
                                        alt={person.name}
                                        className="size-14 rounded-full object-cover"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium">
                                            {person.name}
                                        </div>
                                        {person.aliases.length > 0 && (
                                            <div className="truncate text-sm text-muted-foreground">
                                                {person.aliases.join(', ')}
                                            </div>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                `/settings/card-people/${person.id}`,
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </SettingsShell>
        </>
    );
}

CardPeople.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: home() },
        { title: 'Settings', href: settingsIndex() },
        { title: 'Card people', href: '/settings/card-people' },
    ],
};
