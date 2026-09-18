import { nsHttpClient } from "~/bootstrap";
import { OfflineDatabase } from "./offline-db";
import {
    buildCategoryView,
    findByBarcode,
    searchProducts,
    type OfflineProduct,
} from "./offline-catalog";
import { OfflineConnectivity } from "./offline-connectivity";
import { OfflineSnapshotService, type SnapshotResponse } from "./offline-snapshot";

class OfflineCatalog {
    constructor( private database: OfflineDatabase ) {}

    async getCategoryView( parentId: number | null, enableReordering = false ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );
        const categories = await this.database.getCollection( "categories" );

        return buildCategoryView( products, categories, parentId, enableReordering );
    }

    async search( term: string, limit = 20 ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );

        return searchProducts( products, term, limit );
    }

    async findByBarcode( barcode: string ) {
        const products = await this.database.getCollection<OfflineProduct>( "products" );

        return findByBarcode( products, barcode );
    }
}

export interface OfflineContext {
    database: OfflineDatabase;
    snapshot: OfflineSnapshotService;
    connectivity: OfflineConnectivity;
    catalog: OfflineCatalog;
}

let context: OfflineContext | null = null;
let initialization: Promise<OfflineContext> | null = null;

function heartbeat( timeoutMs: number ): Promise<boolean> {
    return new Promise( ( resolve ) => {
        let subscription: any = null;
        const timer = setTimeout( () => {
            subscription?.unsubscribe();
            resolve( false );
        }, timeoutMs );

        subscription = nsHttpClient.get( "/api/pos/heartbeat" ).subscribe( {
            next: () => {
                clearTimeout( timer );
                resolve( true );
            },
            error: () => {
                clearTimeout( timer );
                resolve( false );
            },
        } );
    } );
}

function fetchSnapshot(): Promise<SnapshotResponse> {
    return new Promise( ( resolve, reject ) => {
        nsHttpClient.get( "/api/pos/offline-snapshot" ).subscribe( {
            next: ( snapshot: SnapshotResponse ) => resolve( snapshot ),
            error: ( error: any ) => reject( error ),
        } );
    } );
}

async function createOfflineContext(): Promise<OfflineContext> {
    const database = new OfflineDatabase();

    await database.open();

    const connectivity = new OfflineConnectivity( heartbeat );
    const snapshot = new OfflineSnapshotService( database, fetchSnapshot );
    const catalog = new OfflineCatalog( database );
    const offlineContext: OfflineContext = {
        database,
        snapshot,
        connectivity,
        catalog,
    };

    connectivity.start();
    snapshot.start();

    window.addEventListener( "offline", () => connectivity.checkNow() );
    window.addEventListener( "online", () => connectivity.checkNow() );

    context = offlineContext;

    // Exposed for debugging and the offline E2E test.
    ( window as any ).nsOffline = context;

    return offlineContext;
}

/**
 * Idempotent: concurrent callers (e.g. the banner, whose mounted hook
 * runs before the POS page's, and the POS page itself) share a single
 * initialization.
 */
export function initializeOfflineMode(): Promise<OfflineContext> {
    if ( context ) {
        return Promise.resolve( context );
    }

    if ( ! initialization ) {
        initialization = createOfflineContext().catch( ( exception ) => {
            initialization = null;
            throw exception;
        } );
    }

    return initialization;
}

export function getOfflineContext(): OfflineContext | null {
    return context;
}
