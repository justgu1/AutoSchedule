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
import { DealershipFormDialog } from '../components/DealershipFormDialog';
import { DealershipManagementCard } from '../components/DealershipManagementCard';
import { DealershipPhotoDialog } from '../components/DealershipPhotoDialog';
import { Toast } from '../components/Toast';
import { ApiError } from '../lib/apiClient';
import { getMe } from '../lib/auth';
import {
    createDealership,
    listDealerships,
    purgeDealership,
    restoreDealership,
    trashDealership,
    updateDealership,
    type Dealership,
    type DealershipProfileInput,
} from '../lib/dealerships';

const PER_PAGE = 12;

type ConfirmAction = { type: 'trash' | 'purge'; dealership: Dealership };

/** Seller gerencia só as próprias (RLS já escopa a listagem); admin vê e edita qualquer uma, inclusive reassocia dono. */
export function DealershipsPage() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Dealership | null>(null);
    const [photoDealership, setPhotoDealership] = useState<Dealership | null>(null);
    const [availabilityDealership, setAvailabilityDealership] = useState<Dealership | null>(null);
    const [confirmAction, setConfirmAction] = useState<ConfirmAction | null>(null);
    const [toastMessage, setToastMessage] = useState<string | null>(null);

    const me = useQuery({ queryKey: ['me'], queryFn: getMe });
    const isAdmin = me.data?.role === 'admin';

    const dealerships = useQuery({
        queryKey: ['dealerships', page],
        queryFn: () => listDealerships(page, PER_PAGE),
    });

    function invalidateList() {
        void queryClient.invalidateQueries({ queryKey: ['dealerships'] });
    }

    const createMutation = useMutation({
        mutationFn: createDealership,
        onSuccess: () => {
            setFormOpen(false);
            invalidateList();
        },
    });
    const updateMutation = useMutation({
        mutationFn: ({ id, input }: { id: string; input: Partial<DealershipProfileInput> }) =>
            updateDealership(id, input),
        onSuccess: () => {
            setFormOpen(false);
            setEditing(null);
            invalidateList();
        },
    });
    const trashMutation = useMutation({
        mutationFn: trashDealership,
        onSuccess: () => {
            setConfirmAction(null);
            invalidateList();
        },
    });
    const restoreMutation = useMutation({
        mutationFn: restoreDealership,
        onSuccess: () => {
            invalidateList();
            setToastMessage('Concessionária restaurada.');
        },
    });
    const purgeMutation = useMutation({
        mutationFn: purgeDealership,
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

    function openEdit(dealership: Dealership) {
        setEditing(dealership);
        setFormOpen(true);
    }

    function handleFormSubmit(input: DealershipProfileInput) {
        if (editing) {
            updateMutation.mutate({ id: editing.id, input });
        } else {
            createMutation.mutate(input);
        }
    }

    if (me.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress aria-label="Carregando" />
            </Box>
        );
    }

    // Backend já bloqueia customer (403); redireciona aqui pra não mostrar uma tela quebrada esperando dados que nunca vêm.
    if (me.data?.role === 'customer') {
        return <Navigate to="/me" replace />;
    }

    return (
        <>
            <Breadcrumb items={[{ label: 'Concessionárias' }]} />
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6" component="h1">
                    Concessionárias
                </Typography>
                <Button variant="contained" onClick={openCreate}>
                    Nova concessionária
                </Button>
            </Stack>

            {dealerships.isError && <Alert severity="error">Não foi possível carregar as concessionárias.</Alert>}

            {dealerships.data?.data.length === 0 && (
                <Alert severity="info">Nenhuma concessionária ainda -- crie a primeira acima.</Alert>
            )}

            {dealerships.data && dealerships.data.data.length > 0 && (
                <Grid container spacing={2}>
                    {dealerships.data.data.map((dealership) => (
                        <Grid key={dealership.id} size={{ xs: 12, sm: 6, md: 4, lg: 3 }}>
                            <DealershipManagementCard
                                dealership={dealership}
                                onEdit={() => openEdit(dealership)}
                                onPhoto={() => setPhotoDealership(dealership)}
                                onAvailability={() => setAvailabilityDealership(dealership)}
                                onTrash={() => setConfirmAction({ type: 'trash', dealership })}
                                onRestore={() => restoreMutation.mutate(dealership.id)}
                                onPurge={() => setConfirmAction({ type: 'purge', dealership })}
                                restoring={restoreMutation.isPending}
                            />
                        </Grid>
                    ))}
                </Grid>
            )}

            {dealerships.data && dealerships.data.meta.last_page > 1 && (
                <Stack sx={{ alignItems: 'center', mt: 2 }}>
                    <Pagination
                        page={page}
                        count={dealerships.data.meta.last_page}
                        onChange={(_, value) => setPage(value)}
                    />
                </Stack>
            )}

            <DealershipFormDialog
                key={editing?.id ?? 'new'}
                open={formOpen}
                dealership={editing}
                isAdmin={isAdmin}
                myPhone={me.data?.phone}
                myEmail={me.data?.email}
                submitting={createMutation.isPending || updateMutation.isPending}
                error={formError}
                onSubmit={handleFormSubmit}
                onClose={() => setFormOpen(false)}
            />
            <DealershipPhotoDialog
                key={photoDealership?.id ?? 'none'}
                open={photoDealership !== null}
                dealership={photoDealership}
                onClose={() => setPhotoDealership(null)}
            />
            <AvailabilityDialog
                open={availabilityDealership !== null}
                scope={availabilityDealership ? { type: 'dealership', id: availabilityDealership.id } : null}
                onClose={() => setAvailabilityDealership(null)}
            />
            <ConfirmDialog
                open={confirmAction !== null}
                title={confirmAction?.type === 'purge' ? 'Excluir em definitivo?' : 'Mover pra lixeira?'}
                description={
                    confirmAction?.type === 'purge'
                        ? 'Anonimiza a concessionária agora, sem esperar os 30 dias. Não pode ser desfeito.'
                        : 'Recuperável em até 30 dias pela própria tela de lixeira, ou anonimizada em definitivo antes disso.'
                }
                confirmLabel={confirmAction?.type === 'purge' ? 'Excluir' : 'Mover pra lixeira'}
                confirmColor="error"
                loading={trashMutation.isPending || purgeMutation.isPending}
                onConfirm={() => {
                    if (!confirmAction) {
                        return;
                    }

                    if (confirmAction.type === 'purge') {
                        purgeMutation.mutate(confirmAction.dealership.id);
                    } else {
                        trashMutation.mutate(confirmAction.dealership.id);
                    }
                }}
                onCancel={() => setConfirmAction(null)}
            />
            <Toast open={toastMessage !== null} message={toastMessage ?? ''} onClose={() => setToastMessage(null)} />
        </>
    );
}
