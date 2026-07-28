import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label as FieldLabel } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import practiceSettings from '@/routes/practice-settings';
import type { PracticeIdentity } from '@/types';

export function IdentitySection({ identity }: { identity: PracticeIdentity }) {
    const form = useForm({
        name: identity.name,
        legal_name: identity.legal_name ?? '',
        address_line1: identity.address_line1 ?? '',
        address_line2: identity.address_line2 ?? '',
        postal_code: identity.postal_code ?? '',
        city: identity.city ?? '',
        country_code: identity.country_code ?? '',
        contact_email: identity.contact_email ?? '',
        contact_phone: identity.contact_phone ?? '',
        timezone: identity.timezone,
        locale: identity.locale,
        reason: '',
    });

    const logoForm = useForm<{ logo: File | null; reason: string }>({
        logo: null,
        reason: '',
    });
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    function submit() {
        form.patch(practiceSettings.identity.update().url, {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    }

    function submitLogo() {
        logoForm.post(practiceSettings.logo.update().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                logoForm.reset();
                setLogoPreview(null);
            },
        });
    }

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">Practice identity</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="text-sm text-muted-foreground">
                        Used on the dossier PDF header and label footers.
                        Evidence is stored in UTC and shown in this
                        timezone — the dossier states which one it was
                        rendered in.
                    </p>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-name">
                                Display name
                            </FieldLabel>
                            <Input
                                id="settings-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-legal-name">
                                Legal name (optional)
                            </FieldLabel>
                            <Input
                                id="settings-legal-name"
                                value={form.data.legal_name}
                                onChange={(e) =>
                                    form.setData('legal_name', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <FieldLabel htmlFor="settings-address1">
                            Address
                        </FieldLabel>
                        <Input
                            id="settings-address1"
                            value={form.data.address_line1}
                            onChange={(e) =>
                                form.setData('address_line1', e.target.value)
                            }
                            placeholder="Line 1"
                        />
                        <Input
                            aria-label="Address line 2"
                            value={form.data.address_line2}
                            onChange={(e) =>
                                form.setData('address_line2', e.target.value)
                            }
                            placeholder="Line 2 (optional)"
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-city">
                                City
                            </FieldLabel>
                            <Input
                                id="settings-city"
                                value={form.data.city}
                                onChange={(e) =>
                                    form.setData('city', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-postal-code">
                                Postal code
                            </FieldLabel>
                            <Input
                                id="settings-postal-code"
                                value={form.data.postal_code}
                                onChange={(e) =>
                                    form.setData('postal_code', e.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-country">
                                Country code
                            </FieldLabel>
                            <Input
                                id="settings-country"
                                maxLength={2}
                                value={form.data.country_code}
                                onChange={(e) =>
                                    form.setData(
                                        'country_code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="FR"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-contact-email">
                                Contact email
                            </FieldLabel>
                            <Input
                                id="settings-contact-email"
                                type="email"
                                value={form.data.contact_email}
                                onChange={(e) =>
                                    form.setData(
                                        'contact_email',
                                        e.target.value,
                                    )
                                }
                            />
                            {form.errors.contact_email && (
                                <p className="text-sm text-destructive">
                                    {form.errors.contact_email}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-contact-phone">
                                Contact phone
                            </FieldLabel>
                            <Input
                                id="settings-contact-phone"
                                value={form.data.contact_phone}
                                onChange={(e) =>
                                    form.setData(
                                        'contact_phone',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-timezone">
                                Timezone
                            </FieldLabel>
                            <Input
                                id="settings-timezone"
                                value={form.data.timezone}
                                onChange={(e) =>
                                    form.setData('timezone', e.target.value)
                                }
                                placeholder="Europe/Paris"
                            />
                            {form.errors.timezone && (
                                <p className="text-sm text-destructive">
                                    {form.errors.timezone}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <FieldLabel htmlFor="settings-locale">
                                Locale
                            </FieldLabel>
                            <Input
                                id="settings-locale"
                                value={form.data.locale}
                                onChange={(e) =>
                                    form.setData('locale', e.target.value)
                                }
                                placeholder="fr"
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <FieldLabel htmlFor="settings-identity-reason">
                            Reason
                        </FieldLabel>
                        <Textarea
                            id="settings-identity-reason"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            placeholder="Why is this changing?"
                        />
                        {form.errors.reason && (
                            <p className="text-sm text-destructive">
                                {form.errors.reason}
                            </p>
                        )}
                    </div>

                    <div>
                        <Button
                            onClick={submit}
                            disabled={
                                form.processing || !form.data.reason.trim()
                            }
                        >
                            Save identity
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">Logo</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="text-sm text-muted-foreground">
                        Used on the dossier PDF header and label footers.
                    </p>
                    <div className="flex items-center gap-4">
                        {(logoPreview ?? identity.logo_url) && (
                            <img
                                src={logoPreview ?? identity.logo_url ?? ''}
                                alt="Practice logo"
                                className="h-16 w-16 rounded-md border object-contain"
                            />
                        )}
                        <Input
                            type="file"
                            accept="image/*"
                            aria-label="Logo file"
                            onChange={(e) => {
                                const file = e.target.files?.[0] ?? null;
                                logoForm.setData('logo', file);
                                setLogoPreview(
                                    file ? URL.createObjectURL(file) : null,
                                );
                            }}
                        />
                    </div>
                    {logoForm.errors.logo && (
                        <p className="text-sm text-destructive">
                            {logoForm.errors.logo}
                        </p>
                    )}
                    <div className="grid gap-2">
                        <FieldLabel htmlFor="settings-logo-reason">
                            Reason
                        </FieldLabel>
                        <Textarea
                            id="settings-logo-reason"
                            value={logoForm.data.reason}
                            onChange={(e) =>
                                logoForm.setData('reason', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <Button
                            onClick={submitLogo}
                            disabled={
                                logoForm.processing ||
                                !logoForm.data.logo ||
                                !logoForm.data.reason.trim()
                            }
                        >
                            Save logo
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
