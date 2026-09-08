import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Pagination from '@mui/material/Pagination';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { VehicleFilterBar } from '../components/VehicleFilterBar';
import { VehicleFormDialog } from '../components/VehicleFormDialog';
import { VehicleGalleryDialog } from '../components/VehicleGalleryDialog';
import { Toast } from '../components/Toast';
import { ApiError } from '../lib/apiClient';
import { getMe } from '../lib/auth';
import {
    createVehicle,
    getVehicleFacets,
    listVehicles,
    purgeVehicle,
    restoreVehicle,
    trashVehicle,
    updateVehicle,
    type Vehicle,
    type VehicleFilterParams,
    type VehicleInput,
    type VehicleStatus,
} from '../lib/vehicles';

const PER_PAGE = 10;

const STATUS_LABEL: Record<VehicleStatus, string> = {
    active: 'Ativo',
    trashed: 'Na lixeira',
    deleted: 'Removido',
};

type ConfirmAction = { type: 'trash' | 'purge'; vehicle: Vehicle };

/** Seller gerencia só o próprio estoque (RLS já escopa via `scope=mine`); admin vê e edita qualquer veículo. */
export function VehiclesPage() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<VehicleFilterParams>({});
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Vehicle | null>(null);
    const [galleryVehicle, setGalleryVehicle] = useState<Vehicle | null>(null);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null);
    const [toastMessage, setToastMessage] = useState<string | null>(null);

    const me = useQuery({ queryKey: ['me'], queryFn: getMe });

    const vehicles = useQuery({
        queryKey: ['vehicles', 'mine', filters, page],
        queryFn: () => listVehicles(filters, page, PER_PAGE),
    });
    const facets = useQuery({
        queryKey: ['vehicles', 'mine', 'filters'],
        queryFn: () => getVehicleFacets(),
    });

    function invalidateList() {
        void queryClient.invalidateQueries({ queryKey: ['vehicles'] });
    }

    const createMutation = useMutation({
        mutationFn: createVehicle,
        onSuccess: () => {
            setFormOpen(false);
            invalidateList();
        },
    });
    const updateMutation = useMutation({
        mutationFn: ({ id, input }: { id: string; input: Partial<VehicleInput> }) => updateVehicle(id, input),
        onSuccess: () => {
            setFormOpen(false);
            setEditing(null);
            invalidateList();
        },
    });
    const trashMutation = useMutation({
        mutationFn: trashVehicle,
        onSuccess: () => {
            setConfirmAction(null);
            invalidateList();
        },
    });
    const restoreMutation = useMutation({
        mutationFn: restoreVehicle,
        onSuccess: () => {
            invalidateList();
            setToastMessage('Veículo restaurado.');
        },
    });
    const purgeMutation = useMutation({
        mutationFn: purgeVehicle,
        onSuccess: () => {
            setConfirmAction(null);
            invalidateList();
        },
    });

    const formError =
        (createMutation.error instanceof ApiError && createMutation.error) ||
        (updateMutation.error instanceof ApiError && updateMutation.error) ||
        null;

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    function openEdit(vehicle: Vehicle) {
        setEditing(vehicle);
        setFormOpen(true);
    }

    function handleFormSubmit(input: VehicleInput) {
        if (editing) {
            updateMutation.mutate({ id: editing.id, input });
        } else {
            createMutation.mutate(input);
        }
    }

    if (me.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress />
            </Box>
        );
    }

    // Backend já bloqueia customer (403); redireciona aqui pra não mostrar uma tela quebrada esperando dados que nunca vêm.
    if (me.data?.role === 'customer') {
        return <Navigate to="/me" replace />;
    }

    return (
        <Paper sx={{ p: 3 }}>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6" component="h1">
                    Veículos
                </Typography>
                <Button variant="contained" onClick={openCreate}>
                    Novo veículo
                </Button>
            </Stack>

            <VehicleFilterBar
                value={filters}
                facets={facets.data}
                onChange={(value) => {
                    setFilters(value);
                    setPage(1);
                }}
            />

            {vehicles.isError && <Alert severity="error">Não foi possível carregar os veículos.</Alert>}

            {vehicles.data?.data.length === 0 && (
                <Alert severity="info">
                    Nenhum veículo encontrado -- ajuste os filtros ou cadastre o primeiro acima.
                </Alert>
            )}

            {vehicles.data && vehicles.data.data.length > 0 && (
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell />
                            <TableCell>Veículo</TableCell>
                            <TableCell>Ano</TableCell>
                            <TableCell>Preço</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {vehicles.data.data.map((vehicle) => (
                            <TableRow key={vehicle.id}>
                                <TableCell sx={{ width: 48 }}>
                                    <Avatar src={vehicle.photo_url ?? undefined} variant="rounded">
                                        {vehicle.brand.charAt(0)}
                                    </Avatar>
                                </TableCell>
                                <TableCell>
                                    {vehicle.brand} {vehicle.model}
                                    {vehicle.version ? ` ${vehicle.version}` : ''}
                                </TableCell>
                                <TableCell>{vehicle.year ?? '-'}</TableCell>
                                <TableCell>
                                    {Number(vehicle.price).toLocaleString('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL',
                                    })}
                                </TableCell>
                                <TableCell>
                                    <Chip
                                        size="small"
                                        label={STATUS_LABEL[vehicle.status]}
                                        color={vehicle.status === 'active' ? 'success' : 'default'}
                                    />
                                </TableCell>
                                <TableCell align="right">
                                    <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                                        <Button size="small" onClick={() => openEdit(vehicle)}>
                                            Editar
                                        </Button>
                                        <Button size="small" onClick={() => setGalleryVehicle(vehicle)}>
                                            Galeria
                                        </Button>
                                        {vehicle.status === 'active' && (
                                            <Button
                                                size="small"
                                                color="error"
                                                onClick={() => setConfirmAction({ type: 'trash', vehicle })}
                                            >
                                                Mover pra lixeira
                                            </Button>
                                        )}
                                        {vehicle.status === 'trashed' && (
                                            <>
                                                <Button
                                                    size="small"
                                                    disabled={restoreMutation.isPending}
                                                    onClick={() => restoreMutation.mutate(vehicle.id)}
                                                >
                                                    Restaurar
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() => setConfirmAction({ type: 'purge', vehicle })}
                                                >
                                                    Excluir agora
                                                </Button>
                                            </>
                                        )}
                                    </Stack>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            {vehicles.data && vehicles.data.meta.last_page > 1 && (
                <Stack sx={{ alignItems: 'center', mt: 2 }}>
                    <Pagination
                        page={page}
                        count={vehicles.data.meta.last_page}
                        onChange={(_, value) => setPage(value)}
                    />
                </Stack>
            )}

            <VehicleFormDialog
                key={editing?.id ?? 'new'}
                open={formOpen}
                vehicle={editing}
                submitting={createMutation.isPending || updateMutation.isPending}
                error={formError}
                onSubmit={handleFormSubmit}
                onClose={() => setFormOpen(false)}
            />
            <VehicleGalleryDialog
                key={galleryVehicle?.id ?? 'none'}
                open={galleryVehicle !== null}
                vehicle={galleryVehicle}
                onClose={() => setGalleryVehicle(null)}
            />
            <ConfirmDialog
                open={confirmAction !== null}
                title={confirmAction?.type === 'purge' ? 'Excluir em definitivo?' : 'Mover pra lixeira?'}
                description={
                    confirmAction?.type === 'purge'
                        ? 'Anonimiza o veículo agora, sem esperar os 30 dias. Não pode ser desfeito.'
                        : 'Recuperável em até 30 dias pela própria tela de lixeira, ou excluído em definitivo antes disso.'
                }
                confirmLabel={confirmAction?.type === 'purge' ? 'Excluir' : 'Mover pra lixeira'}
                confirmColor="error"
                loading={trashMutation.isPending || purgeMutation.isPending}
                onConfirm={() => {
                    if (!confirmAction) {
                        return;
                    }

                    if (confirmAction.type === 'purge') {
                        purgeMutation.mutate(confirmAction.vehicle.id);
                    } else {
                        trashMutation.mutate(confirmAction.vehicle.id);
                    }
                }}
                onCancel={() => setConfirmAction(null)}
            />
            <Toast open={toastMessage !== null} message={toastMessage ?? ''} onClose={() => setToastMessage(null)} />
        </Paper>
    );
}
