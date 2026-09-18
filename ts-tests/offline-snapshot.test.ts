import 'fake-indexeddb/auto';
import { describe, expect, test, jest } from '@jest/globals';
import { OfflineDatabase } from '../resources/ts/libraries/offline/offline-db';
import { OfflineSnapshotService } from '../resources/ts/libraries/offline/offline-snapshot';

function buildSnapshot() {
    return {
        generated_at: '2026-09-18 10:00:00',
        server_time: Date.now() + 5000,
        products: [ { id: 1, name: 'Product A', unit_quantities: [] } ],
        categories: [ { id: 1, name: 'Category A', parent_id: null } ],
        customers: [ { id: 1, first_name: 'Walk-in', last_name: 'Customer' } ],
        payment_types: [ { id: 1, label: 'Cash', identifier: 'cash-payment' } ],
        register: { open_register_id: 3 },
        options: { ns_pos_enable_reordering: 'yes' },
    };
}

describe( 'Offline snapshot service', () => {
    test( 'persists the snapshot collections and metadata', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-snapshot` );
        await database.open();
        const fetcher = jest.fn( async () => buildSnapshot() );
        const service = new OfflineSnapshotService( database, fetcher );

        const refreshed = await service.refresh();

        expect( refreshed ).toBe( true );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 1 );
        expect( ( await database.getCollection( 'categories' ) ).length ).toBe( 1 );

        const meta = await database.getMeta( 'snapshot' );

        expect( meta.generated_at ).toBe( '2026-09-18 10:00:00' );
        expect( typeof meta.offset ).toBe( 'number' );

        const options = await database.getMeta( 'options' );

        expect( options.ns_pos_enable_reordering ).toBe( 'yes' );
        expect( ( await database.getCollection( 'customers' ) ).length ).toBe( 1 );
        expect( ( await database.getMeta( 'payment_types' ) ).length ).toBe( 1 );
        expect( ( await database.getMeta( 'register' ) ).open_register_id ).toBe( 3 );
        expect( typeof meta.fetched_at ).toBe( 'number' );
        expect( meta.offset ).toBe( meta.server_time - meta.fetched_at );
    } );

    test( 'keeps the previous data when a later refresh fails', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-failure` );
        await database.open();

        const fetcher = jest.fn( async () => buildSnapshot() );
        const service = new OfflineSnapshotService( database, fetcher );

        expect( await service.refresh() ).toBe( true );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 1 );

        fetcher.mockImplementation( async () => {
            throw new Error( 'offline' );
        } );

        expect( await service.refresh() ).toBe( false );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 1 );

        const meta = await database.getMeta( 'snapshot' );

        expect( meta.generated_at ).toBe( '2026-09-18 10:00:00' );
    } );

    test( 'overlapping refreshes are coalesced into one fetch', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-coalesce` );
        await database.open();

        let resolveFetch: ( value: any ) => void = () => {};
        const fetcher = jest.fn(
            () =>
                new Promise<any>( ( resolve ) => {
                    resolveFetch = resolve;
                } ),
        );
        const service = new OfflineSnapshotService( database, fetcher );

        const first = service.refresh();
        const second = service.refresh();

        resolveFetch( buildSnapshot() );

        await Promise.all( [ first, second ] );

        expect( fetcher ).toHaveBeenCalledTimes( 1 );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 1 );
    } );

    test( 'calling start twice does not trigger two fetches', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-start` );
        await database.open();

        const fetcher = jest.fn( async () => buildSnapshot() );
        const service = new OfflineSnapshotService( database, fetcher, 60000 );

        await service.start();
        await service.start();

        expect( fetcher ).toHaveBeenCalledTimes( 1 );

        service.stop();
    } );
} );
