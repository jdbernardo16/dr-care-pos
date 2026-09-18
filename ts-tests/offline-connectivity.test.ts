import { describe, expect, test, jest } from '@jest/globals';
import { OfflineConnectivity } from '../resources/ts/libraries/offline/offline-connectivity';

describe( 'Offline connectivity', () => {
    test( 'goes offline after the configured number of failures', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 2 } );

        expect( connectivity.status ).toBe( 'online' );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'online' );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'offline' );
    } );

    test( 'recovers to online after a successful ping', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );

        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'offline' );

        ping.mockImplementation( async () => true );
        await connectivity.checkNow();
        expect( connectivity.status ).toBe( 'online' );
    } );

    test( 'emits status changes to subscribers', async () => {
        const ping = jest.fn( async () => false );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );
        const statuses: string[] = [];

        const subscription = connectivity.status$.subscribe( ( status ) => statuses.push( status ) );
        await connectivity.checkNow();
        subscription.unsubscribe();

        expect( statuses ).toEqual( [ 'online', 'offline' ] );
    } );

    test( 'treats a throwing ping as a failure', async () => {
        const ping = jest.fn( async () => {
            throw new Error( 'network down' );
        } );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 1 } );

        await connectivity.checkNow();

        expect( connectivity.status ).toBe( 'offline' );
    } );
} );
