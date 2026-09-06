import Divider from '@mui/material/Divider';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';
import { FormError } from '../components/FormError';
import { FormTextField } from '../components/FormTextField';
import { GoogleSignInButton } from '../components/GoogleSignInButton';
import { SubmitButton } from '../components/SubmitButton';
import { Toast } from '../components/Toast';
import { ApiError } from '../lib/apiClient';
import { login, loginWithGoogle } from '../lib/auth';

export function LoginPage() {
    const navigate = useNavigate();
    const location = useLocation();
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [noticeOpen, setNoticeOpen] = useState(Boolean((location.state as { message?: string } | null)?.message));
    const notice = (location.state as { message?: string } | null)?.message ?? '';

    const mutation = useMutation({
        mutationFn: () => login(email, password),
        onSuccess: (result) => navigate('/me', { state: { accountRestored: result.accountRestored } }),
    });
    const googleMutation = useMutation({
        mutationFn: (idToken: string) => loginWithGoogle(idToken),
        onSuccess: (result) => navigate('/me', { state: { accountRestored: result.accountRestored } }),
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        mutation.mutate();
    }

    const apiError = mutation.error instanceof ApiError ? mutation.error : null;
    const googleError = googleMutation.error instanceof ApiError ? googleMutation.error : null;

    return (
        <>
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
            <Stack spacing={2} sx={{ mt: 2, alignItems: 'center' }}>
                <Divider sx={{ width: '100%' }}>ou</Divider>
                <GoogleSignInButton onCredential={(idToken) => googleMutation.mutate(idToken)} />
                <FormError message={googleError?.message} />
            </Stack>
            <Toast open={noticeOpen} message={notice} onClose={() => setNoticeOpen(false)} />
        </>
    );
}
