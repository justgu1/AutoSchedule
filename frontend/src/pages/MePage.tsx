import Button from '@mui/material/Button';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { becomeSeller, getMe } from '../lib/auth';

const ROLE_LABEL: Record<string, string> = {
    admin: 'Administrador',
    seller: 'Vendedor',
    customer: 'Cliente',
};

/** Confirmação do fluxo ponta a ponta: se chegou até aqui, o cookie de sessão prova quem é o usuário. */
export function MePage() {
    const queryClient = useQueryClient();
    const { data: me } = useQuery({ queryKey: ['me'], queryFn: getMe });
    const becomeSellerMutation = useMutation({
        mutationFn: becomeSeller,
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ['me'] }),
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
