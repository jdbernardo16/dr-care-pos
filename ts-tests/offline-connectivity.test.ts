import { describe, expect, test, jest, afterEach } from '@jest/globals';
import { OfflineConnectivity } from '../resources/ts/libraries/offline/offline-connectivity';

describe( 'Offline connectivity', () => {
    afterEach( () => jest.useRealTimers() );

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

    test( 'start checks immediately and then on every interval', async () => {
        jest.useFakeTimers();
        const ping = jest.fn( async () => true );
        const connectivity = new OfflineConnectivity( ping, { intervalMs: 1000 } );

        connectivity.start();
        await jest.advanceTimersByTimeAsync( 0 );
        expect( ping ).toHaveBeenCalledTimes( 1 );

        await jest.advanceTimersByTimeAsync( 1000 );
        expect( ping ).toHaveBeenCalledTimes( 2 );

        connectivity.stop();
        await jest.advanceTimersByTimeAsync( 5000 );
        expect( ping ).toHaveBeenCalledTimes( 2 );
    } );

    test( 'calling start twice does not double the checks', async () => {
        jest.useFakeTimers();
        const ping = jest.fn( async () => true );
        const connectivity = new OfflineConnectivity( ping, { intervalMs: 1000 } );

        connectivity.start();
        connectivity.start();

        await jest.advanceTimersByTimeAsync( 1000 );
        expect( ping ).toHaveBeenCalledTimes( 2 );

        connectivity.stop();
    } );

    test( 'concurrent checks are coalesced into one ping', async () => {
        let resolvePing: ( value: boolean ) => void = () => {};
        const ping = jest.fn(
            () =>
                new Promise<boolean>( ( resolve ) => {
                    resolvePing = resolve;
                } ),
        );
        const connectivity = new OfflineConnectivity( ping, { failuresToOffline: 2 } );

        const first = connectivity.checkNow();
        const second = connectivity.checkNow();

        resolvePing( false );

        await Promise.all( [ first, second ] );

        expect( ping ).toHaveBeenCalledTimes( 1 );
        expect( connectivity.status ).toBe( 'online' );
    } );

    test( 'a ping that never resolves times out as a failure', async () => {
        jest.useFakeTimers();
        const ping = jest.fn( () => new Promise<boolean>( () => {} ) );
        const connectivity = new OfflineConnectivity( ping, {
            failuresToOffline: 1,
            timeoutMs: 5000,
        } );

        const check = connectivity.checkNow();
        await jest.advanceTimersByTimeAsync( 5000 );

        expect( await check ).toBe( 'offline' );
    } );
} );
