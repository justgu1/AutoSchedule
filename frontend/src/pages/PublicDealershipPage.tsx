import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { DealershipMap } from '../components/DealershipMap';
import { ApiError } from '../lib/apiClient';
import { getPublicDealership } from '../lib/dealerships';

/** Sem conta, sem role -- é a tela que o cliente final abre pra conhecer a concessionária antes de agendar. */
export function PublicDealershipPage() {
    const { slug } = useParams<{ slug: string }>();

    const dealership = useQuery({
        queryKey: ['public-dealership', slug],
        queryFn: () => getPublicDealership(slug!),
        enabled: slug !== undefined,
        retry: false,
    });

    if (dealership.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    if (dealership.isError || !dealership.data) {
        const notFound = dealership.error instanceof ApiError && dealership.error.status === 404;

        return (
            <Alert severity="error">
                {notFound ? 'Concessionária não encontrada.' : 'Não foi possível carregar a concessionária.'}
            </Alert>
        );
    }

    const data = dealership.data;
    const fullAddress = `${data.address}, ${data.number} - ${data.neighborhood}, ${data.city}/${data.state}`;

    return (
        <Paper sx={{ overflow: 'hidden' }}>
            {data.photo_url && (
                <Box
                    component="img"
                    src={data.photo_url}
                    alt={data.name}
                    sx={{ width: '100%', maxHeight: 320, objectFit: 'cover', display: 'block' }}
                />
            )}
            <Stack spacing={2} sx={{ p: 3 }}>
                <Typography variant="h4" component="h1">
                    {data.name}
                </Typography>

                <Stack spacing={0.5}>
                    <Typography>{fullAddress}</Typography>
                    {data.complement && <Typography color="text.secondary">{data.complement}</Typography>}
                    <Typography color="text.secondary">CEP {data.zip_code}</Typography>
                    {data.phone && <Typography color="text.secondary">Telefone: {data.phone}</Typography>}
                    {data.email && <Typography color="text.secondary">E-mail: {data.email}</Typography>}
                </Stack>

                <DealershipMap address={fullAddress} />

                {data.seller_name && (
                    <>
                        <Divider />
                        <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                            <Avatar>{data.seller_name.charAt(0)}</Avatar>
                            <Stack>
                                <Typography variant="subtitle2">Vendedor responsável</Typography>
                                <Typography color="text.secondary">{data.seller_name}</Typography>
                            </Stack>
                        </Stack>
                    </>
                )}

                <Divider />
                <Stack spacing={1}>
                    <Typography variant="h6" component="h2">
                        Veículos
                    </Typography>
                    {(data.vehicles?.length ?? 0) === 0 && (
                        <Typography color="text.secondary">Em breve -- nenhum veículo cadastrado ainda.</Typography>
                    )}
                </Stack>
            </Stack>
        </Paper>
    );
}
