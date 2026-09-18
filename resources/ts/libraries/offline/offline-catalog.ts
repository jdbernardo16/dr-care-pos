export interface OfflineUnitQuantity {
    id: number;
    unit_id: number;
    unit_name?: string;
    barcode?: string | null;
    unit?: { id: number; name: string } | null;
    sale_price: number;
    sale_price_net?: number;
    sale_price_gross?: number;
    quantity: number;
}

export interface OfflineProduct {
    id: number;
    name: string;
    sku?: string;
    barcode?: string;
    type?: string;
    category_id?: number;
    pinned?: boolean;
    position?: number;
    created_at?: string;
    tax_group_id?: number;
    tax_type?: string;
    tax_group?: any;
    selectedUnitQuantity?: OfflineUnitQuantity;
    unit_quantities: OfflineUnitQuantity[];
    galleries?: { id: number; url: string; featured?: boolean }[];
}

export interface OfflineCategory {
    id: number;
    name: string;
    parent_id?: number | null;
    preview?: string;
    position?: number;
    created_at?: string;
}

export interface OfflineCategoryView {
    categories: OfflineCategory[];
    products: OfflineProduct[];
    pinnedProducts: OfflineProduct[];
    previousCategory: OfflineCategory | false;
    currentCategory: OfflineCategory | false;
}

function sortCollection<T extends { position?: number; created_at?: string }>(
    items: T[],
    enableReordering: boolean,
): T[] {
    return [ ...items ].sort( ( first, second ) => {
        if ( enableReordering ) {
            return ( first.position ?? 0 ) - ( second.position ?? 0 );
        }

        return `${first.created_at ?? ''}`.localeCompare( `${second.created_at ?? ''}` );
    } );
}

export function buildCategoryView(
    products: OfflineProduct[],
    categories: OfflineCategory[],
    parentId: number | null = null,
    enableReordering = false,
): OfflineCategoryView {
    const currentCategory = parentId
        ? categories.find( ( category ) => Number( category.id ) === Number( parentId ) ) ?? false
        : false;

    const children = categories.filter(
        ( category ) => Number( category.parent_id ?? 0 ) === Number( parentId ?? 0 ),
    );

    const productsInCategory = parentId
        ? products.filter( ( product ) => Number( product.category_id ) === Number( parentId ) )
        : [];

    return {
        categories: sortCollection( children, enableReordering ),
        products: sortCollection( productsInCategory, enableReordering ),
        pinnedProducts: products.filter( ( product ) => product.pinned ),
        previousCategory: currentCategory
            ? categories.find(
                  ( category ) => Number( category.id ) === Number( currentCategory.parent_id ?? 0 ),
              ) ?? false
            : false,
        currentCategory,
    };
}

export function searchProducts(
    products: OfflineProduct[],
    term: string,
    limit = 20,
): OfflineProduct[] {
    const needle = term.trim().toLowerCase();

    if ( needle.length === 0 ) {
        return [];
    }

    return products
        .filter( ( product ) => {
            return (
                `${product.name ?? ''}`.toLowerCase().includes( needle ) ||
                `${product.sku ?? ''}`.toLowerCase().includes( needle ) ||
                `${product.barcode ?? ''}`.toLowerCase().includes( needle )
            );
        } )
        .slice( 0, Math.max( 0, limit ) );
}

export function findByBarcode(
    products: OfflineProduct[],
    barcode: string,
): OfflineProduct | false {
    const needle = barcode.trim();

    if ( needle.length === 0 ) {
        return false;
    }

    /**
     * Mirrors the online endpoint: unit-level barcodes take
     * precedence over the product-level barcode.
     */
    for ( const product of products ) {
        const matchingUnit = ( product.unit_quantities ?? [] ).find(
            ( unitQuantity ) => `${unitQuantity.barcode ?? ''}`.trim() === needle,
        );

        if ( matchingUnit ) {
            return { ...product, selectedUnitQuantity: matchingUnit };
        }

        if ( `${product.barcode ?? ''}`.trim() === needle ) {
            return { ...product, selectedUnitQuantity: product.unit_quantities?.[ 0 ] };
        }
    }

    return false;
}
