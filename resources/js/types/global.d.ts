import type { SharedData } from '@/types';

// Daten, die jede Seite erhält (App\Http\Middleware\HandleInertiaRequests::share)
declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedData & { [key: string]: unknown };
    }
}
