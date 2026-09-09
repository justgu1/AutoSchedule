import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import SpeedIcon from '@mui/icons-material/Speed';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router-dom';
import type { PublicVehicleSummary } from '../lib/vehicles';

const IMAGE_HEIGHT = 176;

/** Mesmo card em toda vitrine pública (home e página de concessionária) -- altura de imagem e campos fixos. */
export function VehicleCard({ vehicle }: { vehicle: PublicVehicleSummary }) {
    const price = Number(vehicle.price);

    return (
        <Card sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
            <CardActionArea
                component={RouterLink}
                to={`/veiculos/${vehicle.id}`}
                sx={{ display: 'flex', flexDirection: 'column', alignItems: 'stretch', flex: 1 }}
            >
                {vehicle.photo_url ? (
                    <CardMedia
                        component="img"
                        image={vehicle.photo_url}
                        alt=""
                        sx={{ height: IMAGE_HEIGHT, objectFit: 'cover' }}
                    />
                ) : (
                    <Box
                        sx={{
                            height: IMAGE_HEIGHT,
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            bgcolor: 'grey.200',
                        }}
                    >
                        <Typography color="text.secondary">Sem foto</Typography>
                    </Box>
                )}
                <CardContent sx={{ flex: 1, display: 'flex', flexDirection: 'column', width: '100%' }}>
                    <Typography variant="subtitle1" component="h2" noWrap>
                        {vehicle.brand} {vehicle.model}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" noWrap sx={{ minHeight: '1.25em' }}>
                        {vehicle.version ?? ' '}
                    </Typography>

                    <Stack direction="row" spacing={2} sx={{ mt: 1, mb: 1, color: 'text.secondary' }}>
                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                            <CalendarTodayIcon sx={{ fontSize: 16 }} />
                            <Typography variant="body2">{vehicle.model_year ?? '--'}</Typography>
                        </Stack>
                        <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                            <SpeedIcon sx={{ fontSize: 16 }} />
                            <Typography variant="body2">
                                {vehicle.mileage_km === null
                                    ? '--'
                                    : `${vehicle.mileage_km.toLocaleString('pt-BR')} km`}
                            </Typography>
                        </Stack>
                    </Stack>

                    <Typography variant="h6" sx={{ mt: 'auto' }}>
                        {price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                    </Typography>
                </CardContent>
            </CardActionArea>
        </Card>
    );
}
