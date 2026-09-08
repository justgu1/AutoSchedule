import DeleteIcon from '@mui/icons-material/Delete';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
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
import { useLocation, useNavigate } from 'react-router-dom';
import { Breadcrumb } from '../components/Breadcrumb';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { RevealSecretDialog } from '../components/RevealSecretDialog';
import { Toast } from '../components/Toast';
import {
    createApiClient,
    listApiClients,
    revokeApiClient,
    rotateApiClientSecret,
    type ApiClient,
    type ApiClientWithSecret,
} from '../lib/apiClients';
import { becomeSeller, deactivateAccount, getMe, updateMe } from '../lib/auth';

const ROLE_LABEL: Record<string, string> = {
    admin: 'Administrador',
    seller: 'Vendedor',
    customer: 'Cliente',
};

/** Confirmação do fluxo ponta a ponta: se chegou até aqui, o cookie de sessão prova quem é o usuário. */
export function MePage() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const location = useLocation();
    const [confirmDeactivateOpen, setConfirmDeactivateOpen] = useState(false);
    // Estado de navegação (não a URL) -- some sozinho se a página for recarregada, não fica preso no histórico.
    const [restoredNoticeOpen, setRestoredNoticeOpen] = useState(
        Boolean((location.state as { accountRestored?: boolean } | null)?.accountRestored),
    );
    const { data: me } = useQuery({ queryKey: ['me'], queryFn: getMe });
    const becomeSellerMutation = useMutation({
        mutationFn: becomeSeller,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['me'] }),
    });
    const deactivateMutation = useMutation({
        mutationFn: deactivateAccount,
        onSuccess: () => {
            queryClient.removeQueries({ queryKey: ['me'] });
            void navigate('/login', {
                replace: true,
                state: { message: 'Sua conta foi desativada com sucesso. Faça login em até 30 dias para restaurá-la.' },
            });
        },
    });

    if (!me) {
        return null;
    }

    return (
        <Stack spacing={3} sx={{ maxWidth: 640 }}>
            <Breadcrumb items={[{ label: 'Meu perfil' }]} />
            <Paper sx={{ p: 3 }}>
                <Typography variant="h6" component="h1" gutterBottom>
                    Meu perfil
                </Typography>
                <ProfileFields me={me} />
                {me.role === 'customer' && (
                    <Button
                        sx={{ mt: 2 }}
                        variant="outlined"
                        disabled={becomeSellerMutation.isPending}
                        onClick={() => becomeSellerMutation.mutate()}
                    >
                        Tornar-se vendedor
                    </Button>
                )}
                <Button
                    sx={{ mt: 2, ml: me.role === 'customer' ? 1 : 0 }}
                    variant="outlined"
                    color="error"
                    disabled={deactivateMutation.isPending}
                    onClick={() => setConfirmDeactivateOpen(true)}
                >
                    Desativar minha conta
                </Button>
            </Paper>
            <ApiClientsSection />
            <ConfirmDialog
                open={confirmDeactivateOpen}
                title="Desativar sua conta?"
                description="Você pode recuperá-la fazendo login de novo em até 30 dias. Depois disso, ela é apagada em definitivo."
                confirmLabel="Desativar"
                confirmColor="error"
                loading={deactivateMutation.isPending}
                onConfirm={() => deactivateMutation.mutate()}
                onCancel={() => setConfirmDeactivateOpen(false)}
            />
            <Toast
                open={restoredNoticeOpen}
                message="Sua conta estava desativada e foi restaurada automaticamente."
                onClose={() => setRestoredNoticeOpen(false)}
            />
        </Stack>
    );
}

function ProfileFields({ me }: { me: { name: string; email: string; phone: string | null; role: string } }) {
    const queryClient = useQueryClient();
    const [editing, setEditing] = useState(false);
    const [name, setName] = useState(me.name);
    const [phone, setPhone] = useState(me.phone ?? '');
    const updateMutation = useMutation({
        mutationFn: updateMe,
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['me'] });
            setEditing(false);
        },
    });

    if (!editing) {
        return (
            <Stack spacing={1.5}>
                <Field label="Nome" value={me.name} />
                <Field label="E-mail" value={me.email} />
                <Field label="Telefone" value={me.phone ?? '-'} />
                <Field label="Tipo de conta" value={ROLE_LABEL[me.role] ?? me.role} />
                <Button sx={{ alignSelf: 'flex-start' }} onClick={() => setEditing(true)}>
                    Editar
                </Button>
            </Stack>
        );
    }

    return (
        <Stack spacing={2}>
            <TextField label="Nome" value={name} onChange={(event) => setName(event.target.value)} fullWidth />
            <TextField label="Telefone" value={phone} onChange={(event) => setPhone(event.target.value)} fullWidth />
            <Stack direction="row" spacing={1}>
                <Button
                    variant="contained"
                    disabled={updateMutation.isPending}
                    onClick={() => updateMutation.mutate({ name, phone: phone === '' ? undefined : phone })}
                >
                    Salvar
                </Button>
                <Button
                    disabled={updateMutation.isPending}
                    onClick={() => {
                        setName(me.name);
                        setPhone(me.phone ?? '');
                        setEditing(false);
                    }}
                >
                    Cancelar
                </Button>
            </Stack>
        </Stack>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
            <Typography color="text.secondary">{label}</Typography>
            <Typography>{value}</Typography>
        </Stack>
    );
}

