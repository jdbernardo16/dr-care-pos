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
    } );

    test( 'keeps the previous data when the refresh fails', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-failure` );
        await database.open();
        const fetcher = jest.fn( async () => {
            throw new Error( 'offline' );
        } );
        const service = new OfflineSnapshotService( database, fetcher );

        const refreshed = await service.refresh();

        expect( refreshed ).toBe( false );
        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 0 );
    } );
} );
