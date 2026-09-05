import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { FormError } from '../components/FormError';
import { FormTextField } from '../components/FormTextField';
import { SubmitButton } from '../components/SubmitButton';
import { ApiError } from '../lib/apiClient';
import { login } from '../lib/auth';

export function LoginPage() {
    const navigate = useNavigate();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');

    const mutation = useMutation({
        mutationFn: () => login(email, password),
        onSuccess: () => navigate('/me'),
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        mutation.mutate();
    }

    const apiError = mutation.error instanceof ApiError ? mutation.error : null;

    return (
        <form onSubmit={handleSubmit}>
            <FormTextField
                label="E-mail"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                error={apiError?.errors?.email}
                required
            />
            <FormTextField
                label="Senha"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                error={apiError?.errors?.password}
                required
            />
            <FormError message={apiError?.message} />
            <Stack sx={{ mt: 2 }} spacing={2}>
                <SubmitButton loading={mutation.isPending}>Entrar</SubmitButton>
                <Stack direction="row" sx={{ justifyContent: 'space-between' }}>
                    <Link component={RouterLink} to="/forgot-password" variant="body2">
                        Esqueci minha senha
                    </Link>
                    <Link component={RouterLink} to="/register" variant="body2">
                        Criar conta
                    </Link>
                </Stack>
            </Stack>
        </form>
    );
}
