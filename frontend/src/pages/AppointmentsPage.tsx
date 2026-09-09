import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import MenuItem from '@mui/material/MenuItem';
import Pagination from '@mui/material/Pagination';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { Breadcrumb } from '../components/Breadcrumb';
import {
    cancelAppointment,
    confirmAppointment,
    listAppointments,
    pickupAppointment,
    releaseAppointment,
    type AppointmentStatus,
} from '../lib/appointments';
import { getMe } from '../lib/auth';
import { listVehicles } from '../lib/vehicles';

const PER_PAGE = 10;

const STATUS_LABEL: Record<AppointmentStatus, string> = {
    pending: 'Pendente',
    confirmed: 'Confirmado',
    completed: 'Concluído',
    cancelled: 'Cancelado',
    no_show: 'Não compareceu',
};

const STATUS_COLOR: Record<AppointmentStatus, 'warning' | 'info' | 'success' | 'default' | 'error'> = {
    pending: 'warning',
    confirmed: 'info',
    completed: 'success',
    cancelled: 'default',
    no_show: 'error',
};

/** Confirmar/cancelar normal é o cliente clicando no e-mail -- os botões aqui são só o override manual do painel. */
export function AppointmentsPage() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [status, setStatus] = useState<AppointmentStatus | ''>('');

    const me = useQuery({ queryKey: ['me'], queryFn: getMe });

    const appointments = useQuery({
        queryKey: ['appointments', status, page],
        queryFn: () => listAppointments(status === '' ? {} : { status }, page, PER_PAGE),
    });

    // O resumo do agendamento só traz `vehicle_id` -- junta com o próprio estoque pra exibir marca/modelo.
    const vehicles = useQuery({
        queryKey: ['vehicles', 'mine', 'for-appointments'],
        queryFn: () => listVehicles({}, 1, 100),
    });
    const vehicleLabel = new Map(
        vehicles.data?.data.map((vehicle) => [vehicle.id, `${vehicle.brand} ${vehicle.model}`]) ?? [],
    );

    function invalidateList() {
        void queryClient.invalidateQueries({ queryKey: ['appointments'] });
    }

    const confirmMutation = useMutation({ mutationFn: confirmAppointment, onSuccess: invalidateList });
    const cancelMutation = useMutation({ mutationFn: cancelAppointment, onSuccess: invalidateList });
    const pickupMutation = useMutation({ mutationFn: pickupAppointment, onSuccess: invalidateList });
    const releaseMutation = useMutation({ mutationFn: releaseAppointment, onSuccess: invalidateList });

    if (me.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress aria-label="Carregando" />
            </Box>
        );
    }

    if (me.data?.role === 'customer') {
        return <Navigate to="/me" replace />;
    }

    return (
        <>
            <Breadcrumb items={[{ label: 'Agendamentos' }]} />
            <Paper sx={{ p: 3 }}>
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                    <Typography variant="h6" component="h1">
                        Agendamentos
                    </Typography>
                    <TextField
                        select
                        label="Status"
                        size="small"
                        value={status}
                        onChange={(event) => {
                            setStatus(event.target.value as AppointmentStatus | '');
                            setPage(1);
                        }}
                        sx={{ minWidth: 200 }}
                    >
                        <MenuItem value="">Todos</MenuItem>
                        {Object.entries(STATUS_LABEL).map(([value, label]) => (
                            <MenuItem key={value} value={value}>
                                {label}
                            </MenuItem>
                        ))}
                    </TextField>
                </Stack>

                {appointments.isError && <Alert severity="error">Não foi possível carregar os agendamentos.</Alert>}

                {appointments.data?.data.length === 0 && <Alert severity="info">Nenhum agendamento encontrado.</Alert>}

                {appointments.data && appointments.data.data.length > 0 && (
                    <Table size="small">
                        <TableHead>
                            <TableRow>
                                <TableCell>Cliente</TableCell>
                                <TableCell>Veículo</TableCell>
                                <TableCell>Data/Hora</TableCell>
                                <TableCell>Status</TableCell>
                                <TableCell align="right">Ações</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {appointments.data.data.map((appointment) => (
                                <TableRow key={appointment.id}>
                                    <TableCell>
                                        {appointment.customer_name}
                                        <Typography variant="body2" color="text.secondary">
                                            {appointment.customer_email}
                                        </Typography>
                                    </TableCell>
                                    <TableCell>
                                        {vehicleLabel.get(appointment.vehicle_id) ?? appointment.vehicle_id}
                                    </TableCell>
                                    <TableCell>
                                        {new Date(appointment.scheduled_at).toLocaleString('pt-BR', {
                                            dateStyle: 'short',
                                            timeStyle: 'short',
                                        })}
                                    </TableCell>
                                    <TableCell>
                                        <Chip
                                            size="small"
                                            label={STATUS_LABEL[appointment.status]}
                                            color={STATUS_COLOR[appointment.status]}
                                        />
                                    </TableCell>
                                    <TableCell align="right">
                                        <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                                            {appointment.status === 'pending' && (
                                                <>
                                                    <Button
                                                        size="small"
                                                        onClick={() => confirmMutation.mutate(appointment.id)}
                                                    >
                                                        Confirmar
                                                    </Button>
                                                    <Button
                                                        size="small"
                                                        color="error"
                                                        onClick={() => cancelMutation.mutate(appointment.id)}
                                                    >
                                                        Cancelar
                                                    </Button>
                                                </>
                                            )}
                                            {appointment.status === 'confirmed' &&
                                                appointment.picked_up_at === null && (
                                                    <Button
                                                        size="small"
                                                        onClick={() => pickupMutation.mutate(appointment.id)}
                                                    >
                                                        Marcar retirada
                                                    </Button>
                                                )}
                                            {appointment.status === 'confirmed' &&
                                                appointment.picked_up_at !== null && (
                                                    <Button
                                                        size="small"
                                                        onClick={() => releaseMutation.mutate(appointment.id)}
                                                    >
                                                        Marcar devolução
                                                    </Button>
                                                )}
                                        </Stack>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                {appointments.data && appointments.data.meta.last_page > 1 && (
                    <Stack sx={{ alignItems: 'center', mt: 2 }}>
                        <Pagination
                            page={page}
                            count={appointments.data.meta.last_page}
                            onChange={(_, value) => setPage(value)}
                        />
                    </Stack>
                )}
            </Paper>
        </>
    );
}
