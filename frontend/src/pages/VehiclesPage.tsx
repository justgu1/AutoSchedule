import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Grid from '@mui/material/Grid';
import Pagination from '@mui/material/Pagination';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { AvailabilityDialog } from '../components/AvailabilityDialog';
import { Breadcrumb } from '../components/Breadcrumb';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { FilterSidebar } from '../components/FilterSidebar';
import { VehicleFormDialog } from '../components/VehicleFormDialog';
import { VehicleGalleryDialog } from '../components/VehicleGalleryDialog';
import { VehicleManagementCard } from '../components/VehicleManagementCard';
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
} from '../lib/vehicles';

const PER_PAGE = 12;

type ConfirmAction = { type: 'trash' | 'purge'; vehicle: Vehicle };

/** Seller gerencia só o próprio estoque (RLS já escopa via `scope=mine`); admin vê e edita qualquer veículo. */
export function VehiclesPage() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [filters, setFilters] = useState<VehicleFilterParams>({});
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Vehicle | null>(null);
    const [galleryVehicle, setGalleryVehicle] = useState<Vehicle | null>(null);
    const [availabilityVehicle, setAvailabilityVehicle] = useState<Vehicle | null>(null);
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
        <Stack direction={{ xs: 'column', md: 'row' }} spacing={3} sx={{ alignItems: 'flex-start' }}>
            <FilterSidebar
                value={filters}
                facets={facets.data}
                onChange={(value) => {
                    setFilters(value);
                    setPage(1);
                }}
            />

            <Stack spacing={2} sx={{ flex: 1, minWidth: 0, width: '100%' }}>
                <Breadcrumb items={[{ label: 'Veículos' }]} />
                <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center' }}>
                    <Typography variant="h6" component="h1">
                        Veículos
                    </Typography>
                    <Button variant="contained" onClick={openCreate}>
                        Novo veículo
                    </Button>
                </Stack>

                {vehicles.isError && <Alert severity="error">Não foi possível carregar os veículos.</Alert>}

                {vehicles.data?.data.length === 0 && (
                    <Alert severity="info">
                        Nenhum veículo encontrado -- ajuste os filtros ou cadastre o primeiro acima.
                    </Alert>
                )}

                {vehicles.data && vehicles.data.data.length > 0 && (
                    <Grid container spacing={2}>
                        {vehicles.data.data.map((vehicle) => (
                            <Grid key={vehicle.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                                <VehicleManagementCard
                                    vehicle={vehicle}
                                    onEdit={() => openEdit(vehicle)}
                                    onGallery={() => setGalleryVehicle(vehicle)}
                                    onAvailability={() => setAvailabilityVehicle(vehicle)}
                                    onTrash={() => setConfirmAction({ type: 'trash', vehicle })}
                                    onRestore={() => restoreMutation.mutate(vehicle.id)}
                                    onPurge={() => setConfirmAction({ type: 'purge', vehicle })}
                                    restoring={restoreMutation.isPending}
                                />
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
            <AvailabilityDialog
                open={availabilityVehicle !== null}
                scope={availabilityVehicle ? { type: 'vehicle', id: availabilityVehicle.id } : null}
                onClose={() => setAvailabilityVehicle(null)}
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
        </Stack>
    );
}
