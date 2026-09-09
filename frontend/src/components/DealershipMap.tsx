import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';

const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;

interface DealershipMapProps {
    address: string;
}

/**
 * Maps Embed API, modo "place" -- só exibição por endereço, sem geocoding
 * nem Places autocomplete (`docs/02-architecture/decisions/ADR-008.md`). Sem
 * `VITE_GOOGLE_MAPS_API_KEY` configurado, mostra um aviso em vez de um
 * iframe quebrado -- mapa é complemento, não pode travar a página.
 */
export function DealershipMap({ address }: DealershipMapProps) {
    if (!GOOGLE_MAPS_API_KEY) {
        return <Alert severity="info">Mapa indisponível no momento.</Alert>;
    }

    const src = `https://www.google.com/maps/embed/v1/place?key=${GOOGLE_MAPS_API_KEY}&q=${encodeURIComponent(address)}`;

    return (
        <Box sx={{ borderRadius: 1, overflow: 'hidden', lineHeight: 0 }}>
            <iframe
                title="Localização da concessionária"
                width="100%"
                height="300"
                style={{ border: 0 }}
                loading="lazy"
                src={src}
            />
        </Box>
    );
}
