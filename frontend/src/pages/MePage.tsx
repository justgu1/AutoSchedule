import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { ConfirmDialog } from '../components/ConfirmDialog';
import { Toast } from '../components/Toast';
import { becomeSeller, deactivateAccount, getMe } from '../lib/auth';

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
        <Paper sx={{ p: 3, maxWidth: 480 }}>
            <Typography variant="h6" component="h1" gutterBottom>
                Meu perfil
            </Typography>
            <Stack spacing={1.5}>
                <Field label="Nome" value={me.name} />
                <Field label="E-mail" value={me.email} />
                <Field label="Telefone" value={me.phone ?? '-'} />
                <Field label="Tipo de conta" value={ROLE_LABEL[me.role] ?? me.role} />
            </Stack>
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
        </Paper>
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
