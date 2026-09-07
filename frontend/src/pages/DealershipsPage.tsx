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
import { DealershipFormDialog } from '../components/DealershipFormDialog';
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
    type DealershipStatus,
} from '../lib/dealerships';

const PER_PAGE = 10;

const STATUS_LABEL: Record<DealershipStatus, string> = {
    active: 'Ativa',
    trashed: 'Na lixeira',
    deleted: 'Removida',
};

type ConfirmAction = { type: 'trash' | 'purge'; dealership: Dealership };

/** Seller gerencia só as próprias (RLS já escopa a listagem); admin vê e edita qualquer uma, inclusive reassocia dono. */
export function DealershipsPage() {
    const queryClient = useQueryClient();
    const [page, setPage] = useState(1);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Dealership | null>(null);
    const [photoDealership, setPhotoDealership] = useState<Dealership | null>(null);
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
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell />
                            <TableCell>Nome</TableCell>
                            <TableCell>Cidade</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {dealerships.data.data.map((dealership) => (
                            <TableRow key={dealership.id}>
                                <TableCell sx={{ width: 48 }}>
                                    <Avatar src={dealership.photo_url ?? undefined} variant="rounded">
                                        {dealership.name.charAt(0)}
                                    </Avatar>
                                </TableCell>
                                <TableCell>{dealership.name}</TableCell>
                                <TableCell>
                                    {dealership.city}/{dealership.state}
                                </TableCell>
                                <TableCell>
                                    <Chip
                                        size="small"
                                        label={STATUS_LABEL[dealership.status]}
                                        color={dealership.status === 'active' ? 'success' : 'default'}
                                    />
                                </TableCell>
                                <TableCell align="right">
                                    <Stack direction="row" spacing={1} sx={{ justifyContent: 'flex-end' }}>
                                        <Button size="small" onClick={() => openEdit(dealership)}>
                                            Editar
                                        </Button>
                                        <Button size="small" onClick={() => setPhotoDealership(dealership)}>
                                            Foto
                                        </Button>
                                        {dealership.status === 'active' && (
                                            <Button
                                                size="small"
                                                color="error"
                                                onClick={() => setConfirmAction({ type: 'trash', dealership })}
                                            >
                                                Mover pra lixeira
                                            </Button>
                                        )}
                                        {dealership.status === 'trashed' && (
                                            <>
                                                <Button
                                                    size="small"
                                                    disabled={restoreMutation.isPending}
                                                    onClick={() => restoreMutation.mutate(dealership.id)}
                                                >
                                                    Restaurar
                                                </Button>
                                                <Button
                                                    size="small"
                                                    color="error"
                                                    onClick={() => setConfirmAction({ type: 'purge', dealership })}
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
        </Paper>
    );
}
