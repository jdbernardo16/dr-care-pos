import { BehaviorSubject, Observable } from 'rxjs';

export type ConnectivityStatus = 'online' | 'offline';

export interface ConnectivityOptions {
    intervalMs?: number;
    timeoutMs?: number;
    failuresToOffline?: number;
}

/**
 * Watches for server availability using an injected ping.
 * Two consecutive failures (configurable) mark the POS as offline;
 * a single success brings it back online.
 */
export class OfflineConnectivity {
    private failures = 0;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private stopped = false;
    private statusSubject = new BehaviorSubject<ConnectivityStatus>( 'online' );

    constructor(
        private ping: ( timeoutMs: number ) => Promise<boolean>,
        private options: ConnectivityOptions = {},
    ) {}

    get status$(): Observable<ConnectivityStatus> {
        return this.statusSubject.asObservable();
    }

    get status(): ConnectivityStatus {
        return this.statusSubject.getValue();
    }

    start(): void {
        this.stopped = false;
        this.scheduleNext( 0 );
    }

    stop(): void {
        this.stopped = true;

        if ( this.timer ) {
            clearTimeout( this.timer );
            this.timer = null;
        }
    }

    async checkNow(): Promise<ConnectivityStatus> {
        const reachable = await this.safePing();

        if ( reachable ) {
            this.failures = 0;
            this.emit( 'online' );
        } else {
            this.failures++;

            if ( this.failures >= ( this.options.failuresToOffline ?? 2 ) ) {
                this.emit( 'offline' );
            }
        }

        return this.status;
    }

    private async safePing(): Promise<boolean> {
        try {
            return await this.ping( this.options.timeoutMs ?? 5000 );
        } catch ( exception ) {
            return false;
        }
    }

    private emit( status: ConnectivityStatus ): void {
        if ( this.statusSubject.getValue() !== status ) {
            this.statusSubject.next( status );
        }
    }

    private scheduleNext( delay: number ): void {
        if ( this.stopped ) {
            return;
        }

        this.timer = setTimeout( async () => {
            await this.checkNow();
            this.scheduleNext( this.options.intervalMs ?? 20000 );
        }, delay );
    }
}
