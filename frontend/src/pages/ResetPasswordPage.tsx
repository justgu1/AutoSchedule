import Alert from '@mui/material/Alert';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link as RouterLink, useSearchParams } from 'react-router-dom';
import { FormError } from '../components/FormError';
import { FormTextField } from '../components/FormTextField';
import { SubmitButton } from '../components/SubmitButton';
import { ApiError } from '../lib/apiClient';
import { confirmPasswordReset } from '../lib/auth';

export function ResetPasswordPage() {
    const [searchParams] = useSearchParams();
    const token = searchParams.get('token') ?? '';
    const [password, setPassword] = useState('');

    const mutation = useMutation({ mutationFn: () => confirmPasswordReset(token, password) });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        mutation.mutate();
    }

    const apiError = mutation.error instanceof ApiError ? mutation.error : null;

    if (!token) {
        return <Alert severity="error">Link inválido -- faça um novo pedido de redefinição de senha.</Alert>;
    }

    if (mutation.isSuccess) {
        return (
            <Stack spacing={2}>
                <Alert severity="success">Senha redefinida. Já dá pra entrar com a senha nova.</Alert>
                <Link component={RouterLink} to="/login" variant="body2" sx={{ textAlign: 'center' }}>
                    Ir para o login
                </Link>
            </Stack>
        );
    }

    return (
        <form onSubmit={handleSubmit}>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Escolha a nova senha da sua conta.
            </Typography>
            <FormTextField
                label="Nova senha"
                type="password"
                autoComplete="new-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                error={apiError?.errors?.password}
                required
            />
            <FormError message={apiError?.message} />
            <SubmitButton loading={mutation.isPending} sx={{ mt: 2 }}>
                Redefinir senha
            </SubmitButton>
        </form>
    );
}
