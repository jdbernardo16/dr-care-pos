const DEFAULT_DB_NAME = 'drcare-offline';
const DB_VERSION = 1;

type StoreName = 'products' | 'categories' | 'customers' | 'meta' | 'outbox' | 'counters';

interface StoreDefinition {
    name: StoreName;
    keyPath: string;
}

const STORES: StoreDefinition[] = [
    { name: 'products', keyPath: 'id' },
    { name: 'categories', keyPath: 'id' },
    { name: 'customers', keyPath: 'id' },
    { name: 'meta', keyPath: 'key' },
    { name: 'outbox', keyPath: 'uuid' },
    { name: 'counters', keyPath: 'key' },
];

export class OfflineDatabase {
    private connection: IDBDatabase | null = null;

    constructor( private name: string = DEFAULT_DB_NAME ) {}

    open(): Promise<void> {
        if ( this.connection ) {
            return Promise.resolve();
        }

        return new Promise( ( resolve, reject ) => {
            const request = indexedDB.open( this.name, DB_VERSION );

            request.onupgradeneeded = () => {
                const database = request.result;

                for ( const store of STORES ) {
                    if ( ! database.objectStoreNames.contains( store.name ) ) {
                        database.createObjectStore( store.name, { keyPath: store.keyPath } );
                    }
                }
            };

            request.onsuccess = () => {
                this.connection = request.result;
                resolve();
            };

            request.onerror = () => reject( request.error );
        } );
    }

    async replaceCollection( store: StoreName, items: any[] ): Promise<void> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, 'readwrite' );
            const objectStore = transaction.objectStore( store );

            objectStore.clear();

            for ( const item of items ) {
                objectStore.put( item );
            }

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject( transaction.error );
        } );
    }

    async getCollection<T = any>( store: StoreName ): Promise<T[]> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, 'readonly' );
            const request = transaction.objectStore( store ).getAll();

            request.onsuccess = () => resolve( request.result as T[] );
            request.onerror = () => reject( request.error );
        } );
    }

    async count( store: StoreName ): Promise<number> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( store, 'readonly' );
            const request = transaction.objectStore( store ).count();

            request.onsuccess = () => resolve( request.result );
            request.onerror = () => reject( request.error );
        } );
    }

    async setMeta( key: string, value: any ): Promise<void> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( 'meta', 'readwrite' );
            transaction.objectStore( 'meta' ).put( { key, value } );

            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject( transaction.error );
        } );
    }

    async getMeta<T = any>( key: string ): Promise<T | null> {
        const database = this.database();

        return new Promise( ( resolve, reject ) => {
            const transaction = database.transaction( 'meta', 'readonly' );
            const request = transaction.objectStore( 'meta' ).get( key );

            request.onsuccess = () => resolve( ( request.result?.value ?? null ) as T | null );
            request.onerror = () => reject( request.error );
        } );
    }

    private database(): IDBDatabase {
        if ( ! this.connection ) {
            throw new Error( 'The offline database has not been opened.' );
        }

        return this.connection;
    }
}
