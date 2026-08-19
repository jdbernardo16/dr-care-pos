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
    const raw = localStorage.getItem( STORAGE_KEY );

    if ( raw ) {
        try {
            const parsed = JSON.parse( raw );
            if ( parsed && parsed.device_id && parsed.device_secret ) {
                return parsed;
            }
        } catch ( e ) {
            // fall through and regenerate
        }
    }

    const identity: DeviceIdentity = {
        device_id: crypto.randomUUID(),
        device_secret: generateSecret(),
    };

    localStorage.setItem( STORAGE_KEY, JSON.stringify( identity ) );

    return identity;
}