/** Credenciais m2m (`client_credentials`) pra integração de API -- o client autentica como o próprio dono. */
function ApiClientsSection() {
    const queryClient = useQueryClient();
    const [revealed, setRevealed] = useState<ApiClientWithSecret | null>(null);
    const [pendingRevokeId, setPendingRevokeId] = useState<string | null>(null);
    const { data: clients } = useQuery({ queryKey: ['api-clients'], queryFn: listApiClients });

    function invalidate() {
        return queryClient.invalidateQueries({ queryKey: ['api-clients'] });
    }

    const createMutation = useMutation({
        mutationFn: createApiClient,
        onSuccess: (client) => {
            setRevealed(client);
            void invalidate();
        },
    });
    const rotateMutation = useMutation({
        mutationFn: rotateApiClientSecret,
        onSuccess: (client) => {
            setRevealed(client);
            void invalidate();
        },
    });
    const revokeMutation = useMutation({
        mutationFn: revokeApiClient,
        onSuccess: () => {
            setPendingRevokeId(null);
            void invalidate();
        },
    });

    return (
        <Paper sx={{ p: 3 }}>
            <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'center', mb: 2 }}>
                <Typography variant="h6" component="h2">
                    Credenciais de API
                </Typography>
                <Button
                    variant="outlined"
                    disabled={createMutation.isPending}
                    onClick={() => createMutation.mutate('Integração')}
                >
                    Gerar novo
                </Button>
            </Stack>
            {(clients ?? []).length === 0 ? (
                <Typography color="text.secondary">Nenhuma credencial gerada ainda.</Typography>
            ) : (
                <Table size="small">
                    <TableHead>
                        <TableRow>
                            <TableCell>Nome</TableCell>
                            <TableCell>Client ID</TableCell>
                            <TableCell>Status</TableCell>
                            <TableCell align="right">Ações</TableCell>
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {(clients ?? []).map((client) => (
                            <ApiClientRow
                                key={client.id}
                                client={client}
                                onRotate={() => rotateMutation.mutate(client.id)}
                                rotating={rotateMutation.isPending}
                                onRevoke={() => setPendingRevokeId(client.id)}
                            />
                        ))}
                    </TableBody>
                </Table>
            )}
            {revealed && (
                <RevealSecretDialog
                    open
                    clientId={revealed.client_id}
                    clientSecret={revealed.client_secret}
                    onClose={() => setRevealed(null)}
                />
            )}
            <ConfirmDialog
                open={pendingRevokeId !== null}
                title="Revogar credencial?"
                description="Chamadas feitas com esse client_id/secret param de funcionar imediatamente. Essa ação não pode ser desfeita."
                confirmLabel="Revogar"
                confirmColor="error"
                loading={revokeMutation.isPending}
                onConfirm={() => pendingRevokeId !== null && revokeMutation.mutate(pendingRevokeId)}
                onCancel={() => setPendingRevokeId(null)}
            />
        </Paper>
    );
}

function ApiClientRow({
    client,
    onRotate,
    rotating,
    onRevoke,
}: {
    client: ApiClient;
    onRotate: () => void;
    rotating: boolean;
    onRevoke: () => void;
}) {
    const revoked = client.revoked_at !== null;

    return (
        <TableRow>
            <TableCell>{client.name}</TableCell>
            <TableCell sx={{ fontFamily: 'monospace' }}>{client.client_id}</TableCell>
            <TableCell>
                <Chip size="small" label={revoked ? 'Revogado' : 'Ativo'} color={revoked ? 'default' : 'success'} />
            </TableCell>
            <TableCell align="right">
                {!revoked && (
                    <>
                        <Button size="small" disabled={rotating} onClick={onRotate}>
                            Rotacionar secret
                        </Button>
                        <IconButton aria-label="Revogar" size="small" color="error" onClick={onRevoke}>
                            <DeleteIcon fontSize="small" />
                        </IconButton>
                    </>
                )}
            </TableCell>
        </TableRow>
    );
}
