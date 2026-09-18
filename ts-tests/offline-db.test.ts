import 'fake-indexeddb/auto';
import { describe, expect, test } from '@jest/globals';
import { OfflineDatabase } from '../resources/ts/libraries/offline/offline-db';

describe( 'Offline database', () => {
    test( 'stores and replaces collections', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}` );
        await database.open();

        await database.replaceCollection( 'products', [
            { id: 1, name: 'Product A' },
            { id: 2, name: 'Product B' },
        ] );

        expect( ( await database.getCollection( 'products' ) ).length ).toBe( 2 );

        await database.replaceCollection( 'products', [ { id: 2, name: 'Product B' } ] );
        const products = await database.getCollection( 'products' );

        expect( products.length ).toBe( 1 );
        expect( products[ 0 ].id ).toBe( 2 );
    } );

    test( 'stores metadata and counts records', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-meta` );
        await database.open();

        await database.setMeta( 'snapshot', { generated_at: '2026-09-18', offset: 1000 } );

        const meta = await database.getMeta( 'snapshot' );

        expect( meta.offset ).toBe( 1000 );
        expect( await database.count( 'products' ) ).toBe( 0 );
    } );
} );
