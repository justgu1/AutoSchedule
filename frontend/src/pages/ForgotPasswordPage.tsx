import Alert from '@mui/material/Alert';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import { FormError } from '../components/FormError';
import { FormTextField } from '../components/FormTextField';
import { SubmitButton } from '../components/SubmitButton';
import { ApiError } from '../lib/apiClient';
import { requestPasswordReset } from '../lib/auth';

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const mutation = useMutation({ mutationFn: () => requestPasswordReset(email) });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        mutation.mutate();
    }

    const apiError = mutation.error instanceof ApiError ? mutation.error : null;

    if (mutation.isSuccess) {
        return (
            <Stack spacing={2}>
                <Typography variant="body2" color="text.secondary">
                    Esqueceu sua senha? Informe seu e-mail abaixo.
                </Typography>
                <Alert severity="success">Se o e-mail existir, um link de redefinição foi enviado.</Alert>
                <Link component={RouterLink} to="/login" variant="body2" sx={{ textAlign: 'center' }}>
                    Voltar pro login
                </Link>
            </Stack>
        );
    }

    return (
        <form onSubmit={handleSubmit}>
            <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
                Informe o e-mail da sua conta -- vamos mandar um link pra redefinir a senha.
            </Typography>
            <FormTextField
                label="E-mail"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                error={apiError?.errors?.email}
                required
            />
            <FormError message={apiError?.message} />
            <Stack sx={{ mt: 2 }} spacing={2}>
                <SubmitButton loading={mutation.isPending}>Enviar link</SubmitButton>
                <Link component={RouterLink} to="/login" variant="body2" sx={{ textAlign: 'center' }}>
                    Voltar pro login
                </Link>
            </Stack>
        </form>
    );
}
