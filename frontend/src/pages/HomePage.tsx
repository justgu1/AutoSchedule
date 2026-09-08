import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import Pagination from '@mui/material/Pagination';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import { VehicleFilterBar } from '../components/VehicleFilterBar';
import { getPublicVehicleFacets, listPublicVehicles, type VehicleFilterParams } from '../lib/vehicles';

const PER_PAGE = 12;

/** Catálogo público -- mesmo estoque pra todo mundo, dono logado ou não (`GET /vehicles` sem `scope`). */
export function HomePage() {
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<VehicleFilterParams>({});

    const vehicles = useQuery({
        queryKey: ['public-vehicles', filters, page],
        queryFn: () => listPublicVehicles(filters, page, PER_PAGE),
    });
    const facets = useQuery({ queryKey: ['public-vehicles', 'filters'], queryFn: () => getPublicVehicleFacets() });

    return (
        <Stack spacing={2}>
            <Typography variant="h4" component="h1">
                Encontre seu próximo veículo
            </Typography>

            <VehicleFilterBar
                value={filters}
                facets={facets.data}
                onChange={(value) => {
                    setFilters(value);
                    setPage(1);
                }}
            />

            {vehicles.isPending && (
                <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                    <CircularProgress aria-label="Carregando" />
                </Box>
            )}

            {vehicles.isError && <Alert severity="error">Não foi possível carregar o catálogo.</Alert>}

            {vehicles.data?.data.length === 0 && (
                <Alert severity="info">Nenhum veículo encontrado -- ajuste os filtros e tente de novo.</Alert>
            )}

            {vehicles.data && vehicles.data.data.length > 0 && (
                <Grid container spacing={2}>
                    {vehicles.data.data.map((vehicle) => (
                        <Grid key={vehicle.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                            <Card>
                                <CardActionArea component={RouterLink} to={`/veiculos/${vehicle.id}`}>
                                    {vehicle.photo_url ? (
                                        <CardMedia component="img" height="160" image={vehicle.photo_url} alt="" />
                                    ) : (
                                        <Box
                                            sx={{
                                                height: 160,
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                bgcolor: 'grey.200',
                                            }}
                                        >
                                            <Typography color="text.secondary">Sem foto</Typography>
                                        </Box>
                                    )}
                                    <CardContent>
                                        <Typography variant="subtitle1" component="h2">
                                            {vehicle.brand} {vehicle.model}
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            {vehicle.version}
                                            {vehicle.version && vehicle.year ? ' -- ' : ''}
                                            {vehicle.year}
                                        </Typography>
                                        <Typography variant="h6" sx={{ mt: 1 }}>
                                            {Number(vehicle.price).toLocaleString('pt-BR', {
                                                style: 'currency',
                                                currency: 'BRL',
                                            })}
                                        </Typography>
                                    </CardContent>
                                </CardActionArea>
                            </Card>
                        </Grid>
                    ))}
                </Grid>
            )}

            {vehicles.data && vehicles.data.meta.last_page > 1 && (
                <Stack sx={{ alignItems: 'center' }}>
                    <Pagination
                        page={page}
                        count={vehicles.data.meta.last_page}
                        onChange={(_, value) => setPage(value)}
                    />
                </Stack>
            )}
        </Stack>
    );
}
