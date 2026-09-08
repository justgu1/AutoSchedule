import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useQuery } from '@tanstack/react-query';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { ApiError } from '../lib/apiClient';
import { getPublicVehicle } from '../lib/vehicles';

/** Sem conta, sem role -- passo 1 do fluxo de agendamento (`README.md`): visualizar os detalhes do veículo. */
export function PublicVehiclePage() {
    const { id } = useParams<{ id: string }>();

    const vehicle = useQuery({
        queryKey: ['public-vehicle', id],
        queryFn: () => getPublicVehicle(id!),
        enabled: id !== undefined,
        retry: false,
    });

    if (vehicle.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (vehicle.isError || !vehicle.data) {
        const notFound = vehicle.error instanceof ApiError && vehicle.error.status === 404;

        return (
            <Alert severity="error">
                {notFound ? 'Veículo não encontrado.' : 'Não foi possível carregar o veículo.'}
            </Alert>
        );
    }

    const data = vehicle.data;
    const cover = data.images[0]?.url;

    return (
        <Paper sx={{ overflow: 'hidden' }}>
            {cover && (
                <Box
                    component="img"
                    src={cover}
                    alt=""
                    sx={{ width: '100%', maxHeight: 360, objectFit: 'cover', display: 'block' }}
                />
            )}

            {data.images.length > 1 && (
                <Stack direction="row" spacing={1} sx={{ p: 2, overflowX: 'auto' }}>
                    {data.images.map((image) => (
                        <Box
                            key={image.id}
                            component="img"
                            src={image.url}
                            alt=""
                            sx={{ width: 96, height: 72, objectFit: 'cover', borderRadius: 1, flexShrink: 0 }}
                        />
                    ))}
                </Stack>
            )}

            <Stack spacing={2} sx={{ p: 3 }}>
                <Typography variant="h4" component="h1">
                    {data.brand} {data.model}
                </Typography>
                <Typography color="text.secondary">
                    {data.version}
                    {data.version && data.year ? ' -- ' : ''}
                    {data.year}
                </Typography>
                <Typography variant="h5">
                    {Number(data.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                </Typography>

                {data.description && <Typography>{data.description}</Typography>}

                <Grid container spacing={1} sx={{ mt: 1 }}>
                    <Grid size={12}>
                        <Typography variant="subtitle2">Concessionária</Typography>
                        <Typography
                            component={RouterLink}
                            to={`/concessionarias/${data.dealership.slug}`}
                            sx={{ color: 'primary.main', textDecoration: 'none' }}
                        >
                            {data.dealership.name}
                        </Typography>
                        <Typography color="text.secondary">
                            {data.dealership.city}/{data.dealership.state}
                        </Typography>
                    </Grid>
                </Grid>
            </Stack>
        </Paper>
    );
}
