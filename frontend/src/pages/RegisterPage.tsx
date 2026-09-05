import Divider from '@mui/material/Divider';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Link from '@mui/material/Link';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Stack from '@mui/material/Stack';
import { useMutation } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { FormError } from '../components/FormError';
import { FormTextField } from '../components/FormTextField';
import { GoogleSignInButton } from '../components/GoogleSignInButton';
import { SubmitButton } from '../components/SubmitButton';
import { ApiError } from '../lib/apiClient';
import { loginWithGoogle, register, type RegisterInput } from '../lib/auth';

const initialForm: RegisterInput = { name: '', email: '', phone: '', password: '', role: 'seller' };

export function RegisterPage() {
    const navigate = useNavigate();
    const [form, setForm] = useState<RegisterInput>(initialForm);

    const mutation = useMutation({
        mutationFn: () => register(form),
        onSuccess: () => navigate('/me'),
    });
    const googleMutation = useMutation({
        mutationFn: (idToken: string) => loginWithGoogle(idToken),
        onSuccess: () => navigate('/me'),
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        mutation.mutate();
    }

    function set<K extends keyof RegisterInput>(field: K, value: RegisterInput[K]) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    const apiError = mutation.error instanceof ApiError ? mutation.error : null;
    const googleError = googleMutation.error instanceof ApiError ? googleMutation.error : null;

    return (
        <>
            <form onSubmit={handleSubmit}>
                <FormTextField
                    label="Nome"
                    value={form.name}
                    onChange={(event) => set('name', event.target.value)}
                    error={apiError?.errors?.name}
                    required
                />
                <FormTextField
                    label="E-mail"
                    type="email"
                    autoComplete="email"
                    value={form.email}
                    onChange={(event) => set('email', event.target.value)}
                    error={apiError?.errors?.email}
                    required
                />
                <FormTextField
                    label="Telefone"
                    value={form.phone}
                    onChange={(event) => set('phone', event.target.value)}
                    error={apiError?.errors?.phone}
                />
                <FormTextField
                    label="Senha"
                    type="password"
                    autoComplete="new-password"
                    value={form.password}
                    onChange={(event) => set('password', event.target.value)}
                    error={apiError?.errors?.password}
                    required
                />
                <FormControl fullWidth margin="normal">
                    <InputLabel id="register-role-label">Tipo de conta</InputLabel>
                    <Select
                        labelId="register-role-label"
                        label="Tipo de conta"
                        value={form.role}
                        onChange={(event) => set('role', event.target.value)}
                    >
                        <MenuItem value="seller">Vendedor</MenuItem>
                        <MenuItem value="customer">Cliente</MenuItem>
                    </Select>
                </FormControl>
                <FormError message={apiError?.message} />
                <Stack sx={{ mt: 2 }} spacing={2}>
                    <SubmitButton loading={mutation.isPending}>Criar conta</SubmitButton>
                    <Link component={RouterLink} to="/login" variant="body2" sx={{ textAlign: 'center' }}>
                        Já tenho conta
                    </Link>
                </Stack>
            </form>
            <Stack spacing={2} sx={{ mt: 2, alignItems: 'center' }}>
                <Divider sx={{ width: '100%' }}>ou</Divider>
                <GoogleSignInButton onCredential={(idToken) => googleMutation.mutate(idToken)} />
                <FormError message={googleError?.message} />
            </Stack>
        </>
    );
}
