const STORAGE_KEY = "ns-device-identity";

interface DeviceIdentity {
    device_id: string;
    device_secret: string;
}

function uuid(): string {
    if ( typeof crypto.randomUUID === "function" ) {
        return crypto.randomUUID();
    }

    const bytes = new Uint8Array( 16 );
    crypto.getRandomValues( bytes );
    bytes[ 6 ] = ( bytes[ 6 ] & 0x0f ) | 0x40;
    bytes[ 8 ] = ( bytes[ 8 ] & 0x3f ) | 0x80;

    return [...bytes].map( ( b ) => b.toString( 16 ).padStart( 2, "0" ) )
        .join( "" )
        .replace( /(.{8})(.{4})(.{4})(.{4})(.{12})/, "$1-$2-$3-$4-$5" );
}

function generateSecret(): string {
    const bytes = new Uint8Array( 32 );
    crypto.getRandomValues( bytes );
    let base64 = btoa( String.fromCharCode( ...bytes ) );
    return base64.replace( /[^a-zA-Z0-9]/g, "" ).slice( 0, 43 );
}

export function getDeviceIdentity(): DeviceIdentity {
    let raw: string | null = null;

    try {
        raw = localStorage.getItem( STORAGE_KEY );
    } catch ( e ) {
        // storage unavailable — fall through and generate a fresh identity
    }

    if ( raw ) {
        try {
            const parsed = JSON.parse( raw );
            if ( parsed && parsed.device_id && parsed.device_secret ) {
                return parsed;
            }
        } catch ( e ) {
            // corrupt payload — fall through and regenerate
        }
    }

    const identity: DeviceIdentity = {
        device_id: uuid(),
        device_secret: generateSecret(),
    };

    try {
        localStorage.setItem( STORAGE_KEY, JSON.stringify( identity ) );
    } catch ( e ) {
        // storage unavailable — still return the identity for this session
    }

    return identity;
}
