const STORAGE_KEY = "ns-device-identity";

interface DeviceIdentity {
    device_id: string;
    device_secret: string;
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
        device_id: crypto.randomUUID(),
        device_secret: generateSecret(),
    };

    try {
        localStorage.setItem( STORAGE_KEY, JSON.stringify( identity ) );
    } catch ( e ) {
        // storage unavailable — still return the identity for this session
    }

    return identity;
}
