import { OfflineDatabase } from './offline-db';

export interface SnapshotResponse {
    generated_at: string;
    server_time: number;
    products: any[];
    categories: any[];
    customers: any[];
    payment_types: any[];
    register: { open_register_id: number | null };
    options: Record<string, any>;
}

export class OfflineSnapshotService {
    private timer: ReturnType<typeof setInterval> | null = null;

    constructor(
        private database: OfflineDatabase,
        private fetcher: () => Promise<SnapshotResponse>,
        private intervalMs = 15 * 60 * 1000,
    ) {}

    async refresh(): Promise<boolean> {
        try {
            const snapshot = await this.fetcher();

            await this.database.replaceCollection( 'products', snapshot.products );
            await this.database.replaceCollection( 'categories', snapshot.categories );
            await this.database.replaceCollection( 'customers', snapshot.customers );
            await this.database.setMeta( 'payment_types', snapshot.payment_types );
            await this.database.setMeta( 'register', snapshot.register );
            await this.database.setMeta( 'options', snapshot.options );
            await this.database.setMeta( 'snapshot', {
                generated_at: snapshot.generated_at,
                fetched_at: Date.now(),
                server_time: snapshot.server_time,
                offset: snapshot.server_time - Date.now(),
            } );

            return true;
        } catch ( exception ) {
            console.warn( 'Unable to refresh the offline snapshot', exception );

            return false;
        }
    }

    start(): void {
        this.refresh();

        if ( this.timer ) {
            clearInterval( this.timer );
        }

        this.timer = setInterval( () => this.refresh(), this.intervalMs );
    }

    stop(): void {
        if ( this.timer ) {
            clearInterval( this.timer );
            this.timer = null;
        }
    }
}
