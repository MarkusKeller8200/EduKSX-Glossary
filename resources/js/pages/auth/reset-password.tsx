import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** Neues Passwort setzen (#41) über den Link aus der E-Mail. */
export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/passwort-zuruecksetzen', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <>
            <Head title="Neues Passwort" />
            <div className="mx-auto max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Neues Passwort
                </h1>
                <form onSubmit={submit} className="mt-6 space-y-4" noValidate>
                    <div className="space-y-2">
                        <Label htmlFor="email">E-Mail</Label>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="email"
                            required
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password">
                            Neues Passwort (mind. 12 Zeichen)
                        </Label>
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password}
                            onChange={(e) =>
                                form.setData('password', e.target.value)
                            }
                        />
                        <InputError message={form.errors.password} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="password_confirmation">
                            Wiederholen
                        </Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(e) =>
                                form.setData(
                                    'password_confirmation',
                                    e.target.value,
                                )
                            }
                        />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        Passwort setzen
                    </Button>
                </form>
            </div>
        </>
    );
}
