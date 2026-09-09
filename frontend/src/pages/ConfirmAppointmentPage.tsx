import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams, useSearchParams } from 'react-router-dom';
import {
    cancelAppointmentByToken,
    confirmAppointmentByToken,
    getAppointmentByToken,
    type Appointment,
} from '../lib/appointments';
import { ApiError } from '../lib/apiClient';

/** Aberto pelo link do e-mail de confirmação -- mutação só acontece no clique, nunca no GET do link em si. */
export function ConfirmAppointmentPage() {
    const { id } = useParams<{ id: string }>();
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') ?? '';
    const queryClient = useQueryClient();

    const appointmentKey = ['appointment-by-token', id, token];

    const appointment = useQuery({
        queryKey: appointmentKey,
        queryFn: () => getAppointmentByToken(id!, token),
        enabled: id !== undefined && token !== '',
        retry: false,
    });

    function updateCache(updated: Appointment) {
        queryClient.setQueryData(appointmentKey, updated);
    }

    const confirmMutation = useMutation({
        mutationFn: () => confirmAppointmentByToken(id!, token),
        onSuccess: updateCache,
    });
    const cancelMutation = useMutation({
        mutationFn: () => cancelAppointmentByToken(id!, token),
        onSuccess: updateCache,
    });

    if (!id || !token) {
        return <Alert severity="error">Link inválido.</Alert>;
    }

    if (appointment.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress aria-label="Carregando" />
            </Box>
        );
    }

    if (appointment.isError || !appointment.data) {
        const notFound = appointment.error instanceof ApiError && appointment.error.status === 404;

        return (
            <Alert severity="error">
                {notFound
                    ? 'Agendamento não encontrado, ou o link expirou.'
                    : 'Não foi possível carregar o agendamento.'}
            </Alert>
        );
    }

    const data = appointment.data;
    const scheduledAt = new Date(data.scheduled_at).toLocaleString('pt-BR', { dateStyle: 'full', timeStyle: 'short' });
    const mutationError =
        (confirmMutation.error instanceof ApiError && confirmMutation.error) ||
        (cancelMutation.error instanceof ApiError && cancelMutation.error) ||
        null;

    return (
        <Paper sx={{ p: 3 }}>
            <Stack spacing={2}>
                <Typography variant="h5" component="h1">
                    Seu teste-drive
                </Typography>
                <Typography>Agendado para {scheduledAt}.</Typography>

                {data.status === 'pending' && (
                    <>
                        <Typography color="text.secondary">
                            Confirme se ainda quer fazer o teste-drive, ou cancele se não vai mais dar.
                        </Typography>
                        {mutationError && <Alert severity="error">{mutationError.message}</Alert>}
                        <Stack direction="row" spacing={2}>
                            <Button
                                variant="contained"
                                disabled={confirmMutation.isPending || cancelMutation.isPending}
                                onClick={() => confirmMutation.mutate()}
                            >
                                Confirmar teste-drive
                            </Button>
                            <Button
                                variant="outlined"
                                color="error"
                                disabled={confirmMutation.isPending || cancelMutation.isPending}
                                onClick={() => cancelMutation.mutate()}
                            >
                                Cancelar
                            </Button>
                        </Stack>
                    </>
                )}

                {data.status === 'confirmed' && <Alert severity="success">Teste-drive confirmado!</Alert>}
                {data.status === 'cancelled' && <Alert severity="info">Este agendamento foi cancelado.</Alert>}
                {data.status === 'completed' && <Alert severity="success">Teste-drive já concluído.</Alert>}
                {data.status === 'no_show' && <Alert severity="warning">O prazo pra retirar o veículo passou.</Alert>}
            </Stack>
        </Paper>
    );
}
