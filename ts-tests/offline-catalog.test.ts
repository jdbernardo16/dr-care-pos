import { describe, expect, test } from '@jest/globals';
import {
    buildCategoryView,
    findByBarcode,
    searchProducts,
} from '../resources/ts/libraries/offline/offline-catalog';

const categories = [
    { id: 1, name: 'Root A', parent_id: null, position: 2, created_at: '2026-01-01' },
    { id: 2, name: 'Root B', parent_id: null, position: 1, created_at: '2026-01-02' },
    { id: 3, name: 'Child A1', parent_id: 1, position: 1, created_at: '2026-01-03' },
];

const products = [
    { id: 10, name: 'Biogesic', barcode: '111', category_id: 1, pinned: true, position: 2, created_at: '2026-01-01', unit_quantities: [{ id: 100, unit_id: 1, sale_price: 5, quantity: 10 }] },
    { id: 11, name: 'Alaxan', barcode: '222', category_id: 1, pinned: false, position: 1, created_at: '2026-01-02', unit_quantities: [{ id: 101, unit_id: 1, sale_price: 7, quantity: 3 }] },
    { id: 12, name: 'Skelan', barcode: '333', category_id: 3, pinned: false, position: 1, created_at: '2026-01-03', unit_quantities: [{ id: 102, unit_id: 1, sale_price: 9, quantity: 0 }] },
];

describe( 'Offline catalog', () => {
    test( 'root view returns top-level categories, pinned products and no products', () => {
        const view = buildCategoryView( products as any, categories as any, null, false );

        expect( view.products ).toEqual( [] );
        expect( view.categories.map( ( category ) => category.id ) ).toEqual( [ 1, 2 ] );
        expect( view.pinnedProducts.map( ( product ) => product.id ) ).toEqual( [ 10 ] );
        expect( view.currentCategory ).toBe( false );
        expect( view.previousCategory ).toBe( false );
    } );

    test( 'category view returns children, products and previous category', () => {
        const view = buildCategoryView( products as any, categories as any, 1, false );

        expect( view.categories.map( ( category ) => category.id ) ).toEqual( [ 3 ] );
        expect( view.products.map( ( product ) => product.id ) ).toEqual( [ 10, 11 ] );
        expect( view.currentCategory?.id ).toBe( 1 );
        expect( view.previousCategory ).toBe( false );
    } );

    test( 'reordering option sorts by position', () => {
        const view = buildCategoryView( products as any, categories as any, 1, true );

        expect( view.products.map( ( product ) => product.id ) ).toEqual( [ 11, 10 ] );
    } );

    test( 'search is case insensitive and limited', () => {
        expect( searchProducts( products as any, 'biog', 20 ).map( ( product ) => product.id ) ).toEqual( [ 10 ] );

        const limited = searchProducts( products as any, 'a', 1 );
        expect( limited.length ).toBe( 1 );
    } );

    test( 'barcode lookup returns the product or false', () => {
        expect( findByBarcode( products as any, '222' )?.id ).toBe( 11 );
        expect( findByBarcode( products as any, '999' ) ).toBe( false );
    } );
} );
