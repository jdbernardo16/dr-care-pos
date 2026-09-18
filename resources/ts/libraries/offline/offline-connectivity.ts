import { BehaviorSubject, Observable } from "rxjs";

export type ConnectivityStatus = "online" | "offline";

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
    private generation = 0;
    private inFlight: Promise<ConnectivityStatus> | null = null;
    private statusSubject = new BehaviorSubject<ConnectivityStatus>( "online" );

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
        if ( this.timer !== null ) {
            return;
        }

        this.generation++;
        this.scheduleNext( 0, this.generation );
    }

    stop(): void {
        this.generation++;

        if ( this.timer !== null ) {
            clearTimeout( this.timer );
            this.timer = null;
        }
    }

    async checkNow(): Promise<ConnectivityStatus> {
        if ( this.inFlight ) {
            return this.inFlight;
        }

        this.inFlight = this.performCheck();

        try {
            return await this.inFlight;
        } finally {
            this.inFlight = null;
        }
    }

    private async performCheck(): Promise<ConnectivityStatus> {
        const reachable = await this.safePing();

        if ( reachable ) {
            this.failures = 0;
            this.emit( "online" );
        } else {
            this.failures++;

            if ( this.failures >= ( this.options.failuresToOffline ?? 2 ) ) {
                this.emit( "offline" );
            }
        }

        return this.status;
    }

    private async safePing(): Promise<boolean> {
        const timeoutMs = this.options.timeoutMs ?? 5000;
        let timeout: ReturnType<typeof setTimeout> | null = null;

        try {
            return await Promise.race( [
                this.ping( timeoutMs ),
                new Promise<boolean>( ( resolve ) => {
                    timeout = setTimeout( () => resolve( false ), timeoutMs );
                } ),
            ] );
        } catch ( exception ) {
            return false;
        } finally {
            if ( timeout !== null ) {
                clearTimeout( timeout );
            }
        }
    }

    private emit( status: ConnectivityStatus ): void {
        if ( this.statusSubject.getValue() !== status ) {
            this.statusSubject.next( status );
        }
    }

    private scheduleNext( delay: number, generation: number ): void {
        if ( generation !== this.generation ) {
            return;
        }

        this.timer = setTimeout( async () => {
            this.timer = null;

            await this.checkNow();

            if ( generation === this.generation ) {
                this.scheduleNext( this.options.intervalMs ?? 20000, generation );
            }
        }, delay );
    }
}
