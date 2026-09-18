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

    test( 'a failed replacement keeps the previous data', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-atomic` );
        await database.open();

        await database.replaceCollection( 'products', [ { id: 1 }, { id: 2 } ] );

        await expect( database.replaceCollection( 'products', undefined as any ) ).rejects.toThrow();
        expect( await database.count( 'products' ) ).toBe( 2 );

        await expect(
            database.replaceCollection( 'products', [ { name: 'missing id' } as any ] ),
        ).rejects.toThrow();
        expect( await database.count( 'products' ) ).toBe( 2 );
    } );

    test( 'returns empty results and null metadata for missing data', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-empty` );
        await database.open();

        expect( await database.getCollection( 'products' ) ).toEqual( [] );
        expect( await database.getMeta( 'missing' ) ).toBe( null );
        expect( await database.count( 'outbox' ) ).toBe( 0 );
        expect( await database.count( 'counters' ) ).toBe( 0 );
    } );

    test( 'rejects when used before open', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-closed` );

        await expect( database.getCollection( 'products' ) ).rejects.toThrow();
    } );

    test( 'open is idempotent when called concurrently', async () => {
        const database = new OfflineDatabase( `test-${Date.now()}-concurrent` );

        await Promise.all( [ database.open(), database.open(), database.open() ] );
        await database.replaceCollection( 'products', [ { id: 1 } ] );

        expect( await database.count( 'products' ) ).toBe( 1 );
    } );
} );
