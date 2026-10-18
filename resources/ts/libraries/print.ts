declare const nsHooks;
declare const nsSnackBar;
declare const __;

export default class Print {
    private urls;
    private options;
    private printingURL     =   {
        'refund'    :   'refund_printing_url',
        'sale'      :   'sale_printing_url',
        'payment'   :   'payment_printing_url',
        'z-report'  :   'z_report_printing_url',
    }

    constructor({ urls, options }) {
        this.urls       =   urls;
        this.options    =   options;
    }

    setCustomPrintingUrl( documentType, url ) {
        this.printingURL[ documentType ] = url;
    }

    processRegularPrinting( reference_id, documentType ) {
        const item  =   document.querySelector( '#printing-section' );

        if ( item ) {
            item.remove();
        }

        console.log({ documentType })

        const url               =   this.urls[ this.printingURL[ documentType ] ].replace( '{reference_id}', reference_id );
        const printSection      =   document.createElement( 'iframe' );

        printSection.id         =   'printing-section';
        printSection.className  =   'hidden';
        printSection.src        =   url; // should be different regarding the document

        document.body.appendChild( printSection );
        
        setTimeout( () => {
            document.querySelector( '#printing-section' ).remove();
        }, 5000 );
    }

    process( reference_id, document, mode = 'aloud' ) {
        switch( this.options.ns_pos_printing_gateway ) {
            case 'default' : this.processRegularPrinting( reference_id, document ); break;
            case 'rawbt' : this.processRawBTPrinting( reference_id, document ); break;
            default: this.processCustomPrinting( reference_id, this.options.ns_pos_printing_gateway, document, mode ); break;
        }
    }

    /**
     * Sends the receipt directly to the RawBT app (Android).
     *
     * The server renders a full ESC/POS byte stream (bold, centering,
     * double-size header, raster logo, cut command) and the payload is
     * handed over using the official RawBT API — "rawbt:base64,<data>".
     * The base64 rides in the URI PATH, which Android preserves
     * byte-for-byte; the commonly-copied "intent://base64,..." variant
     * puts it in the URI HOST, which Android lowercases and re-parses,
     * corrupting the payload.
     */
    processRawBTPrinting( reference_id, document, mode = 'aloud' ) {
        if ( document !== 'sale' ) {
            return this.processRegularPrinting( reference_id, document );
        }

        const baseUrl            =   this.urls[ this.printingURL[ document ] ];
        const separator          =   baseUrl.includes( '?' ) ? '&' : '?';
        const dataUrl            =   baseUrl.replace( '{reference_id}', reference_id ) + separator + 'format=rawbt';

        fetch( dataUrl )
            .then( response => {
                const contentType    =   response.headers.get( 'content-type' ) || '';

                if ( ! response.ok || ! contentType.includes( 'application/octet-stream' ) ) {
                    throw new Error( __( 'Unable to load the receipt to print. Make sure you are signed in and the order still exists.' ) );
                }

                return response.arrayBuffer();
            })
            .then( buffer => {
                const bytes      =   new Uint8Array( buffer );
                let binary       =   '';

                bytes.forEach( byte => binary += String.fromCharCode( byte ) );

                const base64     =   btoa( binary );

                window.location.href    =   'rawbt:base64,' + base64;
            })
            .catch( exception => {
                nsSnackBar.error( exception.message || __( 'An error unexpected occurred while printing.' ) );
            })
    }

    processCustomPrinting( reference_id, gateway, document, mode = 'aloud' ) {
        const params    =   { printed: false, reference_id, gateway, document, mode };
        const result =  nsHooks.applyFilters( 'ns-custom-print', {
            params,
            promise: () => new Promise( ( resolve, reject ) => {
                reject({
                    status: 'error',
                    message: __( `The selected print gateway doesn't support this type of printing.`, 'NsPrintAdapter' )
                });
            }),
        });

        result.promise().then( result => {
            nsSnackBar.success( result.message );
        }).catch( exception => {
            nsSnackBar.error( exception.message || __( `An error unexpected occurred while printing.` ) );
        })    
    }
}
